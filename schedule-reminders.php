#!/usr/bin/env php
<?php
/**
 * Cron de lembretes e retornos. Roda 1× por dia.
 *
 * Cron sugerido (diariamente às 08h00):
 *   0 8 * * * php /var/www/html/AgendamentoApp/schedule-reminders.php >> storage/logs/reminders.log 2>&1
 *
 * O que faz:
 *  1. Agenda lembrete de 24h para agendamentos de amanhã sem lembrete enviado.
 *  2. Gera token de cancelamento para os agendamentos encontrados.
 *  3. Agenda lembrete de retorno para clientes inativos há 30+ dias.
 *  4. Agenda notificação WhatsApp para ambos os cenários (quando configurado).
 */

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/bootstrap.php';

use App\Core\Database;
use App\Services\JobService;
use App\Jobs\SendReminderEmailJob;
use App\Jobs\SendReturnReminderJob;
use App\Jobs\SendWhatsAppNotificationJob;

$db      = Database::getInstance();
$jobSvc  = new JobService();
$tomorrow = date('Y-m-d', strtotime('+1 day'));

output("=== schedule-reminders.php ===");
output("Verificando agendamentos para: {$tomorrow}");

// ── 1. Lembretes 24h ─────────────────────────────────────────────────────────
$stmt = $db->prepare(
    "SELECT a.id, a.tenant_id, a.client_id, c.email, c.phone
     FROM appointments a
     LEFT JOIN clients c ON c.id = a.client_id
     WHERE a.date = ?
       AND a.status IN ('scheduled', 'confirmed')
       AND a.reminder_sent_at IS NULL
       AND (c.email IS NOT NULL OR c.phone IS NOT NULL)"
);
$stmt->execute([$tomorrow]);
$appointments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$reminderCount = 0;
foreach ($appointments as $appt) {
    // Gera token de cancelamento único (se ainda não existe)
    $token = bin2hex(random_bytes(32));
    $db->prepare(
        "UPDATE appointments SET cancel_token = ? WHERE id = ? AND cancel_token IS NULL"
    )->execute([$token, $appt['id']]);

    // Recarrega token (pode já existir de envio anterior cancelado)
    $stmt2 = $db->prepare("SELECT cancel_token FROM appointments WHERE id = ?");
    $stmt2->execute([$appt['id']]);
    $existingToken = $stmt2->fetchColumn();

    // Também insere na tabela appointment_tokens (com TTL de 48h)
    foreach (['cancel', 'reschedule'] as $action) {
        $db->prepare(
            "INSERT IGNORE INTO appointment_tokens (tenant_id, appointment_id, token, action, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))"
        )->execute([$appt['tenant_id'], $appt['id'], $existingToken . "_{$action}", $action]);
    }

    if (!empty($appt['email'])) {
        $jobSvc->dispatch(new SendReminderEmailJob($appt['id'], $appt['tenant_id']));
        $reminderCount++;
    }

    if (!empty($appt['phone'])) {
        $jobSvc->dispatch(new SendWhatsAppNotificationJob($appt['id'], $appt['tenant_id'], 'reminder'));
    }
}
output("Lembretes agendados: {$reminderCount}");

// ── 2. Lembretes de retorno (clientes inativos ≥ 30 dias) ───────────────────
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

$stmt = $db->prepare(
    "SELECT c.id, c.tenant_id, c.email,
            MAX(a.date) AS last_visit
     FROM clients c
     LEFT JOIN appointments a ON a.client_id = c.id
         AND a.status = 'completed'
         AND a.tenant_id = c.tenant_id
     WHERE c.deleted_at IS NULL
       AND c.email IS NOT NULL
       AND (c.return_reminder_sent_at IS NULL
            OR c.return_reminder_sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
     GROUP BY c.id, c.tenant_id, c.email
     HAVING (last_visit IS NULL OR last_visit <= ?)"
);
$stmt->execute([$thirtyDaysAgo]);
$inactiveClients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$returnCount = 0;
foreach ($inactiveClients as $client) {
    $jobSvc->dispatch(new SendReturnReminderJob($client['id'], $client['tenant_id']));
    $returnCount++;
}
output("Lembretes de retorno agendados: {$returnCount}");

output("Concluído.");

function output(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}
