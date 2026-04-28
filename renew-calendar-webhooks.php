#!/usr/bin/env php
<?php
/**
 * Renova webhooks do Google Calendar que expirarão em menos de 24h.
 *
 * Adicione ao crontab para rodar diariamente:
 *   0 6 * * * php /path/to/renew-calendar-webhooks.php >> /path/to/storage/logs/calendar.log 2>&1
 */

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/bootstrap.php';

use App\Core\Database;
use App\Services\JobService;
use App\Jobs\RenewCalendarWebhookJob;

$db     = Database::getInstance();
$jobSvc = new JobService();

// Busca integrações com webhook expirando em menos de 24h
$stmt = $db->prepare(
    "SELECT id FROM calendar_integrations
     WHERE sync_enabled = 1
       AND webhook_channel_id IS NOT NULL
       AND (
           webhook_expires_at IS NULL
           OR webhook_expires_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
       )"
);
$stmt->execute();
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$count = 0;
foreach ($rows as $row) {
    $jobSvc->dispatch(new RenewCalendarWebhookJob((int) $row['id']));
    $count++;
}

echo '[' . date('Y-m-d H:i:s') . "] Enfileirados {$count} jobs de renovação de webhook.\n";
