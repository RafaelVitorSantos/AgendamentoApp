<?php

namespace App\Jobs;

use App\Core\Database;
use App\Services\Calendar\GoogleCalendarService;

/**
 * Renova webhooks do Google Calendar que expirarão em menos de 24h.
 * Deve ser agendado via cron para rodar diariamente.
 */
class RenewCalendarWebhookJob extends BaseJob
{
    public string $queue       = 'calendar';
    public int    $maxAttempts = 3;
    public int    $retryDelay  = 120;

    public function __construct(private int $integrationId) {}

    public function handle(): void
    {
        $db      = Database::getInstance();
        $google  = new GoogleCalendarService();

        $integration = $google->loadIntegration($db, $this->integrationId);
        if (!$integration || !$integration['sync_enabled']) {
            return;
        }

        $google->renewWebhook($db, $integration);
    }
}
