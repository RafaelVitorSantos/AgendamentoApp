<?php

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;

/**
 * Registra ações de auditoria para LGPD e segurança.
 */
class AuditService
{
    public static function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, old_data, new_data, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                TenantContext::get(),
                $_SESSION['user_id'] ?? null,
                $action,
                $entityType,
                $entityId,
                $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
                $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (\Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
