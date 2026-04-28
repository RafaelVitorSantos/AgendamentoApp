<?php

namespace App\Core;

use PDO;

/**
 * Executa e rastreia migrations SQL versionadas.
 *
 * Convenção de nome de arquivo: 0001_descricao.sql
 * A ordem de execução é determinada pelo prefixo numérico.
 */
class MigrationRunner
{
    private PDO    $db;
    private string $migrationsPath;

    public function __construct()
    {
        $this->db             = Database::getInstance();
        $this->migrationsPath = base_path('database/migrations');
        $this->ensureMigrationsTable();
    }

    /**
     * Executa todas as migrations pendentes.
     * Retorna lista de migrations aplicadas nesta execução.
     */
    public function run(bool $verbose = false): array
    {
        $pending = $this->getPending();
        $applied = [];

        foreach ($pending as $file) {
            $this->runMigration($file, $verbose);
            $applied[] = $file;
        }

        return $applied;
    }

    /**
     * Lista todas as migrations e seus status.
     */
    public function status(): array
    {
        $files   = $this->getFiles();
        $done    = $this->getApplied();
        $result  = [];

        foreach ($files as $file) {
            $result[] = [
                'file'       => $file,
                'status'     => in_array($file, $done) ? 'applied' : 'pending',
                'applied_at' => $done[$file] ?? null,
            ];
        }

        return $result;
    }

    private function runMigration(string $filename, bool $verbose): void
    {
        $path = $this->migrationsPath . '/' . $filename;
        $sql  = file_get_contents($path);

        if ($verbose) echo "  Applying: {$filename}\n";

        try {
            // DDL statements (CREATE TABLE, ALTER TABLE) fazem auto-commit implícito
            // no MySQL, tornando transações inúteis e causando erro no rollBack().
            // Executa cada statement diretamente, sem wrapper de transação.
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    $this->db->exec($statement);
                }
            }

            $this->db->prepare(
                "INSERT INTO migrations (filename, applied_at) VALUES (?, NOW())"
            )->execute([$filename]);

            if ($verbose) echo "  ✓ Done: {$filename}\n";
        } catch (\Throwable $e) {
            throw new \RuntimeException("Migration failed [{$filename}]: " . $e->getMessage(), 0, $e);
        }
    }

    private function getPending(): array
    {
        $all     = $this->getFiles();
        $applied = array_keys($this->getApplied());
        return array_values(array_diff($all, $applied));
    }

    private function getFiles(): array
    {
        if (!is_dir($this->migrationsPath)) return [];

        $files = glob($this->migrationsPath . '/*.sql');
        $names = array_map('basename', $files);
        sort($names);
        return $names;
    }

    private function getApplied(): array
    {
        $stmt = $this->db->query("SELECT filename, applied_at FROM migrations ORDER BY applied_at ASC");
        return array_column($stmt->fetchAll(), 'applied_at', 'filename');
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS migrations (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename   VARCHAR(255) NOT NULL UNIQUE,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}
