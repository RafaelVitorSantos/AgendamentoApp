<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\TenantContext;
use App\Models\CalendarToken;
use App\Models\CalendarIntegration;
use App\Services\Calendar\ICalService;
use App\Services\Calendar\GoogleCalendarService;
use App\Services\JobService;
use App\Jobs\RenewCalendarWebhookJob;

class CalendarController extends Controller
{
    // ── iCal Feed (público, autenticado por token) ───────────────────────────

    /**
     * GET /calendar/{token}.ics
     * Serve o feed iCal. Não requer sessão — autenticado pelo token na URL.
     */
    public function feed(string $token): void
    {
        $tokenModel = new CalendarToken();
        $tokenRow   = $tokenModel->findByToken($token);

        if (!$tokenRow) {
            http_response_code(404);
            echo "Feed não encontrado ou token revogado.";
            return;
        }

        TenantContext::set((int) $tokenRow['tenant_id']);

        $ical    = new ICalService();
        $content = $ical->generateFeed($tokenRow);

        $filename = 'agendapro-' . ($tokenRow['professional_name'] ?? 'agenda') . '.ics';
        $filename = preg_replace('/[^a-z0-9\-]/i', '-', $filename);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        echo $content;
    }

    // ── Página de configurações de calendário ────────────────────────────────

    /**
     * GET /settings/calendar
     */
    public function settings(): void
    {
        $tenantId = $this->tenantId();
        $userId   = $this->userId();

        $tokenModel       = new CalendarToken();
        $integrationModel = new CalendarIntegration();

        $calendarToken = $tokenModel->findForUser($userId, $tenantId);
        $integrations  = $integrationModel->allForTenant($tenantId);

        $this->render('settings.calendar', [
            'calendarToken' => $calendarToken,
            'integrations'  => $integrations,
            'appUrl'        => rtrim(env('APP_URL', ''), '/'),
            'googleEnabled' => !empty(env('GOOGLE_CLIENT_ID', '')),
        ]);
    }

    /**
     * POST /settings/calendar/token/generate
     * Gera ou regenera o token iCal do usuário logado.
     */
    public function generateToken(): void
    {
        $tenantId      = $this->tenantId();
        $userId        = $this->userId();
        $professionalId = $_SESSION['professional_id'] ?? null;

        $tokenModel = new CalendarToken();
        $result     = $tokenModel->generate($userId, $tenantId, $professionalId);

        flash('success', 'URL do feed iCal gerada com sucesso!');
        header('Location: ' . url('settings/calendar'));
        exit;
    }

    /**
     * POST /settings/calendar/token/revoke
     * Revoga o token iCal do usuário.
     */
    public function revokeToken(): void
    {
        $tenantId = $this->tenantId();
        $userId   = $this->userId();

        $tokenModel = new CalendarToken();
        $tokenModel->revoke($userId, $tenantId);

        flash('success', 'Feed iCal desativado.');
        header('Location: ' . url('settings/calendar'));
        exit;
    }

    // ── Google OAuth ─────────────────────────────────────────────────────────

    /**
     * GET /oauth/google
     * Inicia o fluxo OAuth do Google.
     */
    public function googleOAuth(): void
    {
        if (empty(env('GOOGLE_CLIENT_ID', ''))) {
            flash('error', 'Integração com Google Calendar não configurada. Adicione GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET no .env.');
            header('Location: ' . url('settings/calendar'));
            exit;
        }

        // State para CSRF: contém user_id e timestamp assinados
        $state = base64_encode(json_encode([
            'user_id'   => $this->userId(),
            'tenant_id' => $this->tenantId(),
            'ts'        => time(),
            'nonce'     => bin2hex(random_bytes(8)),
        ]));

        $_SESSION['oauth_state'] = $state;

        $google = new GoogleCalendarService();
        header('Location: ' . $google->getAuthUrl($state));
        exit;
    }

