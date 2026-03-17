<?php

namespace App\Core;

use PDO;

/**
 * Model base com métodos CRUD genéricos.
 * Aplica tenant_id automaticamente em toda operação.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $tenantScoped = true;
    protected bool $softDelete = false;

    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Busca por ID (com escopo de tenant).
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $params = [$id];

        if ($this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->getTenantId();
        }

        if ($this->softDelete) {
            $sql .= " AND deleted_at IS NULL";
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Lista todos os registros com filtros opcionais.
     */
    public function all(array $conditions = [], string $orderBy = 'id DESC', ?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->getTenantId();
        }

        if ($this->softDelete) {
            $sql .= " AND deleted_at IS NULL";
        }

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $operator = $value[0];
                $val = $value[1];
                $sql .= " AND {$column} {$operator} ?";
                $params[] = $val;
            } else {
                $sql .= " AND {$column} = ?";
                $params[] = $value;
            }
        }

        $sql .= " ORDER BY {$orderBy}";

        if ($limit !== null) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Conta registros com condições.
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->getTenantId();
        }

        if ($this->softDelete) {
            $sql .= " AND deleted_at IS NULL";
        }

        foreach ($conditions as $column => $value) {
            $sql .= " AND {$column} = ?";
            $params[] = $value;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Insere um novo registro.
     * Adiciona tenant_id automaticamente.
     */
    public function create(array $data): int
    {
        if ($this->tenantScoped && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $this->getTenantId();
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza um registro por ID.
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets)
             . " WHERE {$this->primaryKey} = ?";
        $params[] = $id;

        if ($this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->getTenantId();
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Deleta um registro (soft ou hard delete).
     */
    public function delete(int $id): bool
    {
        if ($this->softDelete) {
            return $this->update($id, ['deleted_at' => now()]);
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $params = [$id];

        if ($this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->getTenantId();
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Busca registros com WHERE customizado.
     */
    public function where(string $column, mixed $value, string $operator = '='): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ?";
        $params = [$value];

        if ($this->tenantScoped) {
            $sql .= " AND tenant_id = ?";
            $params[] = $this->getTenantId();
        }

        if ($this->softDelete) {
            $sql .= " AND deleted_at IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Executa query customizada com segurança.
     */
    public function raw(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Inicia transação.
     */
    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    /**
     * Confirma transação.
     */
    public function commit(): void
    {
        $this->db->commit();
    }

    /**
     * Desfaz transação.
     */
    public function rollBack(): void
    {
        $this->db->rollBack();
    }

    protected function getTenantId(): int
    {
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if (!$tenantId) {
            throw new \RuntimeException('Tenant não identificado.');
        }
        return (int) $tenantId;
    }
}
