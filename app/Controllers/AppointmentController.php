<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Client;
use App\Models\Unit;
use App\Services\AppointmentService;
use App\Services\AuditService;

class AppointmentController extends Controller
{
    private Appointment $model;
    private AppointmentService $service;

    public function __construct()
    {
        $this->model = new Appointment();
        $this->service = new AppointmentService();
    }

    public function index(): void
    {
        $this->authorize('appointments.view');

        $date = $this->input('date', date('Y-m-d'));
        $unitId = $this->input('unit_id');
        $professionalId = $this->input('professional_id');

        $appointments = $this->model->getByDate($date, $unitId ? (int)$unitId : null, $professionalId ? (int)$professionalId : null);

        $professionalModel = new Professional();
        $professionals = $professionalModel->all(['is_active' => 1], 'name ASC');

        $this->render('appointments.index', [
            'appointments'  => $appointments,
            'professionals' => $professionals,
            'currentDate'   => $date,
            'pageTitle'     => 'Agenda',
        ]);
    }

    public function create(): void
    {
        $this->authorize('appointments.create');

        $serviceModel = new Service();
        $professionalModel = new Professional();
        $clientModel = new Client();

        $this->render('appointments.create', [
            'services'      => $serviceModel->getActiveWithCategory(),
            'professionals' => $professionalModel->getActiveWithServices(),
            'clients'       => $clientModel->all(['is_active' => 1], 'name ASC'),
            'units'         => (new Unit())->all(['is_active' => 1], 'name ASC'),
            'pageTitle'     => 'Novo Agendamento',
        ]);
    }

    public function store(): void
    {
        $this->authorize('appointments.create');

        $errors = $this->validate([
            'professional_id' => 'required|numeric',
            'service_id'      => 'required|numeric',
            'date'            => 'required',
            'start_time'      => 'required',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $serviceModel = new Service();
        $service = $serviceModel->find((int) $this->input('service_id'));

        if (!$service) {
            flash('error', 'Serviço não encontrado.');
            back();
        }

        $result = $this->service->create([
            'unit_id'          => (int) ($this->input('unit_id') ?: $_SESSION['default_unit_id'] ?? 1),
            'client_id'        => $this->input('client_id') ? (int) $this->input('client_id') : null,
            'professional_id'  => (int) $this->input('professional_id'),
            'service_id'       => (int) $this->input('service_id'),
            'date'             => $this->input('date'),
            'start_time'       => $this->input('start_time') . ':00',
            'duration_minutes' => (int) $service['duration_minutes'],
            'price'            => (float) $service['price'],
            'source'           => 'manual',
            'notes'            => $this->input('notes'),
            'created_by'       => $this->userId(),
        ]);

        if ($result['success']) {
            AuditService::log('create', 'appointments', $result['id']);
            flash('success', 'Agendamento criado com sucesso!');
            redirect(url('appointments?date=' . $this->input('date')));
        } else {
            flash('error', $result['error']);
            back();
        }
    }

    public function cancel(string $id): void
    {
        $this->authorize('appointments.cancel');

        $reason = $this->input('reason', '');
        $success = $this->service->cancel((int) $id, 'business', $reason);

        if ($success) {
            AuditService::log('cancel', 'appointments', (int) $id);
            flash('success', 'Agendamento cancelado.');
        } else {
            flash('error', 'Não foi possível cancelar.');
        }

        back();
    }

    public function changeStatus(string $id): void
    {
        $this->authorize('appointments.edit');

        $status = $this->input('status');
        $success = $this->service->changeStatus((int) $id, $status);

        if ($this->isAjax()) {
            $this->json(['success' => $success]);
            return;
        }

        flash($success ? 'success' : 'error', $success ? 'Status atualizado.' : 'Não foi possível alterar o status.');
        back();
    }

    /**
     * API: Retorna slots disponíveis (JSON).
     */
    public function availableSlots(): void
    {
        $professionalId = (int) $this->input('professional_id');
        $unitId = (int) $this->input('unit_id');
        $date = $this->input('date');
        $serviceId = (int) $this->input('service_id');

        if (!$professionalId || !$date || !$serviceId) {
            $this->json(['error' => 'Parâmetros obrigatórios.'], 400);
            return;
        }

        $serviceModel = new Service();
        $service = $serviceModel->find($serviceId);

        if (!$service) {
            $this->json(['error' => 'Serviço não encontrado.'], 404);
            return;
        }

        $slots = $this->service->getAvailableSlots(
            $professionalId,
            $unitId,
            $date,
            (int) $service['duration_minutes']
        );

        $this->json(['slots' => $slots]);
    }

    /**
     * API: Dados para o FullCalendar (JSON).
     */
    public function calendarEvents(): void
    {
        $start = $this->input('start', date('Y-m-d'));
        $end = $this->input('end', date('Y-m-d', strtotime('+7 days')));
        $professionalId = $this->input('professional_id');

        $db = \App\Core\Database::getInstance();
        $tenantId = $this->tenantId();

        $sql = "SELECT a.id, a.date, a.start_time, a.end_time, a.status, a.notes,
                       c.name as client_name, p.name as professional_name, p.color,
                       s.name as service_name
                FROM appointments a
                LEFT JOIN clients c ON c.id = a.client_id
                JOIN professionals p ON p.id = a.professional_id
                JOIN services s ON s.id = a.service_id
                WHERE a.tenant_id = ? AND a.date BETWEEN ? AND ?";

        $params = [$tenantId, $start, $end];

        if ($professionalId) {
            $sql .= " AND a.professional_id = ?";
            $params[] = (int) $professionalId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $events = array_map(function ($row) {
            $statusColors = [
                'scheduled'   => '#F59E0B',
                'confirmed'   => '#3B82F6',
                'in_progress' => '#8B5CF6',
                'completed'   => '#10B981',
                'no_show'     => '#EF4444',
                'cancelled_by_client'   => '#6B7280',
                'cancelled_by_business' => '#6B7280',
            ];

            return [
                'id'              => $row['id'],
                'title'           => ($row['client_name'] ?? 'Walk-in') . ' — ' . $row['service_name'],
                'start'           => $row['date'] . 'T' . $row['start_time'],
                'end'             => $row['date'] . 'T' . $row['end_time'],
                'backgroundColor' => $statusColors[$row['status']] ?? $row['color'],
                'borderColor'     => $row['color'],
                'extendedProps'   => [
                    'status'       => $row['status'],
                    'professional' => $row['professional_name'],
                    'client'       => $row['client_name'],
                    'service'      => $row['service_name'],
                    'notes'        => $row['notes'],
                ],
            ];
        }, $rows);

        $this->json($events);
    }

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
