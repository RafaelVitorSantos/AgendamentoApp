<?php

namespace App\Jobs;

use App\Services\Calendar\CalendarSyncService;

/**
 * Job assíncrono de sincronização de agendamento com provedores de calendário.
 * Fila dedicada 'calendar' para não bloquear a fila de e-mails.
 */
class SyncAppointmentToCalendarJob extends BaseJob
{
    public string $queue       = 'calendar';
    public int    $maxAttempts = 3;
    public int    $retryDelay  = 30;

    public function __construct(
        private int    $appointmentId,
        private int    $tenantId,
        private string $action   // 'create' | 'update' | 'delete'
    ) {}

    public function handle(): void
    {
        $svc = new CalendarSyncService();
        $svc->syncAppointment($this->appointmentId, $this->tenantId, $this->action);
    }
}
