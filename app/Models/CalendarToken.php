<?php

namespace App\Models;

use App\Core\Model;

class CalendarToken extends Model
{
    protected string $table      = 'calendar_tokens';
    protected bool   $tenantScoped = true;

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ct.*, u.name AS user_name, p.name AS professional_name,
                    t.uuid AS tenant_uuid, t.trade_name AS company_name
             FROM calendar_tokens ct
             LEFT JOIN users         u  ON u.id  = ct.user_id
             LEFT JOIN professionals p  ON p.id  = ct.professional_id
             LEFT JOIN tenants        t  ON t.id  = ct.tenant_id
             WHERE ct.token = ? AND ct.revoked_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            // Atualiza estatísticas de acesso (best-effort, não bloqueia resposta)
            $this->db->prepare(
                "UPDATE calendar_tokens
                 SET last_accessed_at = NOW(), access_count = access_count + 1
                 WHERE token = ?"
            )->execute([$token]);
        }

        return $row ?: null;
    }

    public function findForUser(int $userId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM calendar_tokens
             WHERE user_id = ? AND tenant_id = ? AND revoked_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$userId, $tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function generate(int $userId, int $tenantId, ?int $professionalId): array
    {
        // Revoga token anterior se existir
        $this->db->prepare(
            "UPDATE calendar_tokens SET revoked_at = NOW()
             WHERE user_id = ? AND tenant_id = ? AND revoked_at IS NULL"
        )->execute([$userId, $tenantId]);

        $token = bin2hex(random_bytes(32)); // 64-char hex token

        $this->db->prepare(
            "INSERT INTO calendar_tokens (tenant_id, user_id, professional_id, token, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        )->execute([$tenantId, $userId, $professionalId, $token]);

        return ['token' => $token, 'id' => (int) $this->db->lastInsertId()];
    }

    public function revoke(int $userId, int $tenantId): void
    {
        $this->db->prepare(
            "UPDATE calendar_tokens SET revoked_at = NOW()
             WHERE user_id = ? AND tenant_id = ? AND revoked_at IS NULL"
        )->execute([$userId, $tenantId]);
    }
}
