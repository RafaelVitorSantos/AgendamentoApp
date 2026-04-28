<?php

namespace Tests\Integration;

use App\Services\AppointmentService;
use Tests\DatabaseTestCase;

/**
 * S2-08 — Testes de slots disponíveis.
 * Garante que os horários exibidos ao cliente são corretos.
 * RISCO: mostrar horário ocupado ou ocultar horário livre causa agendamentos errados.
 */
class AvailableSlotsTest extends DatabaseTestCase
{
    private array              $tenant;
    private array              $unit;
    private array              $professional;
    private array              $service;
    private AppointmentService $svc;
    private string             $nextMonday;
    private int                $mondayDow; // day_of_week da segunda

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant       = $this->createTenant();
        $this->unit         = $this->createUnit($this->tenant['id']);
        $this->professional = $this->createProfessional($this->tenant['id']);
        $this->service      = $this->createService($this->tenant['id'], ['duration_minutes' => 60]);

        $this->loginAsAdmin(1, $this->tenant['id']);
        $this->svc = new AppointmentService();

        // Usa próxima segunda-feira para evitar conflitos com data atual
        $this->nextMonday = date('Y-m-d', strtotime('next monday'));
        $this->mondayDow  = (int) date('w', strtotime($this->nextMonday)); // 1
    }

    private function addWorkingHours(string $start = '08:00:00', string $end = '18:00:00'): void
    {
        $this->createWorkingHours(
            $this->tenant['id'],
            $this->professional['id'],
            $this->unit['id'],
            $this->mondayDow,
            $start,
            $end
        );
    }

    // --------------------------------------------------
    // Slots básicos
    // --------------------------------------------------

    public function test_retorna_slots_quando_ha_horario_configurado(): void
    {
        $this->addWorkingHours('09:00:00', '11:00:00');

        $slots = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            60
        );

        $this->assertNotEmpty($slots, 'Deve retornar slots quando há horário configurado');
    }

    public function test_slots_dentro_do_horario_de_trabalho(): void
    {
        $this->addWorkingHours('09:00:00', '12:00:00');

        $slots = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            60
        );

        foreach ($slots as $slot) {
            $start = $slot['start'];
            $this->assertGreaterThanOrEqual('09:00', $start, "Slot {$start} anterior ao início do expediente");
            // Último slot que cabe é 11:00 (60min de duração, fim 12:00)
            $this->assertLessThanOrEqual('11:00', $start, "Slot {$start} não cabe dentro do expediente");
        }
    }

    public function test_nao_retorna_slots_sem_horario_configurado_e_sem_fallback(): void
    {
        // Profissional tem horários em outros dias, mas não na segunda
        $this->createWorkingHours(
            $this->tenant['id'],
            $this->professional['id'],
            $this->unit['id'],
            3, // quarta
            '08:00:00',
            '18:00:00'
        );

        $slots = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday, // segunda — dia sem horário
            60
        );

        $this->assertEmpty($slots, 'Não deve retornar slots para dia sem expediente configurado');
    }

    // --------------------------------------------------
    // Slots ocupados por agendamentos
    // --------------------------------------------------

    public function test_remove_slot_ocupado_por_agendamento(): void
    {
        $this->addWorkingHours('08:00:00', '12:00:00');

        // Cria agendamento 10:00–11:00
        $this->createAppointment($this->tenant['id'], [
            'unit_id'          => $this->unit['id'],
            'professional_id'  => $this->professional['id'],
            'service_id'       => $this->service['id'],
            'date'             => $this->nextMonday,
            'start_time'       => '10:00:00',
            'end_time'         => '11:00:00',
            'status'           => 'confirmed',
        ]);

        $slots = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            60
        );

        $starts = array_column($slots, 'start');
        $this->assertNotContains('10:00', $starts, 'Slot 10:00 deve estar bloqueado');
        // Slots adjacentes devem estar disponíveis
        $this->assertContains('08:00', $starts);
        $this->assertContains('11:00', $starts);
    }

    public function test_agendamento_cancelado_libera_slot(): void
    {
        $this->addWorkingHours('08:00:00', '12:00:00');

        $appt = $this->createAppointment($this->tenant['id'], [
            'unit_id'          => $this->unit['id'],
            'professional_id'  => $this->professional['id'],
            'service_id'       => $this->service['id'],
            'date'             => $this->nextMonday,
            'start_time'       => '10:00:00',
            'end_time'         => '11:00:00',
            'status'           => 'cancelled_by_client',
        ]);

        $slots  = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            60
        );

        $starts = array_column($slots, 'start');
        $this->assertContains('10:00', $starts, 'Slot de agendamento cancelado deve estar livre');
    }

    // --------------------------------------------------
    // Intervalos / breaks
    // --------------------------------------------------

    public function test_intervalo_bloqueia_slots(): void
    {
        $this->addWorkingHours('08:00:00', '18:00:00');

        // Adiciona intervalo de almoço 12:00–13:00
        $this->db->prepare(
            "INSERT INTO professional_breaks (tenant_id, professional_id, day_of_week, start_time, end_time)
             VALUES (?, ?, ?, '12:00:00', '13:00:00')"
        )->execute([$this->tenant['id'], $this->professional['id'], $this->mondayDow]);

        $slots  = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            60
        );
        $starts = array_column($slots, 'start');

        $this->assertNotContains('12:00', $starts, 'Slot de intervalo deve estar bloqueado');
        $this->assertContains('13:00', $starts, 'Slot após intervalo deve estar livre');
    }

    // --------------------------------------------------
    // Bloqueios de agenda
    // --------------------------------------------------

    public function test_bloqueio_de_agenda_remove_slots(): void
    {
        $this->addWorkingHours('08:00:00', '18:00:00');

        // Bloqueia o profissional das 09:00 às 11:00 na próxima segunda
        $this->db->prepare(
            "INSERT INTO schedule_blocks (tenant_id, professional_id, title, start_datetime, end_datetime, created_at, updated_at)
             VALUES (?, ?, 'Treinamento', ?, ?, NOW(), NOW())"
        )->execute([
            $this->tenant['id'],
            $this->professional['id'],
            $this->nextMonday . ' 09:00:00',
            $this->nextMonday . ' 11:00:00',
        ]);

        $slots  = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            60
        );
        $starts = array_column($slots, 'start');

        $this->assertNotContains('09:00', $starts, 'Slot bloqueado não deve aparecer');
        $this->assertNotContains('09:15', $starts);
        $this->assertNotContains('09:30', $starts);
        $this->assertNotContains('09:45', $starts);
        $this->assertNotContains('10:00', $starts);
        $this->assertContains('11:00', $starts, 'Slot após bloqueio deve estar livre');
    }

    // --------------------------------------------------
    // Duração de serviço
    // --------------------------------------------------

    public function test_servico_longo_reduz_numero_de_slots(): void
    {
        $this->addWorkingHours('08:00:00', '10:00:00'); // apenas 2h

        $slots30  = $this->svc->getAvailableSlots(
            $this->professional['id'], $this->unit['id'], $this->nextMonday, 30
        );
        $slots60  = $this->svc->getAvailableSlots(
            $this->professional['id'], $this->unit['id'], $this->nextMonday, 60
        );
        $slots120 = $this->svc->getAvailableSlots(
            $this->professional['id'], $this->unit['id'], $this->nextMonday, 120
        );

        $this->assertGreaterThan(count($slots60),  count($slots30),  'Serviço de 30min deve ter mais slots que 60min');
        $this->assertGreaterThan(count($slots120), count($slots60),  'Serviço de 60min deve ter mais slots que 120min');
    }

    public function test_servico_maior_que_expediente_retorna_zero_slots(): void
    {
        $this->addWorkingHours('09:00:00', '10:00:00'); // apenas 1h

        $slots = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            120 // 2h de duração, maior que o expediente
        );

        $this->assertEmpty($slots, 'Serviço maior que expediente não deve ter slots');
    }

    // --------------------------------------------------
    // Formato dos slots
    // --------------------------------------------------

    public function test_slots_tem_formato_correto(): void
    {
        $this->addWorkingHours('09:00:00', '11:00:00');

        $slots = $this->svc->getAvailableSlots(
            $this->professional['id'],
            $this->unit['id'],
            $this->nextMonday,
            60
        );

        $this->assertNotEmpty($slots);
        foreach ($slots as $slot) {
            $this->assertArrayHasKey('start', $slot);
            $this->assertArrayHasKey('end', $slot);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $slot['start']);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $slot['end']);
        }
    }
}
