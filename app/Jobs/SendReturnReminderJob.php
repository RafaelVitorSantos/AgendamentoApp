<?php

namespace App\Jobs;

use App\Core\Database;
use App\Services\MailService;

/**
 * Lembrete de retorno: cliente sem visita há 30+ dias.
 * Disparado por cron diário (schedule-reminders.php).
 */
class SendReturnReminderJob extends BaseJob
{
    public string $queue = 'emails';

    public function __construct(
        private int $clientId,
        private int $tenantId
    ) {}

    public function handle(): void
    {
        $db   = Database::getInstance();
        $data = $this->loadClientData($db);

        if (!$data || empty($data['client_email'])) {
            return;
        }

        $mailer = new MailService();
        $html   = $this->renderTemplate($data);

        $mailer->send(
            [$data['client_email'] => $data['client_name']],
            "Sentimos sua falta! Que tal agendar uma visita? — {$data['company_name']}",
            $html
        );

        // Marca envio para evitar envio duplicado na mesma janela
        $db->prepare(
            "UPDATE clients SET return_reminder_sent_at = NOW() WHERE id = ? AND tenant_id = ?"
        )->execute([$this->clientId, $this->tenantId]);
    }

    private function loadClientData(\PDO $db): ?array
    {
        $stmt = $db->prepare(
            "SELECT
                c.id, c.name AS client_name, c.email AS client_email,
                t.trade_name AS company_name, t.slug AS tenant_slug
             FROM clients c
             JOIN tenants t ON t.id = c.tenant_id
             WHERE c.id = ? AND c.tenant_id = ? AND c.deleted_at IS NULL"
        );
        $stmt->execute([$this->clientId, $this->tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function renderTemplate(array $d): string
    {
        $appUrl     = rtrim(env('APP_URL', ''), '/');
        $bookingUrl = $appUrl . '/' . $d['tenant_slug'];

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><title>Sentimos sua falta!</title></head>
        <body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px">
          <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden">
            <div style="background:#4F46E5;padding:24px;text-align:center">
              <h1 style="color:#fff;margin:0;font-size:22px">{$d['company_name']}</h1>
            </div>
            <div style="padding:32px;text-align:center">
              <h2 style="color:#1f2937;margin-top:0">Sentimos sua falta, {$d['client_name']}! 💙</h2>
              <p style="color:#374151;font-size:16px">Faz um tempinho que você não nos visita.<br>Que tal agendar um horário?</p>
              <a href="{$bookingUrl}" style="display:inline-block;margin-top:20px;background:#4F46E5;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-size:16px;font-weight:bold">
                Agendar agora
              </a>
            </div>
            <div style="background:#f9fafb;padding:16px;text-align:center">
              <p style="color:#9ca3af;font-size:12px;margin:0">Você recebe este e-mail pois é cliente de {$d['company_name']}.<br>Para não receber mais comunicações, entre em contato conosco.</p>
            </div>
          </div>
        </body>
        </html>
        HTML;
    }
}
