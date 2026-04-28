<?php

namespace App\Services\Calendar;

use App\Core\Database;

/**
 * Integração com Google Calendar API v3 via OAuth 2.0.
 * Implementado com chamadas HTTP nativas (sem dependências externas).
 *
 * Scopes necessários no Google Cloud Console:
 *   https://www.googleapis.com/auth/calendar
 *
 * Referências:
 *  - https://developers.google.com/calendar/api/v3/reference
 *  - https://developers.google.com/identity/protocols/oauth2/web-server
 */
class GoogleCalendarService
{
    private const API_BASE     = 'https://www.googleapis.com/calendar/v3';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const REVOKE_URL   = 'https://oauth2.googleapis.com/revoke';
    private const WEBHOOK_URL  = 'https://www.googleapis.com/calendar/v3/calendars/{calendarId}/events/watch';
    private const SCOPE        = 'https://www.googleapis.com/auth/calendar';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $appKey;

    public function __construct()
    {
        $this->clientId     = env('GOOGLE_CLIENT_ID', '');
        $this->clientSecret = env('GOOGLE_CLIENT_SECRET', '');
        $this->redirectUri  = rtrim(env('APP_URL', ''), '/') . '/oauth/google/callback';
        $this->appKey       = env('APP_KEY', '');
    }

    // ── OAuth 2.0 ────────────────────────────────────────────────────────────

