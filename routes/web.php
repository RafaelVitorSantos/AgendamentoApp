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
use App\Controllers\PublicBookingController;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Middleware\CsrfMiddleware;

// -----------------------------------------------
// Rotas públicas (sem autenticação)
// -----------------------------------------------

// Página pública de agendamento online
$router->get('/book/{slug}', [PublicBookingController::class, 'show']);
$router->post('/book/{slug}', [PublicBookingController::class, 'store']);
$router->get('/book/{slug}/api/professionals', [PublicBookingController::class, 'apiProfessionals']);
$router->get('/book/{slug}/api/slots', [PublicBookingController::class, 'apiSlots']);
$router->get('/book/{slug}/confirm/{id}', [PublicBookingController::class, 'confirm']);

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
    $router->post('/financial/{id}/delete', [FinancialController::class, 'destroy']);

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
});
