<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected bool $tenantScoped = true;
    protected bool $softDelete = true;

    /**
     * Busca por email (cross-tenant para login).
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name as role_name, t.slug as tenant_slug, t.status as tenant_status
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = ? AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retorna permissões do usuário (role + user overrides).
     */
    public function getPermissions(int $userId, int $roleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.name FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?
             UNION
             SELECT p.name FROM permissions p
             JOIN user_permissions up ON up.permission_id = p.id
             WHERE up.user_id = ? AND up.granted = 1"
        );
        $stmt->execute([$roleId, $userId]);
        return array_column($stmt->fetchAll(), 'name');
    }
}
