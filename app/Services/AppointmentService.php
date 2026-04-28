<?php

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use App\Models\Appointment;
use App\Services\JobService;
use App\Jobs\SendConfirmationEmailJob;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Jobs\SyncAppointmentToCalendarJob;

/**
 * Lógica de negócio de agendamentos.
 * Valida disponibilidade, conflitos e limites.
 */
class AppointmentService
{
    private Appointment $model;
    private PlanLimiter $limiter;
    private JobService  $jobSvc;

    public function __construct()
    {
        $this->model  = new Appointment();
        $this->limiter = new PlanLimiter();
        $this->jobSvc = new JobService();
    }

    /**
     * Cria um novo agendamento com todas as validações.
     */
    public function create(array $data): array
    {
        // Verifica limite do plano
        if (!$this->limiter->canCreateAppointment()) {
            return ['success' => false, 'error' => 'Limite de agendamentos do plano atingido.'];
        }

        // Garante início em H:i:s (ex.: 14:00 ou 14:00:00 → 14:00:00)
        $startTime = $data['start_time'];
        if (strlen($startTime) === 5) {
            $startTime .= ':00';
        }
        $endTime = date('H:i:s', strtotime($startTime) + ($data['duration_minutes'] * 60));

        // Verifica conflito (um que termina às 14:00 não conflita com um que começa às 14:00)
        if ($this->model->hasConflict(
            (int) $data['professional_id'],
            $data['date'],
            $startTime,
            $endTime
        )) {
            return ['success' => false, 'error' => 'Conflito de horário. Já existe agendamento neste período.'];
        }

        // Verifica se está dentro do horário de funcionamento
        if (!$this->isWithinWorkingHours($data['professional_id'], $data['unit_id'], $data['date'], $startTime, $endTime)) {
            return ['success' => false, 'error' => 'Horário fora do período de funcionamento.'];
        }

        // Verifica se não é feriado
        if ($this->isHoliday($data['date'], $data['unit_id'] ?? null)) {
            return ['success' => false, 'error' => 'A data selecionada é um feriado.'];
        }

        // Verifica bloqueio
        if ($this->isBlocked($data['professional_id'], $data['date'], $startTime, $endTime)) {
            return ['success' => false, 'error' => 'O profissional está com horário bloqueado neste período.'];
        }

        $appointmentData = [
            'tenant_id'        => TenantContext::require(),
            'unit_id'          => $data['unit_id'],
            'client_id'        => $data['client_id'] ?? null,
            'professional_id'  => $data['professional_id'],
            'service_id'       => $data['service_id'],
            'date'             => $data['date'],
            'start_time'       => $startTime,
            'end_time'         => $endTime,
            'duration_minutes' => $data['duration_minutes'],
            'price'            => $data['price'],
            'status'           => 'scheduled',
            'source'           => $data['source'] ?? 'manual',
            'notes'            => $data['notes'] ?? null,
            'created_by'       => $data['created_by'] ?? null,
        ];

        $id = $this->model->create($appointmentData);

        // Dispara e-mail, WhatsApp e sync de calendário de forma assíncrona
        try {
            $tenantId = TenantContext::require();
            $this->jobSvc->dispatch(new SendConfirmationEmailJob($id, $tenantId));
            $this->jobSvc->dispatch(new SendWhatsAppNotificationJob($id, $tenantId, 'confirmation'));
            $this->jobSvc->dispatch(new SyncAppointmentToCalendarJob($id, $tenantId, 'create'));
        } catch (\Throwable) {
            // Falha ao enfileirar não deve impedir o agendamento
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * Cancela um agendamento.
     */
    public function cancel(int $id, string $cancelledBy = 'business', ?string $reason = null): bool
    {
        $appointment = $this->model->find($id);
        if (!$appointment) return false;

        $status = $cancelledBy === 'client' ? 'cancelled_by_client' : 'cancelled_by_business';

        $updated = $this->model->update($id, [
            'status'        => $status,
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);

        if ($updated) {
            try {
                $tenantId = $appointment['tenant_id'] ?? TenantContext::require();
                $this->jobSvc->dispatch(new SyncAppointmentToCalendarJob($id, $tenantId, 'delete'));
            } catch (\Throwable) {}
        }

        return $updated;
    }

    /**
     * Remarca um agendamento para nova data/hora.
     */
    public function reschedule(int $id, string $newDate, string $newStartTime): array
    {
        $original = $this->model->find($id);
        if (!$original) {
            return ['success' => false, 'error' => 'Agendamento não encontrado.'];
        }

        $endTime = date('H:i:s', strtotime($newStartTime) + ($original['duration_minutes'] * 60));

        if ($this->model->hasConflict($original['professional_id'], $newDate, $newStartTime, $endTime, $id)) {
            return ['success' => false, 'error' => 'Conflito de horário na nova data.'];
        }

        // Marca original como remarcado
        $this->model->update($id, ['status' => 'rescheduled']);

        // Cria novo agendamento vinculado
        $newData = [
            'unit_id'          => $original['unit_id'],
            'client_id'        => $original['client_id'],
            'professional_id'  => $original['professional_id'],
            'service_id'       => $original['service_id'],
            'date'             => $newDate,
            'start_time'       => $newStartTime,
            'duration_minutes' => $original['duration_minutes'],
            'price'            => $original['price'],
            'source'           => $original['source'],
            'notes'            => $original['notes'],
        ];

        $result = $this->create($newData);

        if ($result['success']) {
            // Vincula ao original
            $this->model->update($result['id'], ['rescheduled_from_id' => $id]);
        }

        return $result;
    }

    /**
     * Muda status do agendamento.
     */
    public function changeStatus(int $id, string $status): bool
    {
        $validTransitions = [
            'scheduled'   => ['confirmed', 'cancelled_by_client', 'cancelled_by_business', 'rescheduled', 'no_show'],
            'confirmed'   => ['in_progress', 'cancelled_by_client', 'cancelled_by_business', 'rescheduled', 'no_show'],
            'in_progress' => ['completed'],
            'completed'   => [],
        ];

        $appointment = $this->model->find($id);
        if (!$appointment) return false;

        $currentStatus = $appointment['status'];
        if (!isset($validTransitions[$currentStatus]) || !in_array($status, $validTransitions[$currentStatus])) {
            return false;
        }

        $updateData = ['status' => $status];

        switch ($status) {
            case 'confirmed':
                $updateData['confirmed_at'] = now();
                break;
            case 'in_progress':
                $updateData['started_at'] = now();
                break;
            case 'completed':
                $updateData['completed_at'] = now();
                break;
        }

        $updated = $this->model->update($id, $updateData);

        if ($updated) {
            if ($status === 'completed') {
                $this->createCompletionTransaction($appointment);
            }
            try {
                $tenantId = $appointment['tenant_id'] ?? TenantContext::require();
                $action   = in_array($status, ['cancelled_by_client', 'cancelled_by_business', 'no_show'], true)
                    ? 'delete'
                    : 'update';
                $this->jobSvc->dispatch(new SyncAppointmentToCalendarJob($id, $tenantId, $action));
            } catch (\Throwable) {}
        }

        return $updated;
    }

    /**
     * Cria lançamento de recebimento ao concluir um atendimento.
     * Idempotente: não cria duplicata se já existir lançamento para o agendamento.
     */
    private function createCompletionTransaction(array $appointment): void
    {
        if (empty($appointment['price']) || (float) $appointment['price'] <= 0) {
            return;
        }

        $db = Database::getInstance();

        // Idempotência: verifica se já existe lançamento vinculado
        $exists = $db->prepare(
            "SELECT COUNT(*) FROM financial_transactions WHERE appointment_id = ? AND tenant_id = ?"
        );
        $exists->execute([$appointment['id'], $appointment['tenant_id']]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        // Busca detalhes do agendamento para a descrição
        $stmt = $db->prepare(
            "SELECT s.name AS service_name, c.name AS client_name
             FROM appointments a
             LEFT JOIN services s ON s.id = a.service_id
             LEFT JOIN clients  c ON c.id = a.client_id
             WHERE a.id = ?"
        );
        $stmt->execute([$appointment['id']]);
        $details = $stmt->fetch();

        $serviceName = $details['service_name'] ?? 'Serviço';
        $clientName  = $details['client_name']  ?? null;
        $description = $clientName
            ? "Atendimento: {$serviceName} — {$clientName}"
            : "Atendimento: {$serviceName}";

        // Busca ou cria categoria padrão de serviços para o tenant
        $categoryId = $this->getOrCreateServiceCategory($db, (int) $appointment['tenant_id']);

        $db->prepare(
            "INSERT INTO financial_transactions
                (tenant_id, unit_id, category_id, appointment_id, type, description,
                 amount, status, reference_date, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'income', ?, ?, 'pending', ?, ?, NOW(), NOW())"
        )->execute([
            $appointment['tenant_id'],
            $appointment['unit_id'] ?? null,
            $categoryId,
            $appointment['id'],
            $description,
            $appointment['price'],
            $appointment['date'],
            $_SESSION['user_id'] ?? null,
        ]);
    }

    /**
     * Retorna o id da categoria "Serviços" do tenant, criando-a se não existir.
     */
    private function getOrCreateServiceCategory(\PDO $db, int $tenantId): int
    {
        $stmt = $db->prepare(
            "SELECT id FROM financial_categories
             WHERE tenant_id = ? AND type = 'income' AND is_system = 1
             ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        // Cria categoria padrão caso o tenant não tenha nenhuma
        $db->prepare(
            "INSERT INTO financial_categories (tenant_id, name, type, color, is_system, created_at)
             VALUES (?, 'Serviços', 'income', '#10B981', 1, NOW())"
        )->execute([$tenantId]);

        return (int) $db->lastInsertId();
    }

    /**
     * Retorna slots disponíveis para uma data/profissional.
     */
    public function getAvailableSlots(int $professionalId, int $unitId, string $date, int $durationMinutes): array
    {
        $dayOfWeek = (int) date('w', strtotime($date));

        // Horários do profissional
        $db = Database::getInstance();
        $tenantId = TenantContext::require();

        // Busca horários: primeiro com unit_id, depois sem (fallback)
        if ($unitId > 0) {
            $stmt = $db->prepare(
                "SELECT start_time, end_time FROM professional_working_hours
                 WHERE tenant_id = ? AND professional_id = ? AND unit_id = ? AND day_of_week = ? AND is_active = 1"
            );
            $stmt->execute([$tenantId, $professionalId, $unitId, $dayOfWeek]);
        } else {
            $stmt = $db->prepare(
                "SELECT start_time, end_time FROM professional_working_hours
                 WHERE tenant_id = ? AND professional_id = ? AND day_of_week = ? AND is_active = 1"
            );
            $stmt->execute([$tenantId, $professionalId, $dayOfWeek]);
        }
        $workingHours = $stmt->fetchAll();

        // Se não achou para esta unidade, tenta sem filtro de unidade (mesmo dia)
        if (empty($workingHours) && $unitId > 0) {
            $stmt = $db->prepare(
                "SELECT start_time, end_time FROM professional_working_hours
                 WHERE tenant_id = ? AND professional_id = ? AND day_of_week = ? AND is_active = 1"
            );
            $stmt->execute([$tenantId, $professionalId, $dayOfWeek]);
            $workingHours = $stmt->fetchAll();
        }

        if (empty($workingHours)) {
            // Verifica se o profissional tem horários para qualquer outro dia
            $anyStmt = $db->prepare(
                "SELECT COUNT(*) FROM professional_working_hours
                 WHERE tenant_id = ? AND professional_id = ? AND is_active = 1"
            );
            $anyStmt->execute([$tenantId, $professionalId]);

            if ((int) $anyStmt->fetchColumn() > 0) {
                // Tem horários configurados, mas não para este dia → dia fechado
                return [];
            }

            // Nenhum horário configurado → modo permissivo (padrão comercial 08h-18h)
            $workingHours = [['start_time' => '08:00:00', 'end_time' => '18:00:00']];
        }

        // Intervalos do profissional
        $stmt = $db->prepare(
            "SELECT start_time, end_time FROM professional_breaks
             WHERE tenant_id = ? AND professional_id = ? AND (day_of_week = ? OR day_of_week IS NULL)"
        );
        $stmt->execute([$tenantId, $professionalId, $dayOfWeek]);
        $breaks = $stmt->fetchAll();

        // Agendamentos existentes
        $stmt = $db->prepare(
            "SELECT start_time, end_time FROM appointments
             WHERE tenant_id = ? AND professional_id = ? AND date = ?
             AND status NOT IN ('cancelled_by_client', 'cancelled_by_business', 'no_show')"
        );
        $stmt->execute([$tenantId, $professionalId, $date]);
        $booked = $stmt->fetchAll();

        // Bloqueios
        $stmt = $db->prepare(
            "SELECT TIME(start_datetime) as start_time, TIME(end_datetime) as end_time FROM schedule_blocks
             WHERE tenant_id = ? AND (professional_id = ? OR professional_id IS NULL)
             AND DATE(start_datetime) <= ? AND DATE(end_datetime) >= ?"
        );
        $stmt->execute([$tenantId, $professionalId, $date, $date]);
        $blocks = $stmt->fetchAll();

        $occupied = array_merge($booked, $breaks, $blocks);

        // Gera slots
        $slots = [];
        $interval = 15; // Intervalo de slots em minutos

        foreach ($workingHours as $wh) {
            $current = strtotime($wh['start_time']);
            $end     = strtotime($wh['end_time']);

            while (($current + $durationMinutes * 60) <= $end) {
                $slotStart = date('H:i:s', $current);
                $slotEnd   = date('H:i:s', $current + $durationMinutes * 60);

                // Sobreposição só se: início do slot < fim do ocupado E fim do slot > início do ocupado.
                // Assim, slot 14:00–15:00 não conflita com ocupado 13:00–14:00 (fim 14:00 = início do slot).
                $isAvailable = true;
                foreach ($occupied as $occ) {
                    $occEnd   = substr($occ['end_time'], 0, 8); // normaliza para HH:MM:SS
                    $occStart = substr($occ['start_time'], 0, 8);
                    if ($slotStart < $occEnd && $slotEnd > $occStart) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    // Se a data é hoje, não mostra horários já passados.
                    // Usa o timezone do tenant para determinar "agora".
                    if ($date === date('Y-m-d')) {
                        try {
                            $tz      = TenantContext::getData()['timezone'] ?? 'America/Sao_Paulo';
                            $nowTime = (new \DateTimeImmutable('now', new \DateTimeZone($tz)))->format('H:i:s');
                        } catch (\Throwable) {
                            $nowTime = date('H:i:s');
                        }
                        if ($slotStart < $nowTime) {
                            $current += $interval * 60;
                            continue;
                        }
                    }

                    $slots[] = [
                        'start' => substr($slotStart, 0, 5),
                        'end'   => substr($slotEnd, 0, 5),
                    ];
                }

                $current += $interval * 60;
            }
        }

        return $slots;
    }

    private function isWithinWorkingHours(int $professionalId, int $unitId, string $date, string $start, string $end): bool
    {
        $dayOfWeek = (int) date('w', strtotime($date));
        $db        = Database::getInstance();
        $tenantId  = TenantContext::require();

        // 1. Verifica se há horários para este profissional nesta unidade e dia
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM professional_working_hours
             WHERE tenant_id = ? AND professional_id = ? AND unit_id = ?
             AND day_of_week = ? AND is_active = 1"
        );
        $stmt->execute([$tenantId, $professionalId, $unitId, $dayOfWeek]);
        $unitDayCount = (int) $stmt->fetchColumn();

        // Se não há nenhuma regra para esta unidade+dia, verifica se há regras para
        // qualquer unidade (fallback genérico) — se também não há, permite o horário
        if ($unitDayCount === 0) {
            $anyStmt = $db->prepare(
                "SELECT COUNT(*) FROM professional_working_hours
                 WHERE tenant_id = ? AND professional_id = ?"
            );
            $anyStmt->execute([$tenantId, $professionalId]);
            // Sem nenhum horário configurado = sistema permissivo
            if ((int) $anyStmt->fetchColumn() === 0) {
                return true;
            }
            // Há regras em outras unidades/dias mas não nesta combinação = dia fechado
            // Porém se unitId = 0 (não informado), fazemos fallback sem filtro de unidade
            if ($unitId === 0) {
                $stmt2 = $db->prepare(
                    "SELECT COUNT(*) FROM professional_working_hours
                     WHERE tenant_id = ? AND professional_id = ?
                     AND day_of_week = ? AND is_active = 1
                     AND start_time <= ? AND end_time >= ?"
                );
                $stmt2->execute([$tenantId, $professionalId, $dayOfWeek, $start, $end]);
                return (int) $stmt2->fetchColumn() > 0;
            }
            return false;
        }

        // 2. Há regras para esta unidade+dia: verifica se o horário cabe dentro do expediente
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM professional_working_hours
             WHERE tenant_id = ? AND professional_id = ? AND unit_id = ?
             AND day_of_week = ? AND is_active = 1
             AND start_time <= ? AND end_time >= ?"
        );
        $stmt->execute([$tenantId, $professionalId, $unitId, $dayOfWeek, $start, $end]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function isHoliday(string $date, ?int $unitId): bool
    {
        $db = Database::getInstance();
        $tenantId = TenantContext::require();

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM holidays
             WHERE tenant_id = ? AND date = ? AND (unit_id = ? OR unit_id IS NULL)"
        );
        $stmt->execute([$tenantId, $date, $unitId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function isBlocked(int $professionalId, string $date, string $start, string $end): bool
    {
        $db = Database::getInstance();
        $tenantId = TenantContext::require();

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM schedule_blocks
             WHERE tenant_id = ? AND (professional_id = ? OR professional_id IS NULL)
             AND DATE(start_datetime) <= ? AND DATE(end_datetime) >= ?
             AND TIME(start_datetime) < ? AND TIME(end_datetime) > ?"
        );
        $stmt->execute([$tenantId, $professionalId, $date, $date, $end, $start]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
