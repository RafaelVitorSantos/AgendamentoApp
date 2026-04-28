<?php

namespace App\Services\Calendar;

use App\Core\Database;
use App\Core\TenantContext;

/**
 * Orquestrador de sincronização bidirecional.
 * Determina quais integrações devem ser sincronizadas e resolve conflitos.
 */
class CalendarSyncService
{
    private GoogleCalendarService $google;
    private \PDO                  $db;

    public function __construct()
    {
        $this->google = new GoogleCalendarService();
        $this->db     = Database::getInstance();
    }

    /**
     * Sincroniza um agendamento para TODOS os provedores ativos do tenant.
     * Chamado pelo SyncAppointmentToCalendarJob.
     *
     * @param string $action  'create' | 'update' | 'delete'
     */
    public function syncAppointment(int $appointmentId, int $tenantId, string $action): void
    {
        $appointment = $this->loadAppointmentDetails($appointmentId);
        if (!$appointment) return;

        $tz           = $this->resolveTz($tenantId);
        $integrations = $this->google->loadIntegrationsForTenant($this->db, $tenantId);

        foreach ($integrations as $integration) {
            if (!$integration['sync_enabled']) continue;

            $start = microtime(true);
            $status = 'success';
            $error  = null;

            try {
                match ($action) {
                    'delete' => $this->google->deleteEvent($this->db, $integration, $appointmentId),
                    default  => $this->google->upsertEvent($this->db, $integration, $appointment, $tz),
                };

                // Marca timestamp de sync no agendamento
                $this->db->prepare(
                    "UPDATE appointments SET calendar_synced_at = NOW() WHERE id = ?"
                )->execute([$appointmentId]);

            } catch (\Throwable $e) {
                $status = 'failed';
                $error  = substr($e->getMessage(), 0, 2000);
                error_log("CalendarSync error: {$e->getMessage()} (integration #{$integration['id']}, appointment #{$appointmentId})");
            }

            $this->log($tenantId, $integration['id'], $appointmentId, 'google', "push_{$action}", $status, $error, (int) ((microtime(true) - $start) * 1000));
        }
    }

    /**
     * Processa um evento recebido via webhook do Google.
     * Estratégia: "sistema é autoridade" — apenas importa eventos criados externamente
     * que não existam no mapa; atualizações do Google sobrescrevem o agendamento local
     * somente se o agendamento não foi modificado no sistema após a última sync.
     */
    public function processGoogleWebhook(string $channelId, string $resourceState): void
    {
        if ($resourceState === 'sync') return; // handshake inicial, ignora

        $stmt = $this->db->prepare(
            "SELECT * FROM calendar_integrations WHERE webhook_channel_id = ? LIMIT 1"
        );
        $stmt->execute([$channelId]);
        $integration = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$integration) return;

        // Busca eventos alterados desde a última sync
        $changes = $this->google->listChangedEvents($this->db, $integration);
        $tz = $this->resolveTz((int) $integration['tenant_id']);

        foreach ($changes as $event) {
            $this->processExternalEvent($integration, $event, $tz);
        }
    }

    // ── Processamento de evento externo ──────────────────────────────────────

    private function processExternalEvent(array $integration, array $event, string $tz): void
    {
        $externalId = $event['id'];
        $status     = $event['status'] ?? 'confirmed';

        // Verifica se este evento está mapeado para um agendamento interno
        $stmt = $this->db->prepare(
            "SELECT * FROM calendar_event_map WHERE integration_id = ? AND external_event_id = ?"
        );
        $stmt->execute([$integration['id'], $externalId]);
        $mapped = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($mapped) {
            // Evento já existe no sistema
            if ($status === 'cancelled') {
                // Evento cancelado no Google → cancela no sistema
                $this->cancelAppointmentFromExternal((int) $mapped['appointment_id'], (int) $integration['tenant_id']);
                $this->log($integration['tenant_id'], $integration['id'], $mapped['appointment_id'], 'google', 'pull_delete', 'success');
            }
            // Atualizações de data/hora do Google são IGNORADAS (sistema é autoridade)
        } else {
            // Evento novo no Google → não importa automaticamente
            // (evita poluição; o profissional deve criar pelo sistema)
            // Apenas loga para visibilidade futura
            $this->log($integration['tenant_id'], $integration['id'], null, 'google', 'pull_create', 'skipped', 'Evento externo não importado automaticamente');
        }
    }

    private function cancelAppointmentFromExternal(int $appointmentId, int $tenantId): void
    {
        TenantContext::set($tenantId);

        $stmt = $this->db->prepare(
            "SELECT status FROM appointments WHERE id = ? AND tenant_id = ? LIMIT 1"
        );
        $stmt->execute([$appointmentId, $tenantId]);
        $appt = $stmt->fetch();

        if (!$appt || !in_array($appt['status'], ['scheduled', 'confirmed'], true)) {
            return; // Já cancelado ou concluído, ignora
        }

        $this->db->prepare(
            "UPDATE appointments SET status = 'cancelled_by_client', cancelled_at = NOW(),
             cancel_reason = 'Cancelado via Google Calendar', updated_at = NOW()
             WHERE id = ? AND tenant_id = ?"
        )->execute([$appointmentId, $tenantId]);
    }

    // ── Utilitários ──────────────────────────────────────────────────────────

    private function loadAppointmentDetails(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*,
                c.name AS client_name, c.email AS client_email,
                p.name AS professional_name, p.email AS professional_email,
                s.name AS service_name,
                u.name AS unit_name,
                CONCAT_WS(', ', NULLIF(u.address_street,''), NULLIF(u.address_city,''), NULLIF(u.address_state,'')) AS unit_address
             FROM appointments a
             LEFT JOIN clients       c ON c.id = a.client_id
             LEFT JOIN professionals p ON p.id = a.professional_id
             LEFT JOIN services      s ON s.id = a.service_id
             LEFT JOIN units         u ON u.id = a.unit_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function resolveTz(int $tenantId): string
    {
        $stmt = $this->db->prepare("SELECT timezone FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        return $stmt->fetchColumn() ?: 'America/Sao_Paulo';
    }

    private function log(int $tenantId, ?int $integrationId, ?int $appointmentId, string $provider, string $action, string $status, ?string $error = null, int $durationMs = 0): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO calendar_sync_logs (tenant_id, integration_id, appointment_id, provider, action, status, error, duration_ms)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$tenantId, $integrationId, $appointmentId, $provider, $action, $status, $error, $durationMs]);
        } catch (\Throwable) {
            // Log não deve quebrar o fluxo principal
        }
    }
}
