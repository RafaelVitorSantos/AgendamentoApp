<?php

namespace Tests\Integration;

use App\Services\PlanLimiter;
use Tests\DatabaseTestCase;

/**
 * S2-06 — Testes de limites de plano.
 * Garante que tenants não ultrapassam os limites do plano contratado.
 */
class PlanLimiterTest extends DatabaseTestCase
{
    private array $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant();
        $this->loginAsAdmin(1, $this->tenant['id']);
    }

    private function makeLimiter(): PlanLimiter
    {
        return new PlanLimiter();
    }

    // --------------------------------------------------
    // Profissionais
    // --------------------------------------------------

    public function test_pode_criar_profissional_dentro_do_limite(): void
    {
        $plan = $this->createPlan(['max_professionals' => 3, 'slug' => 'basic_' . uniqid()]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        $limiter = $this->makeLimiter();
        $this->assertTrue($limiter->canCreateProfessional());
    }

    public function test_nao_pode_criar_profissional_acima_do_limite(): void
    {
        $plan = $this->createPlan(['max_professionals' => 1, 'slug' => 'small_' . uniqid()]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        // Cria 1 profissional — atinge o limite
        $this->createProfessional($this->tenant['id']);

        $limiter = $this->makeLimiter();
        $this->assertFalse($limiter->canCreateProfessional());
    }

    public function test_limite_negativo_1_significa_ilimitado(): void
    {
        $plan = $this->createPlan(['max_professionals' => -1, 'slug' => 'pro_' . uniqid()]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        // Cria muitos profissionais
        for ($i = 0; $i < 10; $i++) {
            $this->createProfessional($this->tenant['id']);
        }

        $limiter = $this->makeLimiter();
        $this->assertTrue($limiter->canCreateProfessional(), 'Plano ilimitado deve sempre permitir');
    }

    // --------------------------------------------------
    // Agendamentos mensais
    // --------------------------------------------------

    public function test_pode_criar_agendamento_dentro_do_limite_mensal(): void
    {
        $plan = $this->createPlan(['max_appointments_month' => 10, 'slug' => 'monthly_' . uniqid()]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        $limiter = $this->makeLimiter();
        $this->assertTrue($limiter->canCreateAppointment());
    }

    public function test_nao_pode_criar_agendamento_acima_do_limite_mensal(): void
    {
        $plan = $this->createPlan(['max_appointments_month' => 2, 'slug' => 'mini_' . uniqid()]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        $unit = $this->createUnit($this->tenant['id']);
        $prof = $this->createProfessional($this->tenant['id']);
        $svc  = $this->createService($this->tenant['id']);

        // Cria 2 agendamentos neste mês — atinge o limite
        for ($i = 0; $i < 2; $i++) {
            $this->createAppointment($this->tenant['id'], [
                'unit_id'         => $unit['id'],
                'professional_id' => $prof['id'],
                'service_id'      => $svc['id'],
                'date'            => date('Y-m-') . str_pad((string)(10 + $i), 2, '0', STR_PAD_LEFT),
                'start_time'      => '10:00:00',
                'end_time'        => '11:00:00',
                'status'          => 'scheduled',
            ]);
        }

        $limiter = $this->makeLimiter();
        $this->assertFalse($limiter->canCreateAppointment());
    }

    // --------------------------------------------------
    // Clientes
    // --------------------------------------------------

    public function test_pode_criar_cliente_dentro_do_limite(): void
    {
        $plan = $this->createPlan(['max_clients' => 5, 'slug' => 'clients_' . uniqid()]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        $limiter = $this->makeLimiter();
        $this->assertTrue($limiter->canCreateClient());
    }

    public function test_nao_pode_criar_cliente_acima_do_limite(): void
    {
        $plan = $this->createPlan(['max_clients' => 1, 'slug' => 'oneclient_' . uniqid()]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        $this->createClient($this->tenant['id']);

        $limiter = $this->makeLimiter();
        $this->assertFalse($limiter->canCreateClient());
    }

    // --------------------------------------------------
    // Features
    // --------------------------------------------------

    public function test_hasFeature_retorna_true_quando_plano_tem_feature(): void
    {
        $plan = $this->createPlan([
            'slug'         => 'full_' . uniqid(),
            'has_reports'  => 1,
            'has_whatsapp' => 1,
        ]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        $limiter = $this->makeLimiter();
        $this->assertTrue($limiter->hasFeature('reports'));
        $this->assertTrue($limiter->hasFeature('whatsapp'));
    }

    public function test_hasFeature_retorna_false_quando_plano_nao_tem_feature(): void
    {
        $plan = $this->createPlan([
            'slug'        => 'basic2_' . uniqid(),
            'has_reports' => 0,
        ]);
        $this->createSubscription($this->tenant['id'], $plan['id']);

        $limiter = $this->makeLimiter();
        $this->assertFalse($limiter->hasFeature('reports'));
    }

    // --------------------------------------------------
    // Isolamento entre tenants
    // --------------------------------------------------

    public function test_limites_sao_independentes_por_tenant(): void
    {
        $tenantB = $this->createTenant();

        $planA = $this->createPlan(['max_professionals' => 1, 'slug' => 'planA_' . uniqid()]);
        $planB = $this->createPlan(['max_professionals' => 5, 'slug' => 'planB_' . uniqid()]);

        $this->createSubscription($this->tenant['id'], $planA['id']);
        $this->createSubscription($tenantB['id'],      $planB['id']);

        // Esgota o limite do tenant A
        $this->createProfessional($this->tenant['id']);
        $this->loginAsAdmin(1, $this->tenant['id']);
        $this->assertFalse($this->makeLimiter()->canCreateProfessional());

        // Tenant B ainda tem espaço
        $this->loginAsAdmin(2, $tenantB['id']);
        $this->assertTrue($this->makeLimiter()->canCreateProfessional());
    }
}