    /**
     * Gera a URL de autorização para redirecionar o usuário ao Google.
     */
    public function getAuthUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUri,
            'response_type'         => 'code',
            'scope'                 => self::SCOPE,
            'access_type'           => 'offline',
            'prompt'                => 'consent',         // garante refresh_token sempre
            'include_granted_scopes'=> 'true',
            'state'                 => $state,
        ]);
    }

    /**
     * Troca o authorization code por access_token + refresh_token.
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (!isset($response['access_token'])) {
            throw new \RuntimeException('Google OAuth: falha ao obter token — ' . ($response['error_description'] ?? json_encode($response)));
        }

        return $response;
    }

    /**
     * Salva os tokens de uma integração no banco, criptografados.
     */
    public function saveIntegration(\PDO $db, int $userId, int $tenantId, ?int $professionalId, array $tokens): int
    {
        // Busca informações da conta Google
        $profile = $this->getProfile($tokens['access_token']);

        $expiresAt = date('Y-m-d H:i:s', time() + (int) ($tokens['expires_in'] ?? 3600));

        $existing = $db->prepare("SELECT id FROM calendar_integrations WHERE user_id = ? AND provider = 'google'");
        $existing->execute([$userId]);
        $row = $existing->fetch();

        $data = [
            'access_token'      => $this->encrypt($tokens['access_token']),
            'token_expires_at'  => $expiresAt,
            'provider_account'  => $profile['email'] ?? null,
            'sync_enabled'      => 1,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if (!empty($tokens['refresh_token'])) {
            $data['refresh_token'] = $this->encrypt($tokens['refresh_token']);
        }

        if ($row) {
            $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            $db->prepare("UPDATE calendar_integrations SET {$sets} WHERE id = ?")
               ->execute([...array_values($data), $row['id']]);
            return (int) $row['id'];
        }

        $db->prepare(
            "INSERT INTO calendar_integrations
                (tenant_id, user_id, professional_id, provider, provider_account,
                 access_token, refresh_token, token_expires_at, sync_direction, created_at, updated_at)
             VALUES (?, ?, ?, 'google', ?, ?, ?, ?, 'push_only', NOW(), NOW())"
        )->execute([
            $tenantId,
            $userId,
            $professionalId,
            $profile['email'] ?? null,
            $this->encrypt($tokens['access_token']),
            isset($tokens['refresh_token']) ? $this->encrypt($tokens['refresh_token']) : null,
            $expiresAt,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Lista as agendas do usuário para seleção.
     */
    public function listCalendars(array $integration): array
    {
        $token = $this->getValidToken($integration);
        $data  = $this->apiGet('/users/me/calendarList', $token);
        return $data['items'] ?? [];
    }

    /**
     * Salva a agenda selecionada e configura o webhook push.
     */
    public function selectCalendar(\PDO $db, int $integrationId, string $calendarId, string $calendarName): void
    {
        $db->prepare(
            "UPDATE calendar_integrations SET calendar_id = ?, calendar_name = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$calendarId, $calendarName, $integrationId]);

        // Configura webhook push para receber atualizações do Google
        $integration = $this->loadIntegration($db, $integrationId);
        $this->registerWebhook($db, $integration);
    }

    // ── CRUD de Eventos ──────────────────────────────────────────────────────

    /**
     * Cria ou atualiza um evento no Google Calendar.
     * Retorna o ID do evento Google.
     */
    public function upsertEvent(\PDO $db, array $integration, array $appointment, string $tz): ?string
    {
        $token      = $this->getValidToken($integration);
        $calendarId = urlencode($integration['calendar_id'] ?? 'primary');

        // Verifica se já existe mapeamento
        $stmt = $db->prepare(
            "SELECT external_event_id, etag FROM calendar_event_map
             WHERE integration_id = ? AND appointment_id = ?"
        );
        $stmt->execute([$integration['id'], $appointment['id']]);
        $existing = $stmt->fetch();

        $body = $this->buildEventBody($appointment, $tz);

        if ($existing) {
            // Atualiza evento existente
            $eventId = $existing['external_event_id'];
            if (!empty($existing['etag'])) {
                $body['etag'] = $existing['etag'];
            }
            $result = $this->apiPut(
                "/calendars/{$calendarId}/events/{$eventId}",
                $token,
                $body
            );
        } else {
            // Cria novo evento
            $result = $this->apiPost(
                "/calendars/{$calendarId}/events",
                $token,
                $body
            );
        }

        if (empty($result['id'])) {
            return null;
        }

        $eventId = $result['id'];
        $etag    = $result['etag'] ?? null;

        // Upsert no mapa
        $db->prepare(
            "INSERT INTO calendar_event_map
                (tenant_id, integration_id, appointment_id, external_event_id, provider, etag, synced_at, sync_direction)
             VALUES (?, ?, ?, ?, 'google', ?, NOW(), 'push')
             ON DUPLICATE KEY UPDATE external_event_id = VALUES(external_event_id), etag = VALUES(etag), synced_at = NOW()"
        )->execute([
            $integration['tenant_id'],
            $integration['id'],
            $appointment['id'],
            $eventId,
            $etag,
        ]);

        return $eventId;
    }

    /**
     * Remove um evento do Google Calendar (cancelamento).
     */
    public function deleteEvent(\PDO $db, array $integration, int $appointmentId): bool
    {
        $stmt = $db->prepare(
            "SELECT external_event_id FROM calendar_event_map
             WHERE integration_id = ? AND appointment_id = ?"
        );
        $stmt->execute([$integration['id'], $appointmentId]);
        $row = $stmt->fetch();

        if (!$row) return true; // Nada a deletar

        $token      = $this->getValidToken($integration);
        $calendarId = urlencode($integration['calendar_id'] ?? 'primary');
        $eventId    = urlencode($row['external_event_id']);

        $this->apiDelete("/calendars/{$calendarId}/events/{$eventId}", $token);

        $db->prepare(
            "DELETE FROM calendar_event_map WHERE integration_id = ? AND appointment_id = ?"
        )->execute([$integration['id'], $appointmentId]);

        return true;
    }

    // ── Webhooks (Push Notifications) ────────────────────────────────────────

    /**
     * Registra um canal de push notification no Google.
     * O Google irá chamar POST {APP_URL}/webhook/google quando houver mudanças.
     */
    public function registerWebhook(\PDO $db, array $integration): void
    {
        if (empty($integration['calendar_id'])) return;

        $token      = $this->getValidToken($integration);
        $calendarId = urlencode($integration['calendar_id']);
        $channelId  = 'agendapro-' . $integration['id'] . '-' . time();
        $webhookUrl = rtrim(env('APP_URL', ''), '/') . '/webhook/google';

        $result = $this->apiPost(
            "/calendars/{$calendarId}/events/watch",
            $token,
            [
                'id'      => $channelId,
                'type'    => 'web_hook',
                'address' => $webhookUrl,
                'expiration' => (string) ((time() + 604800) * 1000), // 7 dias em ms
            ]
        );

        if (!empty($result['id'])) {
            $expiresAt = date('Y-m-d H:i:s', (int) ($result['expiration'] / 1000));
            $db->prepare(
                "UPDATE calendar_integrations
                 SET webhook_channel_id = ?, webhook_resource_id = ?, webhook_expires_at = ?, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$result['id'], $result['resourceId'] ?? null, $expiresAt, $integration['id']]);
        }
    }

    /**
     * Para um canal de webhook ativo (sem registrar novo).
     * Usado ao deletar a integração.
     */
    public function stopWebhookChannel(\PDO $db, array $integration): void
    {
        if (empty($integration['webhook_channel_id'])) return;

        $token = $this->getValidToken($integration);
        $this->apiPost('/channels/stop', $token, [
            'id'         => $integration['webhook_channel_id'],
            'resourceId' => $integration['webhook_resource_id'] ?? '',
        ]);

        $db->prepare(
            "UPDATE calendar_integrations
             SET webhook_channel_id = NULL, webhook_resource_id = NULL,
                 webhook_expires_at = NULL, updated_at = NOW()
             WHERE id = ?"
        )->execute([$integration['id']]);
    }

    /**
     * Renovação do webhook (chamar antes de expirar via cron/job).
     */
    public function renewWebhook(\PDO $db, array $integration): void
    {
        // Para o canal antigo
        $this->stopWebhookChannel($db, $integration);
        // Recarrega integração com webhook_channel_id limpo
        $fresh = $this->loadIntegration($db, $integration['id']);
        // Registra novo
        $this->registerWebhook($db, $fresh ?? $integration);
    }

    /**
     * Lista eventos modificados desde o último sync (incremental via syncToken).
     */
    public function listChangedEvents(\PDO $db, array $integration): array
    {
        $token      = $this->getValidToken($integration);
        $calendarId = urlencode($integration['calendar_id'] ?? 'primary');

        $params = ['maxResults' => 250, 'singleEvents' => 'true'];

        if (!empty($integration['sync_token'])) {
            $params['syncToken'] = $integration['sync_token'];
        } else {
            // Primeira sync: últimos 30 dias
            $params['timeMin'] = date('c', strtotime('-30 days'));
        }

        $data = $this->apiGet("/calendars/{$calendarId}/events?" . http_build_query($params), $token);

        // Salva o novo syncToken para próxima chamada
        if (!empty($data['nextSyncToken'])) {
            $db->prepare(
                "UPDATE calendar_integrations SET sync_token = ?, last_sync_at = NOW(), updated_at = NOW() WHERE id = ?"
            )->execute([$data['nextSyncToken'], $integration['id']]);
        }

        return $data['items'] ?? [];
    }

    // ── Helpers internos ─────────────────────────────────────────────────────

    private function buildEventBody(array $a, string $tz): array
    {
        $dtStart = $a['date'] . 'T' . $a['start_time'];
        $dtEnd   = $a['date'] . 'T' . $a['end_time'];

        $summary = ($a['service_name'] ?? 'Atendimento');
        if (!empty($a['professional_name'])) {
            $summary .= ' c/ ' . $a['professional_name'];
        }

        $desc = implode("\n", array_filter([
            !empty($a['client_name'])       ? 'Cliente: '      . $a['client_name']       : null,
            !empty($a['professional_name']) ? 'Profissional: '  . $a['professional_name'] : null,
            !empty($a['service_name'])      ? 'Serviço: '       . $a['service_name']      : null,
            !empty($a['notes'])             ? 'Obs: '           . $a['notes']             : null,
        ]));

        $status = match ($a['status'] ?? 'scheduled') {
            'confirmed', 'in_progress', 'completed' => 'confirmed',
            'cancelled_by_client', 'cancelled_by_business', 'no_show' => 'cancelled',
            default => 'tentative',
        };

        $body = [
            'summary'     => $summary,
            'description' => $desc,
            'status'      => $status,
            'start'       => ['dateTime' => $dtStart, 'timeZone' => $tz],
            'end'         => ['dateTime' => $dtEnd,   'timeZone' => $tz],
            'reminders'   => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'popup', 'minutes' => 60],
                    ['method' => 'email', 'minutes' => 1440],
                ],
            ],
        ];

        if (!empty($a['unit_name'])) {
            $body['location'] = implode(', ', array_filter([$a['unit_name'], $a['unit_address'] ?? null]));
        }

        if (!empty($a['client_email'])) {
            $body['attendees'] = [
                ['email' => $a['client_email'], 'displayName' => $a['client_name'] ?? null],
            ];
        }

        return $body;
    }

    private function getProfile(string $accessToken): array
    {
        $data = @file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false,
            stream_context_create(['http' => [
                'header' => "Authorization: Bearer {$accessToken}",
                'timeout' => 5,
                'ignore_errors' => true,
            ]])
        );
        return $data ? json_decode($data, true) ?? [] : [];
    }

    /**
     * Retorna um access_token válido, fazendo refresh se necessário.
     */
    public function getValidToken(array $integration): string
    {
        $accessToken  = $this->decrypt($integration['access_token'] ?? '');
        $expiresAt    = strtotime($integration['token_expires_at'] ?? '0');

        // Considera expirado se faltar menos de 5 minutos
        if ($expiresAt > time() + 300) {
            return $accessToken;
        }

        $refreshToken = $this->decrypt($integration['refresh_token'] ?? '');
        if (!$refreshToken) {
            throw new \RuntimeException("Google: refresh_token ausente para integration #{$integration['id']}");
        }

        return $this->refreshAccessToken($integration['id'], $refreshToken);
    }

    private function refreshAccessToken(int $integrationId, string $refreshToken): string
    {
        $response = $this->post(self::TOKEN_URL, [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (empty($response['access_token'])) {
            throw new \RuntimeException("Google: falha ao renovar token — " . json_encode($response));
        }

        $db         = Database::getInstance();
        $expiresAt  = date('Y-m-d H:i:s', time() + (int) ($response['expires_in'] ?? 3600));

        $db->prepare(
            "UPDATE calendar_integrations
             SET access_token = ?, token_expires_at = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([$this->encrypt($response['access_token']), $expiresAt, $integrationId]);

        return $response['access_token'];
    }

    public function loadIntegration(\PDO $db, int $id): ?array
    {
        $stmt = $db->prepare("SELECT * FROM calendar_integrations WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function loadIntegrationsForTenant(\PDO $db, int $tenantId): array
    {
        $stmt = $db->prepare(
            "SELECT * FROM calendar_integrations
             WHERE tenant_id = ? AND sync_enabled = 1 AND provider = 'google'"
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── HTTP helpers ─────────────────────────────────────────────────────────

    private function apiGet(string $path, string $token): array
    {
        $url = str_starts_with($path, 'http') ? $path : self::API_BASE . $path;
        $data = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'header'        => "Authorization: Bearer {$token}\r\nAccept: application/json",
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]));
        return $data ? json_decode($data, true) ?? [] : [];
    }

    private function apiPost(string $path, string $token, array $body): array
    {
        $url     = str_starts_with($path, 'http') ? $path : self::API_BASE . $path;
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $data    = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\nAccept: application/json",
                'content'       => $payload,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]));
        return $data ? json_decode($data, true) ?? [] : [];
    }

    private function apiPut(string $path, string $token, array $body): array
    {
        $url     = self::API_BASE . $path;
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $data    = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'        => 'PUT',
                'header'        => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\nAccept: application/json",
                'content'       => $payload,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]));
        return $data ? json_decode($data, true) ?? [] : [];
    }

    private function apiDelete(string $path, string $token): void
    {
        @file_get_contents(self::API_BASE . $path, false, stream_context_create([
            'http' => [
                'method'        => 'DELETE',
                'header'        => "Authorization: Bearer {$token}",
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]));
    }

    private function post(string $url, array $params): array
    {
        $data = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded",
                'content'       => http_build_query($params),
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]));
        return $data ? json_decode($data, true) ?? [] : [];
    }

    // ── Criptografia de tokens ────────────────────────────────────────────────

    /**
     * Cifra AES-256-CBC usando APP_KEY como chave.
     */
    public function encrypt(string $value): string
    {
        if (empty($value)) return '';
        $key = substr(hash('sha256', $this->appKey, true), 0, 32);
        $iv  = random_bytes(16);
        $enc = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    public function decrypt(string $value): string
    {
        if (empty($value)) return '';
        try {
            $raw = base64_decode($value);
            $key = substr(hash('sha256', $this->appKey, true), 0, 32);
            $iv  = substr($raw, 0, 16);
            $enc = substr($raw, 16);
            return openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv) ?: '';
        } catch (\Throwable) {
            return '';
        }
    }
}
