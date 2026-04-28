#!/usr/bin/env php
<?php
/**
 * Worker de filas assíncronas.
 *
 * Uso:
 *   php worker.php                    # fila padrão, roda indefinidamente
 *   php worker.php --queue=emails     # fila específica
 *   php worker.php --once             # processa um job e sai
 *   php worker.php --queue=emails --sleep=3 --max-jobs=500
 *
 * Opções:
 *   --queue=<nome>    Fila a processar (default: default)
 *   --sleep=<s>       Segundos de espera quando fila vazia (default: 5)
 *   --max-jobs=<n>    Máximo de jobs antes de reiniciar processo (default: 1000)
 *   --once            Processa um único job e sai
 */

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/bootstrap.php';

use App\Core\Database;

// ── Argumentos ──────────────────────────────────────────────────────────────
$opts   = getopt('', ['queue:', 'sleep:', 'max-jobs:', 'once']);
$queue  = $opts['queue']    ?? 'default';
$sleep  = (int) ($opts['sleep']   ?? 5);
$maxJobs = (int) ($opts['max-jobs'] ?? 1000);
$once   = isset($opts['once']);

// ── Worker ──────────────────────────────────────────────────────────────────
$db = Database::getInstance();
$processed = 0;

output("Worker iniciado. Fila: {$queue}");

while (true) {
    $job = fetchNextJob($db, $queue);

    if (!$job) {
        if ($once) {
            output("Nenhum job disponível. Encerrando.");
            exit(0);
        }
        sleep($sleep);
        continue;
    }

    processJob($db, $job);
    $processed++;

    if ($once || $processed >= $maxJobs) {
        output("Limite de {$maxJobs} jobs atingido. Reiniciando processo.");
        exit(0);
    }
}

// ── Funções ──────────────────────────────────────────────────────────────────

function fetchNextJob(\PDO $db, string $queue): ?array
{
    $db->beginTransaction();
    try {
        // Seleciona e reserva atomicamente via SELECT ... FOR UPDATE
        $stmt = $db->prepare(
            "SELECT * FROM jobs
             WHERE queue = ?
               AND reserved_at IS NULL
               AND available_at <= NOW()
             ORDER BY available_at ASC
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$queue]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
            $db->rollBack();
            return null;
        }

        $db->prepare("UPDATE jobs SET reserved_at = NOW(), attempts = attempts + 1 WHERE id = ?")
           ->execute([$job['id']]);

        $db->commit();
        return $job;
    } catch (\Throwable $e) {
        $db->rollBack();
        return null;
    }
}

function processJob(\PDO $db, array $jobRow): void
{
    $jobId = $jobRow['id'];
    output("Processando job #{$jobId} da fila '{$jobRow['queue']}' (tentativa {$jobRow['attempts']})");

    try {
        $job = \App\Jobs\BaseJob::fromRow($jobRow['payload']);

        if (!method_exists($job, 'handle')) {
            throw new \RuntimeException("Payload inválido para job #{$jobId}");
        }

        $job->handle();

        // Sucesso: remove da fila
        $db->prepare("DELETE FROM jobs WHERE id = ?")->execute([$jobId]);
        output("  ✓ Job #{$jobId} concluído.");

    } catch (\Throwable $e) {
        $attempts   = (int) $jobRow['attempts'];
        $maxAttempts = (int) ($jobRow['max_attempts'] ?? 3);

        output("  ✗ Job #{$jobId} falhou: " . $e->getMessage());

        if ($attempts >= $maxAttempts) {
            // Move para failed_jobs
            $db->prepare(
                "INSERT INTO failed_jobs (queue, payload, exception, failed_at)
                 VALUES (?, ?, ?, NOW())"
            )->execute([
                $jobRow['queue'],
                $jobRow['payload'],
                substr($e->getMessage() . "\n" . $e->getTraceAsString(), 0, 65535),
            ]);
            $db->prepare("DELETE FROM jobs WHERE id = ?")->execute([$jobId]);
            output("  → Movido para failed_jobs após {$attempts} tentativas.");
        } else {
            // Libera para nova tentativa com backoff exponencial
            $delay = 60 * (2 ** ($attempts - 1)); // 60s, 120s, 240s…
            $availableAt = date('Y-m-d H:i:s', time() + $delay);
            $db->prepare(
                "UPDATE jobs SET reserved_at = NULL, available_at = ? WHERE id = ?"
            )->execute([$availableAt, $jobId]);
            output("  → Reagendado para {$availableAt} (tentativa {$attempts}/{$maxAttempts}).");
        }
    }
}

function output(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}
