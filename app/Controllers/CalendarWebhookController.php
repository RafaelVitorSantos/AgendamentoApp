<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Calendar\CalendarSyncService;

/**
 * Recebe push notifications do Google Calendar.
 *
 * O Google envia POST com headers:
 *   X-Goog-Channel-Id: <channel_id>
 *   X-Goog-Resource-State: sync|exists|not_exists
 *   X-Goog-Resource-Id: <resource_id>
 *
 * Endpoint: POST /webhook/google  (público, sem autenticação por sessão)
 */
class CalendarWebhookController extends Controller
{
    public function google(): void
    {
        $channelId     = $_SERVER['HTTP_X_GOOG_CHANNEL_ID']     ?? '';
        $resourceState = $_SERVER['HTTP_X_GOOG_RESOURCE_STATE'] ?? '';

        if (!$channelId) {
            http_response_code(400);
            return;
        }

        try {
            $svc = new CalendarSyncService();
            $svc->processGoogleWebhook($channelId, $resourceState);
        } catch (\Throwable $e) {
            error_log("CalendarWebhook error: " . $e->getMessage());
        }

        // Google espera 200 OK para confirmar recebimento
        http_response_code(200);
    }
}
