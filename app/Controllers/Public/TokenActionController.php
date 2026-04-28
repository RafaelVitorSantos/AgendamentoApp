<?php

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AppointmentService;

/**
 * Ações sem login via token: cancelamento e remarcação de agendamento.
 * S3-06 (cancelar via link) e S3-07 (remarcar via link).
 */
class TokenActionController extends Controller
{
    private \PDO               $db;
    private AppointmentService $apptSvc;

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->apptSvc = new AppointmentService();
    }

    // ── GET /booking/cancel/{token} ──────────────────────────────────────────

    public function showCancel(string $token): void
    {
        $appt = $this->resolveToken($token, 'cancel');
        if (!$appt) {
            $this->renderError('Link inválido ou expirado.');
            return;
        }

        $this->view('public/token_cancel', ['appointment' => $appt, 'token' => $token]);
    }

    // ── POST /booking/cancel/{token} ─────────────────────────────────────────

    public function processCancel(string $token): void
    {
        $appt = $this->resolveToken($token, 'cancel');
        if (!$appt) {
            $this->renderError('Link inválido, expirado ou já utilizado.');
            return;
        }

        // Garante isolamento de tenant
        \App\Core\TenantContext::set($appt['tenant_id']);

        $this->apptSvc->cancel($appt['id'], 'client', 'Cancelado pelo cliente via link');
        $this->markTokenUsed($token);

        $this->view('public/token_action_done', [
            'title'   => 'Agendamento cancelado',
            'message' => 'Seu agendamento foi cancelado com sucesso.',
        ]);
    }

    // ── GET /booking/reschedule/{token} ──────────────────────────────────────

    public function showReschedule(string $token): void
    {
        $appt = $this->resolveToken($token, 'reschedule');
        if (!$appt) {
            $this->renderError('Link inválido ou expirado.');
            return;
        }

        // Carrega slots disponíveis para a próxima semana
        \App\Core\TenantContext::set($appt['tenant_id']);
        $dates = $this->nextAvailableDates();
        $slots = [];
        foreach ($dates as $date) {
            $daySlots = $this->apptSvc->getAvailableSlots(
                $appt['professional_id'],
                $appt['unit_id'],
                $date,
                $appt['duration_minutes']
            );
            if (!empty($daySlots)) {
                $slots[$date] = $daySlots;
            }
        }

        $this->view('public/token_reschedule', [
            'appointment' => $appt,
            'token'       => $token,
            'slots'       => $slots,
        ]);
    }

    // ── POST /booking/reschedule/{token} ─────────────────────────────────────

    public function processReschedule(string $token): void
    {
        $appt = $this->resolveToken($token, 'reschedule');
        if (!$appt) {
            $this->renderError('Link inválido, expirado ou já utilizado.');
            return;
        }

        $newDate  = $_POST['date']       ?? '';
        $newStart = $_POST['start_time'] ?? '';

        if (!$newDate || !$newStart) {
            $this->renderError('Selecione uma data e horário.');
            return;
        }

        \App\Core\TenantContext::set($appt['tenant_id']);
        $result = $this->apptSvc->reschedule($appt['id'], $newDate, $newStart);

        if (!$result['success']) {
            $this->renderError($result['error']);
            return;
        }

        $this->markTokenUsed($token);

        $this->view('public/token_action_done', [
            'title'   => 'Agendamento remarcado',
            'message' => 'Seu agendamento foi remarcado com sucesso. Você receberá uma confirmação em breve.',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveToken(string $token, string $action): ?array
    {
        // Tenta pelo cancel_token direto em appointments (lembrete)
        $stmt = $this->db->prepare(
            "SELECT a.*, t.id AS tenant_id
             FROM appointments a
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.cancel_token = ?
               AND a.status IN ('scheduled','confirmed')"
        );
        $stmt->execute([$token]);
        $appt = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($appt) return $appt;

        // Tenta pela tabela appointment_tokens (cancelamento/remarcação específico)
        $stmt = $this->db->prepare(
            "SELECT at.*, a.unit_id, a.professional_id, a.service_id,
                    a.date, a.start_time, a.end_time, a.duration_minutes,
                    a.status, a.client_id, at.tenant_id
             FROM appointment_tokens at
             JOIN appointments a ON a.id = at.appointment_id
             WHERE at.token = ?
               AND at.action = ?
               AND at.expires_at > NOW()
               AND at.used_at IS NULL
               AND a.status IN ('scheduled','confirmed')"
        );
        $stmt->execute([$token . "_{$action}", $action]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;

        $row['id'] = $row['appointment_id'];
        return $row;
    }

    private function markTokenUsed(string $token): void
    {
        // Invalida tanto o token de cancelamento quanto o de remarcação de uma vez
        $this->db->prepare(
            "UPDATE appointment_tokens SET used_at = NOW()
             WHERE token IN (?, ?)"
        )->execute([$token . '_cancel', $token . '_reschedule']);
    }

    private function nextAvailableDates(int $days = 14): array
    {
        $dates = [];
        $ts    = strtotime('+1 day');
        for ($i = 0; $i < $days; $i++) {
            $dates[] = date('Y-m-d', $ts + $i * 86400);
        }
        return $dates;
    }

    private function renderError(string $message): void
    {
        http_response_code(400);
        $this->view('public/token_action_done', [
            'title'   => 'Link inválido',
            'message' => $message,
        ]);
    }
}
