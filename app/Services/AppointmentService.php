<?php

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;
use App\Models\Appointment;

/**
 * Lógica de negócio de agendamentos.
 * Valida disponibilidade, conflitos e limites.
 */
class AppointmentService
{
    private Appointment $model;
    private PlanLimiter $limiter;

    public function __construct()
    {
        $this->model = new Appointment();
        $this->limiter = new PlanLimiter();
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

        return $this->model->update($id, [
            'status'        => $status,
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);
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

        return $this->model->update($id, $updateData);
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

        // Se não tiver para esta unidade, tenta sem filtro de unidade
        if (empty($workingHours) && $unitId > 0) {
            $stmt = $db->prepare(
                "SELECT start_time, end_time FROM professional_working_hours
                 WHERE tenant_id = ? AND professional_id = ? AND day_of_week = ? AND is_active = 1"
            );
            $stmt->execute([$tenantId, $professionalId, $dayOfWeek]);
            $workingHours = $stmt->fetchAll();
        }

        // Se não houver horários configurados para nenhuma unidade, usa padrão 08h-18h
        if (empty($workingHours)) {
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
                    // Se a data é hoje, não mostra horários já passados (slot início estritamente antes de agora)
                    if ($date === date('Y-m-d') && $slotStart < date('H:i:s')) {
                        $current += $interval * 60;
                        continue;
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
