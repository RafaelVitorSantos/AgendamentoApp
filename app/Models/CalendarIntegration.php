<?php

namespace App\Models;

use App\Core\Model;

class CalendarIntegration extends Model
{
    protected string $table      = 'calendar_integrations';
    protected bool   $tenantScoped = true;

    public function findByUser(int $userId, string $provider = 'google'): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM calendar_integrations
             WHERE user_id = ? AND provider = ?
             LIMIT 1"
        );
        $stmt->execute([$userId, $provider]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ci.*,
                    u.name  AS user_name,
                    p.name  AS professional_name
             FROM calendar_integrations ci
             LEFT JOIN users         u ON u.id = ci.user_id
             LEFT JOIN professionals p ON p.id = ci.professional_id
             WHERE ci.tenant_id = ?
             ORDER BY ci.created_at DESC"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function toggleSync(int $id, int $tenantId, bool $enabled): void
    {
        $this->db->prepare(
            "UPDATE calendar_integrations
             SET sync_enabled = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?"
        )->execute([(int) $enabled, $id, $tenantId]);
    }

    public function remove(int $id, int $tenantId): void
    {
        $this->db->prepare(
            "DELETE FROM calendar_integrations WHERE id = ? AND tenant_id = ?"
        )->execute([$id, $tenantId]);
    }
}
