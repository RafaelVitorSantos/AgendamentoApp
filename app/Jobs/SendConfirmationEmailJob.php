<?php

namespace App\Jobs;

use App\Core\Database;
use App\Services\MailService;

/**
 * Envia e-mail de confirmação ao cliente quando um agendamento é criado.
 */
class SendConfirmationEmailJob extends BaseJob
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
            return; // cliente sem e-mail — ignora silenciosamente
        }

        $mailer = new MailService();
        $html   = $this->renderTemplate($data);

        $mailer->send(
            [$data['client_email'] => $data['client_name']],
            "Confirmação de Agendamento — {$data['company_name']}",
            $html
        );
    }

    private function loadAppointmentData(\PDO $db): ?array
    {
        $stmt = $db->prepare(
            "SELECT
                a.id, a.date, a.start_time, a.end_time, a.status,
                c.name AS client_name, c.email AS client_email, c.phone AS client_phone,
                p.name AS professional_name,
                s.name AS service_name,
                u.name AS unit_name,
                CONCAT_WS(', ', NULLIF(u.address_street,''), NULLIF(u.address_city,''), NULLIF(u.address_state,'')) AS unit_address,
                t.trade_name AS company_name, t.email AS company_email
             FROM appointments a
             LEFT JOIN clients      c ON c.id = a.client_id
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

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><title>Confirmação de Agendamento</title></head>
        <body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px">
          <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
            <div style="background:#4F46E5;padding:24px;text-align:center">
              <h1 style="color:#fff;margin:0;font-size:22px">{$d['company_name']}</h1>
            </div>
            <div style="padding:32px">
              <h2 style="color:#1f2937;margin-top:0">Agendamento Confirmado ✓</h2>
              <p style="color:#374151">Olá, <strong>{$d['client_name']}</strong>!</p>
              <p style="color:#374151">Seu agendamento foi registrado com sucesso. Confira os detalhes:</p>

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

              <p style="color:#374151;font-size:14px">Precisa cancelar ou remarcar? Entre em contato conosco.</p>
            </div>
            <div style="background:#f9fafb;padding:16px;text-align:center">
              <p style="color:#9ca3af;font-size:12px;margin:0">Este e-mail foi gerado automaticamente por {$d['company_name']}.</p>
            </div>
          </div>
        </body>
        </html>
        HTML;
    }
}
