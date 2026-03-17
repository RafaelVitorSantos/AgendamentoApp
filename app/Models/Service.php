<?php

namespace App\Models;

use App\Core\Model;

class Service extends Model
{
    protected string $table = 'services';
    protected bool $tenantScoped = true;
    protected bool $softDelete = true;

    public function getActiveWithCategory(): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, sc.name as category_name
             FROM services s
             LEFT JOIN service_categories sc ON sc.id = s.category_id
             WHERE s.tenant_id = ? AND s.is_active = 1 AND s.deleted_at IS NULL
             ORDER BY sc.sort_order ASC, s.sort_order ASC, s.name ASC"
        );
        $stmt->execute([$this->getTenantId()]);
        return $stmt->fetchAll();
    }

    public function getAllWithCategory(): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, sc.name as category_name
             FROM services s
             LEFT JOIN service_categories sc ON sc.id = s.category_id
             WHERE s.tenant_id = ? AND s.deleted_at IS NULL
             ORDER BY s.is_active DESC, sc.sort_order ASC, s.name ASC"
        );
        $stmt->execute([$this->getTenantId()]);
        return $stmt->fetchAll();
    }

    public function getCategories(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM service_categories WHERE tenant_id = ? ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute([$this->getTenantId()]);
        return $stmt->fetchAll();
    }

    public function createCategory(string $name): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO service_categories (tenant_id, name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())"
        );
        $stmt->execute([$this->getTenantId(), $name]);
        return (int) $this->db->lastInsertId();
    }
}
