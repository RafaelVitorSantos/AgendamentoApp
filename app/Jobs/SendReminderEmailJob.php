<?php

namespace App\Jobs;

use App\Core\Database;
use App\Services\MailService;

/**
 * Envia lembrete 24h antes do agendamento.
 * Disparado pelo script schedule-reminders.php via cron.
 */
class SendReminderEmailJob extends BaseJob
{
    public string $queue = 'emails';

    public function __construct(
        private int $appointmentId,
        private int $tenantId
    ) {}

    public function handle(): void
    {
        $db   = Database::getInstance();
        $data = $this->loadAppointmentData($db);

        if (!$data || empty($data['client_email'])) {
            return;
        }

        // Verifica se agendamento ainda está ativo (pode ter sido cancelado)
        if (!in_array($data['status'], ['scheduled', 'confirmed'], true)) {
            return;
        }

        $mailer = new MailService();
        $html   = $this->renderTemplate($data);

        $mailer->send(
            [$data['client_email'] => $data['client_name']],
            "Lembrete: seu agendamento é amanhã — {$data['company_name']}",
            $html
        );

        // Marca que o lembrete foi enviado para não duplicar
        $db->prepare(
            "UPDATE appointments SET reminder_sent_at = NOW() WHERE id = ? AND tenant_id = ?"
        )->execute([$this->appointmentId, $this->tenantId]);
    }

    private function loadAppointmentData(\PDO $db): ?array
    {
        $stmt = $db->prepare(
            "SELECT
                a.id, a.date, a.start_time, a.end_time, a.status,
                a.cancel_token,
                c.name AS client_name, c.email AS client_email,
                p.name AS professional_name,
                s.name AS service_name,
                u.name AS unit_name,
                t.trade_name AS company_name, t.slug AS tenant_slug
             FROM appointments a
             LEFT JOIN clients       c ON c.id = a.client_id
             LEFT JOIN professionals p ON p.id = a.professional_id
             LEFT JOIN services      s ON s.id = a.service_id
             LEFT JOIN units         u ON u.id = a.unit_id
             LEFT JOIN tenants       t ON t.id = a.tenant_id
             WHERE a.id = ? AND a.tenant_id = ?"
        );
        $stmt->execute([$this->appointmentId, $this->tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function renderTemplate(array $d): string
    {
        $date  = date('d/m/Y', strtotime($d['date']));
        $start = substr($d['start_time'], 0, 5);
        $end   = substr($d['end_time'],   0, 5);
        $appUrl = rtrim(env('APP_URL', ''), '/');

        $cancelLink    = $appUrl . '/booking/cancel/'    . urlencode((string) $d['cancel_token']);
        $rescheduleLink = $appUrl . '/booking/reschedule/' . urlencode((string) $d['cancel_token']);

        $cancelBlock = $d['cancel_token']
            ? "<p style=\"text-align:center;margin-top:20px\">
                 <a href=\"{$cancelLink}\" style=\"background:#ef4444;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;margin-right:8px\">Cancelar</a>
                 <a href=\"{$rescheduleLink}\" style=\"background:#4F46E5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none\">Remarcar</a>
               </p>"
            : "";

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><title>Lembrete de Agendamento</title></head>
        <body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px">
          <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
            <div style="background:#4F46E5;padding:24px;text-align:center">
              <h1 style="color:#fff;margin:0;font-size:22px">{$d['company_name']}</h1>
            </div>
            <div style="padding:32px">
              <h2 style="color:#1f2937;margin-top:0">Seu agendamento é amanhã! ⏰</h2>
              <p style="color:#374151">Olá, <strong>{$d['client_name']}</strong>!</p>
              <p style="color:#374151">Este é um lembrete do seu agendamento para amanhã:</p>

              <table style="width:100%;border-collapse:collapse;margin:20px 0">
                <tr style="background:#f9fafb">
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-weight:bold;width:40%">Data</td>
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#111827">{$date}</td>
                </tr>
                <tr>
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-weight:bold">Horário</td>
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#111827">{$start} – {$end}</td>
                </tr>
                <tr style="background:#f9fafb">
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-weight:bold">Serviço</td>
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#111827">{$d['service_name']}</td>
                </tr>
                <tr>
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-weight:bold">Profissional</td>
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#111827">{$d['professional_name']}</td>
                </tr>
                <tr style="background:#f9fafb">
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#6b7280;font-weight:bold">Local</td>
                  <td style="padding:12px;border:1px solid #e5e7eb;color:#111827">{$d['unit_name']}</td>
                </tr>
              </table>

              {$cancelBlock}
            </div>
            <div style="background:#f9fafb;padding:16px;text-align:center">
              <p style="color:#9ca3af;font-size:12px;margin:0">Este e-mail foi gerado automaticamente. Não responda a esta mensagem.</p>
            </div>
          </div>
        </body>
        </html>
        HTML;
    }
}
