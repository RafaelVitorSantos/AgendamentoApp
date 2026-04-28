<?php

namespace App\Jobs;

use App\Core\Database;
use App\Services\WhatsAppService;

/**
 * Envia notificação WhatsApp para o cliente.
 * Suporta os tipos: confirmation, reminder.
 */
class SendWhatsAppNotificationJob extends BaseJob
{
    public string $queue   = 'notifications';
    public int    $maxAttempts = 2;

    public function __construct(
        private int    $appointmentId,
        private int    $tenantId,
        private string $type   // 'confirmation' | 'reminder'
    ) {}

    public function handle(): void
    {
        $db   = Database::getInstance();
        $data = $this->loadAppointmentData($db);

        if (!$data || empty($data['client_phone'])) {
            return;
        }

        if (!in_array($data['status'], ['scheduled', 'confirmed'], true)) {
            return;
        }

        $wa = new WhatsAppService();

        match ($this->type) {
            'confirmation' => $wa->sendConfirmation($data['client_phone'], $data),
            'reminder'     => $wa->sendReminder($data['client_phone'], $data),
            default        => null,
        };
    }

    private function loadAppointmentData(\PDO $db): ?array
    {
        $stmt = $db->prepare(
            "SELECT
                a.id, a.date, a.start_time, a.end_time, a.status,
                c.phone AS client_phone,
                p.name AS professional_name,
                s.name AS service_name,
                u.name AS unit_name
             FROM appointments a
             LEFT JOIN clients       c ON c.id = a.client_id
             LEFT JOIN professionals p ON p.id = a.professional_id
             LEFT JOIN services      s ON s.id = a.service_id
             LEFT JOIN units         u ON u.id = a.unit_id
             WHERE a.id = ? AND a.tenant_id = ?"
        );
        $stmt->execute([$this->appointmentId, $this->tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
