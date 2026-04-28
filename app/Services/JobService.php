<?php

namespace App\Services;

use App\Core\Database;
use App\Jobs\BaseJob;

/**
 * Despacha jobs para a fila e fornece utilitários de inspeção.
 */
class JobService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Publica um job na fila com delay opcional.
     *
     * @param BaseJob $job
     * @param int     $delaySeconds  Segundos para aguardar antes de processar
     */
    public function dispatch(BaseJob $job, int $delaySeconds = 0): int
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);

        $stmt = $this->db->prepare(
            "INSERT INTO jobs (queue, payload, max_attempts, available_at, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $job->queue,
            $job->serialize(),
            $job->maxAttempts,
            $availableAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Publica vários jobs de uma vez (bulk insert).
     *
     * @param BaseJob[] $jobs
     */
    public function dispatchMany(array $jobs, int $delaySeconds = 0): void
    {
        if (empty($jobs)) return;

        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        $placeholders = implode(',', array_fill(0, count($jobs), '(?, ?, ?, ?, NOW())'));

        $params = [];
        foreach ($jobs as $job) {
            $params[] = $job->queue;
            $params[] = $job->serialize();
            $params[] = $job->maxAttempts;
            $params[] = $availableAt;
        }

        $this->db->prepare(
            "INSERT INTO jobs (queue, payload, max_attempts, available_at, created_at) VALUES {$placeholders}"
        )->execute($params);
    }

    /**
     * Total de jobs pendentes por fila.
     */
    public function pendingCount(string $queue = 'default'): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM jobs WHERE queue = ? AND reserved_at IS NULL AND available_at <= NOW()"
        );
        $stmt->execute([$queue]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Total de jobs com falha.
     */
    public function failedCount(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM failed_jobs")->fetchColumn();
    }
}
