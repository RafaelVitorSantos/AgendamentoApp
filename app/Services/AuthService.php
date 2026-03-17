<?php

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use App\Models\User;
use App\Models\Tenant;

class AuthService
{
    private User $userModel;
    private Tenant $tenantModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->tenantModel = new Tenant();
    }

    /**
     * Autentica usuário por email/senha.
     * Retorna dados do usuário ou null se inválido.
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            $this->logAccess(null, null, 'login_failed', ['reason' => 'user_not_found']);
            return null;
        }

        if (!$user['is_active']) {
            $this->logAccess($user['id'], $user['tenant_id'], 'login_failed', ['reason' => 'inactive']);
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->logAccess($user['id'], $user['tenant_id'], 'login_failed', ['reason' => 'wrong_password']);
            return null;
        }

        if ($user['tenant_id'] && $user['tenant_status'] !== 'active' && $user['tenant_status'] !== 'trial') {
            $this->logAccess($user['id'], $user['tenant_id'], 'login_failed', ['reason' => 'tenant_' . $user['tenant_status']]);
            return null;
        }

        $this->createSession($user);
        $this->logAccess($user['id'], $user['tenant_id'], 'login_success');

        // Atualiza último login
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $user['id']]);

        // Rehash se necessário (upgrade de algoritmo)
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $newHash = $this->hashPassword($password);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
        }

        return $user;
    }

    /**
     * Cria sessão do usuário autenticado.
     */
    private function createSession(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['tenant_id']  = $user['tenant_id'] ? (int) $user['tenant_id'] : null;
        $_SESSION['role_id']    = (int) $user['role_id'];
        $_SESSION['role_name']  = $user['role_name'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['_login_time']    = time();
        $_SESSION['_last_activity'] = time();
        $_SESSION['_fingerprint']   = $this->fingerprint();

        // Carrega permissões
        $_SESSION['permissions'] = $this->userModel->getPermissions($user['id'], $user['role_id']);

        if ($user['tenant_id']) {
            TenantContext::set((int) $user['tenant_id']);
        }
    }

    /**
     * Registra nova empresa + administrador.
     */
    public function register(array $companyData, array $adminData): array
    {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $slug = generate_slug($companyData['company_name']);
            $existingSlug = $this->tenantModel->findBySlug($slug);
            if ($existingSlug) {
                $slug .= '-' . substr(uniqid(), -4);
            }

            // Cria tenant
            $stmtTenant = $db->prepare(
                "INSERT INTO tenants (uuid, company_name, trade_name, slug, email, phone, status, trial_ends_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'trial', DATE_ADD(NOW(), INTERVAL 14 DAY), NOW(), NOW())"
            );
            $stmtTenant->execute([
                generate_uuid(),
                $companyData['company_name'],
                $companyData['trade_name'] ?? $companyData['company_name'],
                $slug,
                $adminData['email'],
                $companyData['phone'] ?? null,
            ]);
            $tenantId = (int) $db->lastInsertId();

            // Cria admin
            $adminRoleStmt = $db->prepare("SELECT id FROM roles WHERE name = 'tenant_admin' LIMIT 1");
            $adminRoleStmt->execute();
            $roleId = (int) $adminRoleStmt->fetchColumn();

            $stmtUser = $db->prepare(
                "INSERT INTO users (tenant_id, role_id, name, email, password_hash, is_active, email_verified_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())"
            );
            $stmtUser->execute([
                $tenantId,
                $roleId,
                $adminData['name'],
                $adminData['email'],
                $this->hashPassword($adminData['password']),
            ]);
            $userId = (int) $db->lastInsertId();

            // Cria unidade padrão
            $stmtUnit = $db->prepare(
                "INSERT INTO units (tenant_id, name, slug, is_active, is_default, created_at, updated_at)
                 VALUES (?, 'Unidade Principal', 'principal', 1, 1, NOW(), NOW())"
            );
            $stmtUnit->execute([$tenantId]);

            // Cria assinatura gratuita
            $freePlanStmt = $db->prepare("SELECT id FROM plans WHERE slug = 'free' LIMIT 1");
            $freePlanStmt->execute();
            $freePlanId = (int) $freePlanStmt->fetchColumn();

            $stmtSub = $db->prepare(
                "INSERT INTO subscriptions (tenant_id, plan_id, status, billing_cycle, current_period_start, current_period_end, created_at, updated_at)
                 VALUES (?, ?, 'trialing', 'monthly', NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY), NOW(), NOW())"
            );
            $stmtSub->execute([$tenantId, $freePlanId]);

            // Cria categorias financeiras padrão
            $defaultCategories = [
                ['Serviços', 'income'], ['Produtos', 'income'], ['Outros', 'income'],
                ['Aluguel', 'expense'], ['Salários', 'expense'], ['Materiais', 'expense'],
                ['Comissões', 'expense'], ['Outros', 'expense'],
            ];
            $stmtCat = $db->prepare(
                "INSERT INTO financial_categories (tenant_id, name, type, is_system, created_at) VALUES (?, ?, ?, 1, NOW())"
            );
            foreach ($defaultCategories as $cat) {
                $stmtCat->execute([$tenantId, $cat[0], $cat[1]]);
            }

            $db->commit();

            return [
                'tenant_id' => $tenantId,
                'user_id'   => $userId,
                'slug'      => $slug,
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function logout(): void
    {
        $userId   = $_SESSION['user_id'] ?? null;
        $tenantId = $_SESSION['tenant_id'] ?? null;

        $this->logAccess($userId, $tenantId, 'logout');
        TenantContext::clear();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    public function hashPassword(string $password): string
    {
        $config = config('auth.password');
        return password_hash($password, $config['algo'] ?? PASSWORD_ARGON2ID, [
            'memory_cost' => $config['memory_cost'] ?? 65536,
            'time_cost'   => $config['time_cost'] ?? 4,
            'threads'     => $config['threads'] ?? 3,
        ]);
    }

    /**
     * Gera e salva token de reset de senha.
     */
    public function createPasswordResetToken(string $email): ?string
    {
        $user = $this->userModel->findByEmail($email);
        if (!$user) return null;

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?"
        );
        $stmt->execute([hash('sha256', $token), $expires, $user['id']]);

        return $token;
    }

    /**
     * Reseta senha usando token válido.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $hashedToken = hash('sha256', $token);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$hashedToken]);
        $user = $stmt->fetch();

        if (!$user) return false;

        $stmt = $db->prepare(
            "UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires_at = NULL, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$this->hashPassword($newPassword), $user['id']]);

        $this->logAccess($user['id'], null, 'password_changed');

        return true;
    }

    private function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    private function logAccess(?int $userId, ?int $tenantId, string $action, array $metadata = []): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO access_logs (user_id, tenant_id, action, ip_address, user_agent, metadata, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $userId, $tenantId, $action,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                !empty($metadata) ? json_encode($metadata) : null,
            ]);
        } catch (\Exception $e) {
            error_log('Failed to log access: ' . $e->getMessage());
        }
    }
}
