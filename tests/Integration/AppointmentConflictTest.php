<?php

namespace Tests\Integration;

use App\Core\TenantContext;
use App\Services\AppointmentService;
use Tests\DatabaseTestCase;

/**
 * S2-03 — Testes de conflito de agendamento.
 * Garante que double booking é impossível.
 * RISCO: dois agendamentos no mesmo horário geram caos operacional.
 */
class AppointmentConflictTest extends DatabaseTestCase
{
    private array              $tenant;
    private array              $unit;
    private array              $professional;
    private array              $service;
    private AppointmentService $svc;
    private string             $tomorrow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant       = $this->createTenant();
        $this->unit         = $this->createUnit($this->tenant['id']);
        $this->professional = $this->createProfessional($this->tenant['id']);
        $this->service      = $this->createService($this->tenant['id'], ['duration_minutes' => 60]);
        $this->tomorrow     = date('Y-m-d', strtotime('+1 day'));

        $this->loginAsAdmin(1, $this->tenant['id']);
        $this->svc = new AppointmentService();
    }

    private function payload(string $start): array
    {
        return [
            'unit_id'          => $this->unit['id'],
            'professional_id'  => $this->professional['id'],
            'service_id'       => $this->service['id'],
            'date'             => $this->tomorrow,
            'start_time'       => $start,
            'duration_minutes' => 60,
            'price'            => 100.00,
            'source'           => 'manual',
            'created_by'       => null,
        ];
    }

    public function test_cria_agendamento_sem_conflito(): void
    {
        $result = $this->svc->create($this->payload('10:00:00'));

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('id', $result);
    }

    public function test_rejeita_agendamento_com_sobreposicao_exata(): void
    {
        $this->svc->create($this->payload('10:00:00'));
        $result = $this->svc->create($this->payload('10:00:00'));

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Conflito', $result['error']);
    }

    public function test_rejeita_sobreposicao_parcial_inicio(): void
    {
        // Existente: 10:00–11:00. Novo: 10:30–11:30 — sobreposição
        $this->svc->create($this->payload('10:00:00'));
        $result = $this->svc->create($this->payload('10:30:00'));

        $this->assertFalse($result['success']);
    }

    public function test_rejeita_sobreposicao_parcial_fim(): void
    {
        // Existente: 10:00–11:00. Novo: 09:30–10:30 — sobreposição
        $this->svc->create($this->payload('10:00:00'));
        $result = $this->svc->create($this->payload('09:30:00'));

        $this->assertFalse($result['success']);
    }

    public function test_rejeita_agendamento_contido_no_existente(): void
    {
        // Existente: 10:00–11:00. Novo: 10:15–10:45 — contido
        $this->svc->create($this->payload('10:00:00'));

        $payload = $this->payload('10:15:00');
        $payload['duration_minutes'] = 30;
        $result = $this->svc->create($payload);

        $this->assertFalse($result['success']);
    }

    public function test_permite_agendamento_imediatamente_apos_outro(): void
    {
        // 10:00–11:00 e depois 11:00–12:00 — sem conflito (fim = início do próximo)
        $this->svc->create($this->payload('10:00:00'));
        $result = $this->svc->create($this->payload('11:00:00'));

        $this->assertTrue($result['success'], 'Agendamentos consecutivos devem ser permitidos');
    }

    public function test_permite_agendamento_imediatamente_antes(): void
    {
        // 11:00–12:00 e antes 10:00–11:00 — sem conflito
        $this->svc->create($this->payload('11:00:00'));
        $result = $this->svc->create($this->payload('10:00:00'));

        $this->assertTrue($result['success']);
    }

    public function test_cancelado_nao_causa_conflito(): void
    {
        // Agendamento cancelado não deve bloquear o horário
        $first  = $this->svc->create($this->payload('10:00:00'));
        $this->svc->cancel($first['id'], 'business');

        $result = $this->svc->create($this->payload('10:00:00'));

        $this->assertTrue($result['success'], 'Horário de cancelado deve estar livre');
    }

    public function test_no_show_nao_causa_conflito(): void
    {
        $first = $this->svc->create($this->payload('10:00:00'));
        $this->svc->changeStatus($first['id'], 'confirmed');
        $this->svc->changeStatus($first['id'], 'no_show');

        // No-show não deveria bloquear o horário
        // (status no_show é excluído da query de conflito)
        // Obs: changeStatus para no_show a partir de confirmed é válido
        $result = $this->svc->create($this->payload('10:00:00'));

        $this->assertTrue($result['success']);
    }

    public function test_profissionais_diferentes_nao_conflitam(): void
    {
        $prof2 = $this->createProfessional($this->tenant['id']);

        $this->svc->create($this->payload('10:00:00'));

        $payload2                    = $this->payload('10:00:00');
        $payload2['professional_id'] = $prof2['id'];
        $result                      = $this->svc->create($payload2);

        $this->assertTrue($result['success'], 'Profissionais diferentes não conflitam no mesmo horário');
    }

    public function test_multiplos_agendamentos_sequenciais_sem_conflito(): void
    {
        $horarios = ['09:00:00', '10:00:00', '11:00:00', '13:00:00', '14:00:00'];

        foreach ($horarios as $horario) {
            $result = $this->svc->create($this->payload($horario));
            $this->assertTrue(
                $result['success'],
                "Agendamento das {$horario} falhou inesperadamente"
            );
        }
    }
}
