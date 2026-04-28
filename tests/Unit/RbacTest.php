<?php

namespace Tests\Unit;

use App\Core\Controller;
use Tests\TestCase;

/**
 * S2-05 — Testes de RBAC e sistema de permissões.
 * Verifica que can() e authorize() se comportam corretamente por role.
 * Puro — sem banco de dados.
 */
class RbacTest extends TestCase
{
    // Proxy para testar o método protegido can() do Controller
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria proxy anônimo que expõe can() publicamente
        $this->controller = new class extends Controller {
            public function canPublic(string $permission): bool
            {
                return $this->can($permission);
            }
        };
    }

    public function test_tenant_admin_tem_todas_as_permissoes(): void
    {
        $this->loginAs(1, 1, 'tenant_admin', []);

        $this->assertTrue($this->controller->canPublic('appointments.view'));
        $this->assertTrue($this->controller->canPublic('clients.delete'));
        $this->assertTrue($this->controller->canPublic('financial.view'));
        $this->assertTrue($this->controller->canPublic('qualquer.permissao.inventada'));
    }

    public function test_usuario_sem_permissao_nao_tem_acesso(): void
    {
        $this->loginAs(1, 1, 'professional', ['appointments.view']);

        $this->assertFalse($this->controller->canPublic('clients.delete'));
        $this->assertFalse($this->controller->canPublic('financial.view'));
        $this->assertFalse($this->controller->canPublic('reports.view'));
    }

    public function test_usuario_com_permissao_especifica_tem_acesso(): void
    {
        $permissions = ['appointments.view', 'appointments.create', 'clients.view'];
        $this->loginAs(1, 1, 'receptionist', $permissions);

        $this->assertTrue($this->controller->canPublic('appointments.view'));
        $this->assertTrue($this->controller->canPublic('appointments.create'));
        $this->assertTrue($this->controller->canPublic('clients.view'));
    }

    public function test_usuario_sem_sessao_nao_tem_permissoes(): void
    {
        // Sem loginAs — $_SESSION vazio
        $this->assertFalse($this->controller->canPublic('appointments.view'));
        $this->assertFalse($this->controller->canPublic('admin.access'));
    }

    public function test_super_admin_role_nao_bypassa_como_tenant_admin(): void
    {
        // super_admin não deve ter bypass automático como tenant_admin
        $this->loginAs(1, 1, 'super_admin', []);

        // super_admin não é tenant_admin, então precisa de permissão explícita
        $this->assertFalse($this->controller->canPublic('appointments.view'));
    }

    public function test_permissoes_sao_case_sensitive(): void
    {
        $this->loginAs(1, 1, 'professional', ['appointments.view']);

        $this->assertTrue($this->controller->canPublic('appointments.view'));
        $this->assertFalse($this->controller->canPublic('Appointments.View'));
        $this->assertFalse($this->controller->canPublic('APPOINTMENTS.VIEW'));
    }

    public function test_lista_vazia_de_permissoes_nega_tudo(): void
    {
        $this->loginAs(1, 1, 'manager', []);

        $this->assertFalse($this->controller->canPublic('appointments.view'));
        $this->assertFalse($this->controller->canPublic('clients.view'));
    }

    public function test_sessao_com_todas_as_permissoes_comuns(): void
    {
        $permissions = [
            'appointments.view', 'appointments.create', 'appointments.cancel',
            'clients.view', 'clients.create', 'clients.edit',
            'services.view', 'professionals.view',
            'financial.view', 'reports.view',
        ];
        $this->loginAs(1, 1, 'manager', $permissions);

        foreach ($permissions as $perm) {
            $this->assertTrue(
                $this->controller->canPublic($perm),
                "Falhou para permissão: {$perm}"
            );
        }
    }
}
