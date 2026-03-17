<?php

namespace App\Models;

use App\Core\Model;

class Unit extends Model
{
    protected string $table = 'units';
    protected bool $tenantScoped = true;
    protected bool $softDelete = true;

    public function getAllWithStats(): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*,
                    (SELECT COUNT(*) FROM professional_units pu WHERE pu.unit_id = u.id AND pu.tenant_id = u.tenant_id) as professional_count,
                    (SELECT COUNT(*) FROM appointments a WHERE a.unit_id = u.id AND a.tenant_id = u.tenant_id AND a.date = CURDATE()) as today_appointments
             FROM units u
             WHERE u.tenant_id = ? AND u.deleted_at IS NULL
             ORDER BY u.is_default DESC, u.name ASC"
        );
        $stmt->execute([$this->getTenantId()]);
        return $stmt->fetchAll();
    }
}
