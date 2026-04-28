<?php

namespace Tests;

use App\Core\TenantContext;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Classe base para todos os testes.
 * Gerencia sessão simulada e contexto de tenant.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        TenantContext::clear();
        parent::tearDown();
    }

    // --------------------------------------------------
    // Helpers de sessão
    // --------------------------------------------------

    protected function loginAs(int $userId, int $tenantId, string $role, array $permissions = []): void
    {
        $_SESSION['user_id']        = $userId;
        $_SESSION['tenant_id']      = $tenantId;
        $_SESSION['role_name']      = $role;
        $_SESSION['permissions']    = $permissions;
        $_SESSION['_login_time']    = time();
        $_SESSION['_last_activity'] = time();
        $_SESSION['_fingerprint']   = hash('sha256', '127.0.0.1PHPUnit');
        TenantContext::set($tenantId);
    }

    protected function loginAsAdmin(int $userId, int $tenantId): void
    {
        $this->loginAs($userId, $tenantId, 'tenant_admin', []);
    }

    protected function logout(): void
    {
        $_SESSION = [];
        TenantContext::clear();
    }

    // --------------------------------------------------
    // Helpers de asserção
    // --------------------------------------------------

    protected function assertSessionHas(string $key, mixed $expected = null): void
    {
        $this->assertArrayHasKey($key, $_SESSION, "Session não contém a chave '{$key}'");
        if ($expected !== null) {
            $this->assertEquals($expected, $_SESSION[$key]);
        }
    }

    protected function assertSessionMissing(string $key): void
    {
        $this->assertArrayNotHasKey($key, $_SESSION, "Session não deveria conter '{$key}'");
    }
}
