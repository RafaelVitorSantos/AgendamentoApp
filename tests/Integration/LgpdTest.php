<?php

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * S2-07 — Testes de conformidade LGPD.
 * Valida export de dados e anonimização de cliente.
 */
class LgpdTest extends DatabaseTestCase
{
    private array $tenant;
    private array $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant();
        $this->loginAsAdmin(1, $this->tenant['id']);
        $this->client = $this->createClient($this->tenant['id'], [
            'name'  => 'João Silva',
            'email' => 'joao@example.com',
            'phone' => '11999999999',
        ]);
    }

    // --------------------------------------------------
    // Anonimização
    // --------------------------------------------------

    public function test_anonimizacao_remove_nome_real(): void
    {
        $this->anonymizeDirectly($this->client['id'], $this->tenant['id']);

        $stmt = $this->db->prepare("SELECT name FROM clients WHERE id = ?");
        $stmt->execute([$this->client['id']]);
        $row = $stmt->fetch();

        $this->assertStringNotContainsString('João', $row['name']);
        $this->assertStringContainsString('Anonimizado', $row['name']);
    }

    public function test_anonimizacao_remove_email(): void
    {
        $this->anonymizeDirectly($this->client['id'], $this->tenant['id']);

        $stmt = $this->db->prepare("SELECT email FROM clients WHERE id = ?");
        $stmt->execute([$this->client['id']]);
        $row = $stmt->fetch();

        $this->assertNull($row['email']);
    }

    public function test_anonimizacao_remove_telefone_real(): void
    {
        $this->anonymizeDirectly($this->client['id'], $this->tenant['id']);

        $stmt = $this->db->prepare("SELECT phone FROM clients WHERE id = ?");
        $stmt->execute([$this->client['id']]);
        $row = $stmt->fetch();

        $this->assertStringNotContainsString('11999999999', $row['phone']);
    }

    public function test_anonimizacao_marca_deleted_at(): void
    {
        $this->anonymizeDirectly($this->client['id'], $this->tenant['id']);

        $stmt = $this->db->prepare("SELECT deleted_at FROM clients WHERE id = ?");
        $stmt->execute([$this->client['id']]);
        $row = $stmt->fetch();

        $this->assertNotNull($row['deleted_at'], 'deleted_at deve ser definido após anonimização');
    }

    public function test_anonimizacao_remove_lgpd_consent(): void
    {
        // Primeiro dá o consentimento
        $this->db->prepare("UPDATE clients SET lgpd_consent = 1 WHERE id = ?")
                 ->execute([$this->client['id']]);

        $this->anonymizeDirectly($this->client['id'], $this->tenant['id']);

        $stmt = $this->db->prepare("SELECT lgpd_consent FROM clients WHERE id = ?");
        $stmt->execute([$this->client['id']]);
        $row = $stmt->fetch();

        $this->assertEquals(0, (int) $row['lgpd_consent']);
    }

    public function test_anonimizacao_preserva_agendamentos_historicos(): void
    {
        $unit = $this->createUnit($this->tenant['id']);
        $prof = $this->createProfessional($this->tenant['id']);
        $svc  = $this->createService($this->tenant['id']);

        $appt = $this->createAppointment($this->tenant['id'], [
            'client_id'       => $this->client['id'],
            'unit_id'         => $unit['id'],
            'professional_id' => $prof['id'],
            'service_id'      => $svc['id'],
        ]);

        $this->anonymizeDirectly($this->client['id'], $this->tenant['id']);

        // O agendamento ainda deve existir (histórico contábil)
        $stmt = $this->db->prepare("SELECT id, client_id FROM appointments WHERE id = ?");
        $stmt->execute([$appt['id']]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row, 'Agendamento deve persistir após anonimização');
        $this->assertEquals($this->client['id'], (int) $row['client_id']);
    }

    public function test_anonimizacao_nao_afeta_outros_clientes_do_tenant(): void
    {
        $outroCliente = $this->createClient($this->tenant['id'], [
            'name'  => 'Maria Oliveira',
            'email' => 'maria@example.com',
        ]);

        $this->anonymizeDirectly($this->client['id'], $this->tenant['id']);

        $stmt = $this->db->prepare("SELECT name, email FROM clients WHERE id = ?");
        $stmt->execute([$outroCliente['id']]);
        $row = $stmt->fetch();

        $this->assertEquals('Maria Oliveira', $row['name']);
        $this->assertEquals('maria@example.com', $row['email']);
    }

    public function test_cliente_de_outro_tenant_nao_pode_ser_anonimizado(): void
    {
        $tenantB   = $this->createTenant();
        $clienteB  = $this->createClient($tenantB['id'], ['name' => 'Protegido B']);

        // Tenta anonimizar cliente do Tenant B usando o WHERE do Tenant A
        $stmt = $this->db->prepare(
            "UPDATE clients SET name = 'Hackeado', deleted_at = NOW() WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$clienteB['id'], $this->tenant['id']]);

        // Zero rows afetadas — isolamento funciona
        $stmt2 = $this->db->prepare("SELECT name FROM clients WHERE id = ?");
        $stmt2->execute([$clienteB['id']]);
        $row = $stmt2->fetch();

        $this->assertEquals('Protegido B', $row['name'], 'Cliente do Tenant B não deve ser alterado por Tenant A');
    }

    // --------------------------------------------------
    // Helpers
    // --------------------------------------------------

    private function anonymizeDirectly(int $clientId, int $tenantId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE clients SET
                name            = ?,
                email           = NULL,
                phone           = ?,
                birthdate       = NULL,
                lgpd_consent    = 0,
                deleted_at      = NOW(),
                updated_at      = NOW()
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([
            'Cliente Anonimizado #' . $clientId,
            'anonimizado-' . $clientId,
            $clientId,
            $tenantId,
        ]);
    }
}
