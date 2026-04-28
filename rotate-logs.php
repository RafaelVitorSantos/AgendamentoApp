#!/usr/bin/env php
<?php

/**
 * Rotação de logs do AgendaPRO.
 * Mantém os últimos N arquivos de log, comprime os anteriores.
 *
 * Uso (adicionar ao cron diário):
 *   0 0 * * * php /caminho/para/rotate-logs.php >> /dev/null 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$logsDir  = base_path('storage/logs');
$maxFiles = (int) env('LOG_MAX_FILES', 7);
$date     = date('Y-m-d');

$patterns = ['*.log', 'php_errors.log'];

foreach (glob($logsDir . '/*.log') as $logFile) {
    if (!file_exists($logFile) || filesize($logFile) === 0) {
        continue;
    }

    $basename = basename($logFile, '.log');
    $rotated  = "{$logsDir}/{$basename}-{$date}.log";

    // Renomeia o log atual para o arquivo datado
    rename($logFile, $rotated);

    // Cria novo arquivo vazio
    touch($logFile);
    chmod($logFile, 0664);

    echo "Rotacionado: " . basename($logFile) . " → " . basename($rotated) . "\n";
}

// Remove arquivos mais antigos que $maxFiles dias
$allLogs = glob($logsDir . '/*-????-??-??.log');
if ($allLogs) {
    usort($allLogs, fn($a, $b) => filemtime($b) - filemtime($a));

    $toDelete = array_slice($allLogs, $maxFiles);
    foreach ($toDelete as $old) {
        unlink($old);
        echo "Removido: " . basename($old) . "\n";
    }
}

echo "Rotação concluída em " . date('Y-m-d H:i:s') . "\n";
