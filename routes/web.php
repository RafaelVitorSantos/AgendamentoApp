<?php

/**
 * Rotas Web da aplicação.
 * $router é uma instância de App\Core\Router.
 */

use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\DashboardController;
use App\Controllers\AppointmentController;
use App\Controllers\ClientController;
use App\Controllers\ServiceController;
use App\Controllers\ProfessionalController;
use App\Controllers\UnitController;
use App\Controllers\FinancialController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\PlaceholderController;
use App\Controllers\QueueController;
use App\Controllers\ScheduleBlockController;
use App\Controllers\HealthController;
use App\Controllers\LgpdController;
use App\Controllers\PublicBookingController;
use App\Controllers\Public\TokenActionController;
use App\Controllers\CalendarController;
use App\Controllers\CalendarWebhookController;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Middleware\CsrfMiddleware;

// Health check (sem autenticação — usado por load balancers e monitoramento)
$router->get('/health', [HealthController::class, 'check']);

// Feed iCal público (autenticado por token na URL, sem sessão)
$router->get('/calendar/{token}.ics', [CalendarController::class, 'feed']);

// Webhook do Google Calendar (sem sessão — autenticado pelo channelId interno)
$router->post('/webhook/google', [CalendarWebhookController::class, 'google']);

// -----------------------------------------------
// Rotas públicas (sem autenticação)
// -----------------------------------------------

// Página pública de agendamento online
$router->get('/book/{slug}', [PublicBookingController::class, 'show']);
$router->post('/book/{slug}', [PublicBookingController::class, 'store']);
$router->get('/book/{slug}/api/professionals', [PublicBookingController::class, 'apiProfessionals']);
$router->get('/book/{slug}/api/slots', [PublicBookingController::class, 'apiSlots']);
$router->get('/book/{slug}/confirm/{id}', [PublicBookingController::class, 'confirm']);

// Cancelamento e remarcação via link (sem login) — S3-06/07
$router->get('/booking/cancel/{token}',        [TokenActionController::class, 'showCancel']);
$router->post('/booking/cancel/{token}',       [TokenActionController::class, 'processCancel'], [CsrfMiddleware::class]);
$router->get('/booking/reschedule/{token}',    [TokenActionController::class, 'showReschedule']);
$router->post('/booking/reschedule/{token}',   [TokenActionController::class, 'processReschedule'], [CsrfMiddleware::class]);

$router->get('/', [LoginController::class, 'showLogin']);
$router->get('/login', [LoginController::class, 'showLogin']);
$router->post('/login', [LoginController::class, 'login'], [CsrfMiddleware::class]);
$router->get('/logout', [LoginController::class, 'logout']);

$router->get('/register', [RegisterController::class, 'showRegister']);
$router->post('/register', [RegisterController::class, 'register'], [CsrfMiddleware::class]);