    /**
     * GET /oauth/google/callback
     * Callback do OAuth — troca o code por tokens e salva integração.
     */
    public function googleCallback(): void
    {
        $error = $_GET['error'] ?? null;
        if ($error) {
            flash('error', 'Autorização negada: ' . htmlspecialchars($error));
            header('Location: ' . url('settings/calendar'));
            exit;
        }

        $state    = $_GET['state'] ?? '';
        $code     = $_GET['code']  ?? '';
        $expected = $_SESSION['oauth_state'] ?? '';

        if (!$state || $state !== $expected) {
            flash('error', 'Estado OAuth inválido. Tente novamente.');
            header('Location: ' . url('settings/calendar'));
            exit;
        }

        unset($_SESSION['oauth_state']);

        $stateData  = json_decode(base64_decode($state), true) ?? [];
        $userId     = (int) ($stateData['user_id']   ?? 0);
        $tenantId   = (int) ($stateData['tenant_id'] ?? 0);
        $stateAge   = time() - (int) ($stateData['ts'] ?? 0);

        // Rejeita estados com mais de 10 minutos (previne replay)
        if ($stateAge > 600) {
            flash('error', 'Sessão OAuth expirada. Tente novamente.');
            header('Location: ' . url('settings/calendar'));
            exit;
        }

        if ($userId !== $this->userId() || $tenantId !== $this->tenantId()) {
            flash('error', 'Sessão inválida. Tente novamente.');
            header('Location: ' . url('settings/calendar'));
            exit;
        }

        try {
            $google         = new GoogleCalendarService();
            $tokens         = $google->exchangeCode($code);
            $professionalId = $_SESSION['professional_id'] ?? null;

            $db             = Database::getInstance();
            $integrationId  = $google->saveIntegration($db, $userId, $tenantId, $professionalId, $tokens);

            $_SESSION['calendar_integration_id'] = $integrationId;

            flash('success', 'Google Calendar conectado! Agora selecione a agenda que deseja sincronizar.');
            header('Location: ' . url('settings/calendar/google/select'));
        } catch (\Throwable $e) {
            error_log("GoogleOAuth callback error: " . $e->getMessage());
            flash('error', 'Erro ao conectar com Google Calendar. Tente novamente.');
            header('Location: ' . url('settings/calendar'));
        }
        exit;
    }

    /**
     * GET /settings/calendar/google/select
     * Exibe lista de agendas para o usuário escolher.
     */
    public function googleSelectCalendar(): void
    {
        $integrationId = $_SESSION['calendar_integration_id'] ?? null;
        if (!$integrationId) {
            header('Location: ' . url('settings/calendar'));
            exit;
        }

        $db       = Database::getInstance();
        $google   = new GoogleCalendarService();
        $integration = $google->loadIntegration($db, (int) $integrationId);

        if (!$integration || (int) $integration['tenant_id'] !== $this->tenantId()) {
            flash('error', 'Integração não encontrada.');
            header('Location: ' . url('settings/calendar'));
            exit;
        }

        try {
            $calendars = $google->listCalendars($integration);
        } catch (\Throwable $e) {
            error_log("GoogleCalendar listCalendars error: " . $e->getMessage());
            $calendars = [];
        }

        $this->render('settings.calendar_select', [
            'calendars'     => $calendars,
            'integrationId' => $integrationId,
        ]);
    }

    /**
     * POST /settings/calendar/google/select
     * Salva a agenda selecionada e configura webhook.
     */
    public function googleSaveCalendar(): void
    {
        $integrationId = (int) ($_POST['integration_id'] ?? 0);
        $calendarId    = $_POST['calendar_id']   ?? '';
        $calendarName  = $_POST['calendar_name'] ?? '';

        if (!$integrationId || !$calendarId) {
            flash('error', 'Selecione uma agenda.');
            header('Location: ' . url('settings/calendar/google/select'));
            exit;
        }

        try {
            $db     = Database::getInstance();
            $google = new GoogleCalendarService();
            $google->selectCalendar($db, $integrationId, $calendarId, $calendarName);

            unset($_SESSION['calendar_integration_id']);
            flash('success', 'Agenda selecionada! A sincronização está ativa.');
        } catch (\Throwable $e) {
            error_log("GoogleCalendar selectCalendar error: " . $e->getMessage());
            flash('error', 'Erro ao configurar agenda. Tente novamente.');
        }

        header('Location: ' . url('settings/calendar'));
        exit;
    }

    /**
     * POST /settings/calendar/integrations/{id}/toggle
     * Ativa/desativa sincronização de uma integração.
     */
    public function toggleIntegration(int $id): void
    {
        $tenantId = $this->tenantId();
        $enabled  = (bool) ($this->input('sync_enabled', false));

        $model = new CalendarIntegration();
        $model->toggleSync($id, $tenantId, $enabled);

        $this->json(['success' => true]);
    }

    /**
     * POST /settings/calendar/integrations/{id}/delete
     * Remove uma integração de calendário.
     */
    public function deleteIntegration(int $id): void
    {
        $tenantId = $this->tenantId();

        // Tenta parar o webhook no Google antes de deletar localmente
        try {
            $db          = Database::getInstance();
            $google      = new GoogleCalendarService();
            $integration = $google->loadIntegration($db, $id);
            if ($integration && !empty($integration['webhook_channel_id'])) {
                $token = $google->getValidToken($integration);
                // Para o canal de push notification no Google
                $google->stopWebhookChannel($db, $integration);
            }
        } catch (\Throwable) {
            // Não bloqueia a remoção local
        }

        $model = new CalendarIntegration();
        $model->remove($id, $tenantId);

        flash('success', 'Integração removida.');
        header('Location: ' . url('settings/calendar'));
        exit;
    }
}
