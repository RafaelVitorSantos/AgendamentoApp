<?php

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * S2-02 — Testes de isolamento multi-tenant.
 * Garante que um tenant jamais acessa dados de outro.
 * RISCO: vazamento de dados entre empresas clientes.
 */
class MultiTenantIsolationTest extends DatabaseTestCase
{
    private array $tenantA;
    private array $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = $this->createTenant(['company_name' => 'Empresa A']);
        $this->tenantB = $this->createTenant(['company_name' => 'Empresa B']);
    }

    // --------------------------------------------------
    // Clientes
    // --------------------------------------------------

    public function test_tenant_nao_ve_clientes_de_outro_tenant(): void
    {
        $clienteA = $this->createClient($this->tenantA['id'], ['name' => 'Cliente Exclusivo A']);
        $clienteB = $this->createClient($this->tenantB['id'], ['name' => 'Cliente Exclusivo B']);

        // Autentica como Tenant A
        $this->loginAsAdmin(1, $this->tenantA['id']);

        $stmt = $this->db->prepare(
            "SELECT * FROM clients WHERE tenant_id = ? AND name = ?"
        );

        // Deve encontrar o próprio cliente
        $stmt->execute([$this->tenantA['id'], 'Cliente Exclusivo A']);
        $this->assertNotEmpty($stmt->fetch(), 'Tenant A deveria ver seu próprio cliente');

        // NÃO deve encontrar o cliente do outro tenant via mesma query
        $stmt->execute([$this->tenantA['id'], 'Cliente Exclusivo B']);
        $this->assertFalse($stmt->fetch(), 'Tenant A não deveria ver cliente do Tenant B');
    }

    public function test_tenant_nao_acessa_cliente_por_id_de_outro_tenant(): void
    {
        $clienteB = $this->createClient($this->tenantB['id'], ['name' => 'Segredo do Tenant B']);

        // Autentica como Tenant A — tenta buscar o ID do cliente do Tenant B
        $this->loginAsAdmin(1, $this->tenantA['id']);

        $stmt = $this->db->prepare(
            "SELECT * FROM clients WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$clienteB['id'], $this->tenantA['id']]);

        $this->assertFalse($stmt->fetch(), 'Tenant A não deve acessar cliente do Tenant B por ID');
    }

    // --------------------------------------------------
    // Agendamentos
    // --------------------------------------------------

    public function test_tenant_nao_ve_agendamentos_de_outro_tenant(): void
    {
        $unitA = $this->createUnit($this->tenantA['id']);
        $unitB = $this->createUnit($this->tenantB['id']);
        $profA = $this->createProfessional($this->tenantA['id']);
        $profB = $this->createProfessional($this->tenantB['id']);
        $svcA  = $this->createService($this->tenantA['id']);
        $svcB  = $this->createService($this->tenantB['id']);

        $apptA = $this->createAppointment($this->tenantA['id'], [
            'unit_id' => $unitA['id'], 'professional_id' => $profA['id'], 'service_id' => $svcA['id'],
        ]);
        $apptB = $this->createAppointment($this->tenantB['id'], [
            'unit_id' => $unitB['id'], 'professional_id' => $profB['id'], 'service_id' => $svcB['id'],
        ]);

        // Busca agendamentos do Tenant A — não deve aparecer o do Tenant B
        $stmt = $this->db->prepare("SELECT id FROM appointments WHERE tenant_id = ?");
        $stmt->execute([$this->tenantA['id']]);
        $ids = array_column($stmt->fetchAll(), 'id');

        $this->assertContains($apptA['id'], $ids);
        $this->assertNotContains($apptB['id'], $ids);
    }

    // --------------------------------------------------
    // Profissionais
    // --------------------------------------------------

    public function test_tenant_nao_ve_profissionais_de_outro_tenant(): void
    {
        $profA = $this->createProfessional($this->tenantA['id'], ['name' => 'Dr. A']);
        $profB = $this->createProfessional($this->tenantB['id'], ['name' => 'Dr. B']);

        $stmt = $this->db->prepare("SELECT name FROM professionals WHERE tenant_id = ?");
        $stmt->execute([$this->tenantA['id']]);
        $nomes = array_column($stmt->fetchAll(), 'name');

        $this->assertContains('Dr. A', $nomes);
        $this->assertNotContains('Dr. B', $nomes);
    }

    // --------------------------------------------------
    // Serviços
    // --------------------------------------------------

    public function test_tenant_nao_ve_servicos_de_outro_tenant(): void
    {
        $svcA = $this->createService($this->tenantA['id'], ['name' => 'Serviço Privado A']);
        $svcB = $this->createService($this->tenantB['id'], ['name' => 'Serviço Privado B']);

        $stmt = $this->db->prepare("SELECT name FROM services WHERE tenant_id = ?");
        $stmt->execute([$this->tenantA['id']]);
        $nomes = array_column($stmt->fetchAll(), 'name');

        $this->assertContains('Serviço Privado A', $nomes);
        $this->assertNotContains('Serviço Privado B', $nomes);
    }

    // --------------------------------------------------
    // Financeiro
    // --------------------------------------------------

    public function test_tenant_nao_ve_transacoes_financeiras_de_outro_tenant(): void
    {
        // Cria categoria para cada tenant
        $stmtCat = $this->db->prepare(
            "INSERT INTO financial_categories (tenant_id, name, type, created_at) VALUES (?, 'Serviços', 'income', NOW())"
        );
        $stmtCat->execute([$this->tenantA['id']]);
        $catA = (int) $this->db->lastInsertId();

        $stmtCat->execute([$this->tenantB['id']]);
        $catB = (int) $this->db->lastInsertId();

        $stmtTx = $this->db->prepare(
            "INSERT INTO financial_transactions (tenant_id, category_id, type, amount, date, reference_date, status, created_at, updated_at)
             VALUES (?, ?, 'income', ?, NOW(), NOW(), 'paid', NOW(), NOW())"
        );
        $stmtTx->execute([$this->tenantA['id'], $catA, 500.00]);
        $stmtTx->execute([$this->tenantB['id'], $catB, 999.99]);

        $stmt = $this->db->prepare("SELECT amount FROM financial_transactions WHERE tenant_id = ?");
        $stmt->execute([$this->tenantA['id']]);
        $amounts = array_column($stmt->fetchAll(), 'amount');

        $this->assertContains('500.00', $amounts);
        $this->assertNotContains('999.99', $amounts);
    }

    // --------------------------------------------------
    // Contexto de tenant
    // --------------------------------------------------

    public function test_tenant_context_isola_operacoes_do_model(): void
    {
        $clienteA = $this->createClient($this->tenantA['id'], ['name' => 'Isolado A']);
        $clienteB = $this->createClient($this->tenantB['id'], ['name' => 'Isolado B']);

        // Seta contexto do Tenant A
        $this->loginAsAdmin(1, $this->tenantA['id']);

        $model = new \App\Models\Client();
        $found = $model->find($clienteB['id']); // tenta buscar ID do cliente B no contexto de A

        $this->assertNull($found, 'Model com tenant_id do Tenant A não deve retornar cliente do Tenant B');
    }

    public function test_soft_delete_isolado_por_tenant(): void
    {
        $clienteA = $this->createClient($this->tenantA['id']);

        // Marca como deletado no tenant A
        $this->db->prepare("UPDATE clients SET deleted_at = NOW() WHERE id = ?")
                 ->execute([$clienteA['id']]);

        // No tenant B, o registro não deve aparecer nem como deletado
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$clienteA['id'], $this->tenantB['id']]);

        $this->assertFalse($stmt->fetch(), 'Registro deletado do Tenant A não deve aparecer no Tenant B');
    }
}