// -----------------------------------------------
// Rotas autenticadas (empresa)
// -----------------------------------------------
$router->group(['middleware' => [AuthMiddleware::class, TenantMiddleware::class, CsrfMiddleware::class]], function ($router) {

    // Dashboard
    $router->get('/dashboard', [DashboardController::class, 'index']);

    // Agendamentos
    $router->get('/appointments', [AppointmentController::class, 'index']);
    $router->get('/appointments/create', [AppointmentController::class, 'create']);
    $router->post('/appointments', [AppointmentController::class, 'store']);
    $router->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    $router->post('/appointments/{id}/status', [AppointmentController::class, 'changeStatus']);

    // API interna (JSON)
    $router->get('/api/appointments/slots', [AppointmentController::class, 'availableSlots']);
    $router->get('/api/appointments/events', [AppointmentController::class, 'calendarEvents']);

    // Clientes
    $router->get('/clients', [ClientController::class, 'index']);
    $router->get('/clients/create', [ClientController::class, 'create']);
    $router->post('/clients', [ClientController::class, 'store']);
    $router->get('/clients/{id}', [ClientController::class, 'show']);
    $router->get('/clients/{id}/edit', [ClientController::class, 'edit']);
    $router->post('/clients/{id}', [ClientController::class, 'update']);
    $router->post('/clients/{id}/delete', [ClientController::class, 'destroy']);

    // Serviços
    $router->get('/services', [ServiceController::class, 'index']);
    $router->get('/services/create', [ServiceController::class, 'create']);
    $router->post('/services', [ServiceController::class, 'store']);
    $router->get('/services/{id}/edit', [ServiceController::class, 'edit']);
    $router->post('/services/{id}', [ServiceController::class, 'update']);
    $router->post('/services/{id}/delete', [ServiceController::class, 'destroy']);
    $router->post('/services/{id}/toggle', [ServiceController::class, 'toggleStatus']);

    // Profissionais
    $router->get('/professionals', [ProfessionalController::class, 'index']);
    $router->get('/professionals/create', [ProfessionalController::class, 'create']);
    $router->post('/professionals', [ProfessionalController::class, 'store']);
    $router->get('/professionals/{id}/edit', [ProfessionalController::class, 'edit']);
    $router->post('/professionals/{id}', [ProfessionalController::class, 'update']);
    $router->post('/professionals/{id}/delete', [ProfessionalController::class, 'destroy']);
    $router->post('/professionals/{id}/toggle', [ProfessionalController::class, 'toggleStatus']);
    // Horários de funcionamento do profissional
    $router->get('/professionals/{id}/schedule', [ProfessionalController::class, 'schedule']);
    $router->post('/professionals/{id}/schedule', [ProfessionalController::class, 'saveSchedule']);
    $router->post('/professionals/{id}/breaks', [ProfessionalController::class, 'saveBreaks']);

    // Unidades
    $router->get('/units', [UnitController::class, 'index']);
    $router->get('/units/create', [UnitController::class, 'create']);
    $router->post('/units', [UnitController::class, 'store']);
    $router->get('/units/{id}/edit', [UnitController::class, 'edit']);
    $router->post('/units/{id}', [UnitController::class, 'update']);
    $router->post('/units/{id}/delete', [UnitController::class, 'destroy']);

    // Financeiro
    $router->get('/financial', [FinancialController::class, 'index']);
    $router->get('/financial/create', [FinancialController::class, 'create']);
    $router->post('/financial', [FinancialController::class, 'store']);
    $router->post('/financial/{id}/confirm', [FinancialController::class, 'confirm']);
    $router->post('/financial/{id}/delete',  [FinancialController::class, 'destroy']);

    // Relatórios
    $router->get('/reports', [ReportController::class, 'index']);

    // Fila de atendimento
    $router->get('/queue', [QueueController::class, 'index']);
    $router->post('/queue', [QueueController::class, 'store']);
    $router->post('/queue/{id}/status', [QueueController::class, 'updateStatus']);
    $router->post('/queue/{id}/remove', [QueueController::class, 'remove']);

    // Bloqueios de horário
    $router->get('/schedule-blocks', [ScheduleBlockController::class, 'index']);
    $router->get('/schedule-blocks/create', [ScheduleBlockController::class, 'create']);
    $router->post('/schedule-blocks', [ScheduleBlockController::class, 'store']);
    $router->post('/schedule-blocks/{id}/delete', [ScheduleBlockController::class, 'destroy']);
    $router->get('/api/schedule-blocks/events', [ScheduleBlockController::class, 'calendarEvents']);

    // Configurações
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->post('/settings/company', [SettingsController::class, 'updateCompany']);
    $router->post('/settings/password', [SettingsController::class, 'updatePassword']);
    $router->post('/settings/profile', [SettingsController::class, 'updateProfile']);

    // LGPD — portabilidade e direito ao esquecimento
    $router->get('/clients/{id}/export', [LgpdController::class, 'exportClient']);
    $router->post('/clients/{id}/anonymize', [LgpdController::class, 'anonymizeClient']);

    // Calendário — configurações e OAuth
    $router->get('/settings/calendar',                              [CalendarController::class, 'settings']);
    $router->post('/settings/calendar/token/generate',              [CalendarController::class, 'generateToken']);
    $router->post('/settings/calendar/token/revoke',                [CalendarController::class, 'revokeToken']);
    $router->get('/settings/calendar/google/select',                [CalendarController::class, 'googleSelectCalendar']);
    $router->post('/settings/calendar/google/select',               [CalendarController::class, 'googleSaveCalendar']);
    $router->post('/settings/calendar/integrations/{id}/toggle',    [CalendarController::class, 'toggleIntegration']);
    $router->post('/settings/calendar/integrations/{id}/delete',    [CalendarController::class, 'deleteIntegration']);

    // OAuth do Google (redireciona para Google e recebe callback)
    $router->get('/oauth/google',          [CalendarController::class, 'googleOAuth']);
    $router->get('/oauth/google/callback', [CalendarController::class, 'googleCallback']);
});
