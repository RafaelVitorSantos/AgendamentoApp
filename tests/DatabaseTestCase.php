<?php

namespace Tests;

use App\Core\Database;
use App\Core\TenantContext;

/**
 * Base para testes que precisam de banco de dados.
 * Envolve cada teste em uma transação que é revertida no tearDown,
 * garantindo banco limpo sem depender de fixtures externas.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected ?\PDO $db = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->db = Database::getInstance();
            $this->db->beginTransaction();
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Banco de dados de teste indisponível. ' .
                'Crie o banco: CREATE DATABASE agendapro_test; ' .
                'e importe o schema: mysql -u root agendapro_test < database/schema.sql. ' .
                'Erro: ' . $e->getMessage()
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->db !== null && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
        parent::tearDown();
    }

    // --------------------------------------------------
    // Factories — criam registros mínimos válidos
    // --------------------------------------------------

    protected function createTenant(array $overrides = []): array
    {
        $slug = $overrides['slug'] ?? 'tenant-test-' . uniqid();
        $data = array_merge([
            'uuid'         => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
            'company_name' => 'Empresa Teste ' . uniqid(),
            'trade_name'   => 'Empresa Teste',
            'slug'         => $slug,
            'email'        => 'tenant-' . uniqid() . '@test.com',
            'status'       => 'active',
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO tenants (uuid, company_name, trade_name, slug, email, status, created_at, updated_at)
             VALUES (:uuid, :company_name, :trade_name, :slug, :email, :status, NOW(), NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createRole(string $name = 'professional'): int
    {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $role = $stmt->fetch();
        if ($role) return (int) $role['id'];

        $stmt = $this->db->prepare(
            "INSERT INTO roles (name, display_name, created_at) VALUES (?, ?, NOW())"
        );
        $stmt->execute([$name, ucfirst($name)]);
        return (int) $this->db->lastInsertId();
    }

    protected function createUser(int $tenantId, array $overrides = []): array
    {
        $roleId = $overrides['role_id'] ?? $this->createRole('professional');
        $data   = array_merge([
            'tenant_id'     => $tenantId,
            'role_id'       => $roleId,
            'name'          => 'Usuário Teste ' . uniqid(),
            'email'         => 'user-' . uniqid() . '@test.com',
            'password_hash' => password_hash('senha123', PASSWORD_BCRYPT),
            'is_active'     => 1,
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO users (tenant_id, role_id, name, email, password_hash, is_active, created_at, updated_at)
             VALUES (:tenant_id, :role_id, :name, :email, :password_hash, :is_active, NOW(), NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createUnit(int $tenantId, array $overrides = []): array
    {
        $data = array_merge([
            'tenant_id'  => $tenantId,
            'name'       => 'Unidade Teste',
            'slug'       => 'unidade-teste-' . uniqid(),
            'is_active'  => 1,
            'is_default' => 1,
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO units (tenant_id, name, slug, is_active, is_default, created_at, updated_at)
             VALUES (:tenant_id, :name, :slug, :is_active, :is_default, NOW(), NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createService(int $tenantId, array $overrides = []): array
    {
        $data = array_merge([
            'tenant_id'            => $tenantId,
            'name'                 => 'Serviço Teste',
            'duration_minutes'     => 60,
            'price'                => 100.00,
            'is_active'            => 1,
            'allow_online_booking' => 1,
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO services (tenant_id, name, duration_minutes, price, is_active, allow_online_booking, created_at, updated_at)
             VALUES (:tenant_id, :name, :duration_minutes, :price, :is_active, :allow_online_booking, NOW(), NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createProfessional(int $tenantId, array $overrides = []): array
    {
        $data = array_merge([
            'tenant_id' => $tenantId,
            'name'      => 'Profissional Teste ' . uniqid(),
            'is_active' => 1,
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO professionals (tenant_id, name, is_active, created_at, updated_at)
             VALUES (:tenant_id, :name, :is_active, NOW(), NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createAppointment(int $tenantId, array $overrides = []): array
    {
        $data = array_merge([
            'tenant_id'        => $tenantId,
            'unit_id'          => 1,
            'professional_id'  => 1,
            'service_id'       => 1,
            'client_id'        => null,
            'date'             => date('Y-m-d', strtotime('+1 day')),
            'start_time'       => '10:00:00',
            'end_time'         => '11:00:00',
            'duration_minutes' => 60,
            'price'            => 100.00,
            'status'           => 'scheduled',
            'source'           => 'manual',
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO appointments
                (tenant_id, unit_id, professional_id, service_id, client_id,
                 date, start_time, end_time, duration_minutes, price, status, source, created_at, updated_at)
             VALUES
                (:tenant_id, :unit_id, :professional_id, :service_id, :client_id,
                 :date, :start_time, :end_time, :duration_minutes, :price, :status, :source, NOW(), NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createClient(int $tenantId, array $overrides = []): array
    {
        $data = array_merge([
            'tenant_id' => $tenantId,
            'name'      => 'Cliente Teste ' . uniqid(),
            'phone'     => '11' . rand(900000000, 999999999),
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO clients (tenant_id, name, phone, created_at, updated_at)
             VALUES (:tenant_id, :name, :phone, NOW(), NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createPlan(array $overrides = []): array
    {
        $stmt = $this->db->prepare("SELECT * FROM plans WHERE slug = 'free' LIMIT 1");
        $stmt->execute();
        $plan = $stmt->fetch();
        if ($plan) return $plan;

        $data = array_merge([
            'name'                   => 'Free',
            'slug'                   => 'free',
            'max_professionals'      => 2,
            'max_appointments_month' => 50,
            'max_units'              => 1,
            'max_clients'            => 100,
            'price_monthly'          => 0,
        ], $overrides);

        $stmt = $this->db->prepare(
            "INSERT INTO plans (name, slug, max_professionals, max_appointments_month, max_units, max_clients, price_monthly, created_at)
             VALUES (:name, :slug, :max_professionals, :max_appointments_month, :max_units, :max_clients, :price_monthly, NOW())"
        );
        $stmt->execute($data);
        $data['id'] = (int) $this->db->lastInsertId();
        return $data;
    }

    protected function createSubscription(int $tenantId, int $planId, string $status = 'active'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO subscriptions (tenant_id, plan_id, status, billing_cycle, current_period_start, current_period_end, created_at, updated_at)
             VALUES (?, ?, ?, 'monthly', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW())"
        );
        $stmt->execute([$tenantId, $planId, $status]);
        return (int) $this->db->lastInsertId();
    }

    protected function createWorkingHours(int $tenantId, int $professionalId, int $unitId, int $dayOfWeek, string $start = '08:00:00', string $end = '18:00:00'): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO professional_working_hours (tenant_id, professional_id, unit_id, day_of_week, start_time, end_time, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([$tenantId, $professionalId, $unitId, $dayOfWeek, $start, $end]);
    }
}
