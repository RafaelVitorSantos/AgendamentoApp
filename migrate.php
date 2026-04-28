#!/usr/bin/env php
<?php

/**
 * CLI de migrations do AgendaPRO.
 *
 * Uso:
 *   php migrate.php           — executa migrations pendentes
 *   php migrate.php status    — lista status de todas as migrations
 *   php migrate.php make nome — cria arquivo de migration vazio
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Core\MigrationRunner;

$command = $argv[1] ?? 'run';

$runner = new MigrationRunner();

match ($command) {
    'status' => (function () use ($runner) {
        $rows = $runner->status();
        if (empty($rows)) {
            echo "Nenhuma migration encontrada em database/migrations/\n";
            return;
        }
        echo "\n  Status das Migrations\n";
        echo "  " . str_repeat('-', 60) . "\n";
        foreach ($rows as $row) {
            $icon = $row['status'] === 'applied' ? '✓' : '○';
            $date = $row['applied_at'] ? " ({$row['applied_at']})" : '';
            printf("  %s  %-45s %s\n", $icon, $row['file'], $date);
        }
        echo "\n";
    })(),

    'make' => (function () use ($argv) {
        $name = $argv[2] ?? null;
        if (!$name) {
            echo "Uso: php migrate.php make nome_da_migration\n";
            exit(1);
        }
        $dir      = __DIR__ . '/database/migrations';
        $existing = glob($dir . '/*.sql');
        $next     = count($existing) + 1;
        $filename = sprintf('%04d_%s.sql', $next, preg_replace('/[^a-z0-9_]/', '_', strtolower($name)));
        $path     = $dir . '/' . $filename;
        file_put_contents($path, "-- Migration: {$filename}\n-- Criada em: " . date('Y-m-d H:i:s') . "\n\n");
        echo "Migration criada: database/migrations/{$filename}\n";
    })(),

    default => (function () use ($runner) {
        echo "\n  Executando migrations pendentes...\n";
        $applied = $runner->run(verbose: true);
        if (empty($applied)) {
            echo "  Nenhuma migration pendente.\n\n";
        } else {
            echo "\n  " . count($applied) . " migration(s) aplicada(s) com sucesso.\n\n";
        }
    })(),
};
