<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\PlanLimiter;

class ProfessionalController extends Controller
{
    private Professional $model;

    public function __construct()
    {
        $this->model = new Professional();
    }

    public function index(): void
    {
        $this->authorize('professionals.view');

        $professionals = $this->model->getAllWithServiceCount();

        $this->render('professionals.index', [
            'professionals' => $professionals,
            'pageTitle'     => 'Profissionais',
        ]);
    }

    public function create(): void
    {
        $this->authorize('professionals.manage');

        $limiter = new PlanLimiter();
        if (!$limiter->canCreateProfessional()) {
            flash('error', 'Limite de profissionais do seu plano atingido. Faça upgrade.');
            redirect(url('professionals'));
        }

        $services = (new Service())->getActiveWithCategory();
        $this->render('professionals.form', [
            'professional' => null,
            'services'     => $services,
            'assigned'     => [],
            'pageTitle'    => 'Novo Profissional',
        ]);
    }

    public function store(): void
    {
        $this->authorize('professionals.manage');

        $errors = $this->validate([
            'name' => 'required|min:2|max:255',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $id = $this->model->create([
            'name'                     => $this->input('name'),
            'email'                    => $this->input('email'),
            'phone'                    => $this->input('phone'),
            'bio'                      => $this->input('bio'),
            'color'                    => $this->input('color', '#3B82F6'),
            'commission_default_type'  => $this->input('commission_default_type', 'percentage'),
            'commission_default_value' => (float) str_replace(',', '.', $this->input('commission_default_value', '0')),
            'is_active'                => 1,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $serviceIds = $this->input('service_ids', []);
        if (!empty($serviceIds)) {
            $this->model->syncServices($id, (array) $serviceIds);
        }

        AuditService::log('create', 'professionals', $id);
        flash('success', 'Profissional cadastrado com sucesso!');
        redirect(url('professionals'));
    }

    public function edit(string $id): void
    {
        $this->authorize('professionals.manage');

        $professional = $this->model->find((int) $id);
        if (!$professional) {
            flash('error', 'Profissional não encontrado.');
            redirect(url('professionals'));
            return;
        }

        $services  = (new Service())->getActiveWithCategory();
        $assigned  = $this->model->getServices((int) $id);

        $this->render('professionals.form', [
            'professional' => $professional,
            'services'     => $services,
            'assigned'     => $assigned,
            'pageTitle'    => 'Editar Profissional',
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('professionals.manage');

        $professional = $this->model->find((int) $id);
        if (!$professional) {
            flash('error', 'Profissional não encontrado.');
            redirect(url('professionals'));
            return;
        }

        $errors = $this->validate([
            'name' => 'required|min:2|max:255',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $this->model->update((int) $id, [
            'name'                     => $this->input('name'),
            'email'                    => $this->input('email'),
            'phone'                    => $this->input('phone'),
            'bio'                      => $this->input('bio'),
            'color'                    => $this->input('color', '#3B82F6'),
            'commission_default_type'  => $this->input('commission_default_type', 'percentage'),
            'commission_default_value' => (float) str_replace(',', '.', $this->input('commission_default_value', '0')),
            'is_active'                => $this->input('is_active') ? 1 : 0,
            'updated_at'               => now(),
        ]);

        $this->model->syncServices((int) $id, (array) ($this->input('service_ids') ?? []));

        AuditService::log('update', 'professionals', (int) $id);
        flash('success', 'Profissional atualizado com sucesso!');
        redirect(url('professionals'));
    }

    public function destroy(string $id): void
    {
        $this->authorize('professionals.manage');
        $this->model->delete((int) $id);
        AuditService::log('delete', 'professionals', (int) $id);
        flash('success', 'Profissional removido.');
        redirect(url('professionals'));
    }

    public function toggleStatus(string $id): void
    {
        $this->authorize('professionals.manage');
        $p = $this->model->find((int) $id);
        if ($p) {
            $this->model->update((int) $id, [
                'is_active'  => $p['is_active'] ? 0 : 1,
                'updated_at' => now(),
            ]);
        }
        back();
    }

    /**
     * Exibe a grade de horários de funcionamento do profissional.
     */
    public function schedule(string $id): void
    {
        $this->authorize('professionals.manage');

        $professional = $this->model->find((int) $id);
        if (!$professional) {
            flash('error', 'Profissional não encontrado.');
            redirect(url('professionals'));
            return;
        }

        $db       = Database::getInstance();
        $tenantId = $this->tenantId();

        // Unidades do tenant
        $units = (new Unit())->all(['is_active' => 1], 'name ASC');

        // Horários existentes indexados por [unit_id][day_of_week]
        $stmt = $db->prepare(
            "SELECT * FROM professional_working_hours WHERE tenant_id = ? AND professional_id = ?"
        );
        $stmt->execute([$tenantId, (int) $id]);
        $rows = $stmt->fetchAll();

        $workingHours = [];
        foreach ($rows as $row) {
            $workingHours[$row['unit_id']][$row['day_of_week']] = $row;
        }

        // Pausas/intervalos existentes
        $stmt = $db->prepare(
            "SELECT * FROM professional_breaks WHERE tenant_id = ? AND professional_id = ? ORDER BY day_of_week, start_time"
        );
        $stmt->execute([$tenantId, (int) $id]);
        $breaks = $stmt->fetchAll();

        $this->render('professionals.schedule', [
            'pageTitle'    => 'Horários — ' . $professional['name'],
            'professional' => $professional,
            'units'        => $units,
            'workingHours' => $workingHours,
            'breaks'       => $breaks,
        ]);
    }

    /**
     * Salva horários de funcionamento do profissional.
     * Substitui todos os registros existentes (simples e confiável).
     */
    public function saveSchedule(string $id): void
    {
        $this->authorize('professionals.manage');

        $professional = $this->model->find((int) $id);
        if (!$professional) {
            flash('error', 'Profissional não encontrado.');
            redirect(url('professionals'));
            return;
        }

        $db       = Database::getInstance();
        $tenantId = $this->tenantId();

        // Remove todos os horários atuais deste profissional
        $db->prepare("DELETE FROM professional_working_hours WHERE tenant_id = ? AND professional_id = ?")
           ->execute([$tenantId, (int) $id]);

        // Re-insere os que vieram do form
        // Formato: hours[unit_id][day_of_week][start_time|end_time|is_active]
        $hours = $this->input('hours', []);

        $stmt = $db->prepare(
            "INSERT INTO professional_working_hours (tenant_id, professional_id, unit_id, day_of_week, start_time, end_time, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ((array) $hours as $unitId => $days) {
            foreach ((array) $days as $dayOfWeek => $data) {
                $isActive = !empty($data['is_active']) ? 1 : 0;
                if (!$isActive) continue; // Só salva dias ativos

                $startTime = $data['start_time'] ?? '08:00';
                $endTime   = $data['end_time']   ?? '18:00';

                if ($startTime >= $endTime) continue; // Ignora intervalo inválido

                $stmt->execute([
                    $tenantId,
                    (int) $id,
                    (int) $unitId,
                    (int) $dayOfWeek,
                    $startTime . ':00',
                    $endTime . ':00',
                    1,
                ]);
            }
        }

        AuditService::log('update_schedule', 'professionals', (int) $id);
        flash('success', 'Horários salvos com sucesso!');
        redirect(url('professionals/' . $id . '/schedule'));
    }

    /**
     * Salva pausas/intervalos do profissional.
     */
    public function saveBreaks(string $id): void
    {
        $this->authorize('professionals.manage');

        $professional = $this->model->find((int) $id);
        if (!$professional) {
            flash('error', 'Profissional não encontrado.');
            redirect(url('professionals'));
            return;
        }

        $db       = Database::getInstance();
        $tenantId = $this->tenantId();

        // Remove todas as pausas atuais
        $db->prepare("DELETE FROM professional_breaks WHERE tenant_id = ? AND professional_id = ?")
           ->execute([$tenantId, (int) $id]);

        // Re-insere as pausas do form
        // Formato: breaks[][day_of_week|start_time|end_time|description]
        $breaks = $this->input('breaks', []);

        $stmt = $db->prepare(
            "INSERT INTO professional_breaks (tenant_id, professional_id, day_of_week, start_time, end_time, description)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        foreach ((array) $breaks as $break) {
            $startTime = $break['start_time'] ?? '';
            $endTime   = $break['end_time']   ?? '';

            if (!$startTime || !$endTime || $startTime >= $endTime) continue;

            $dayOfWeek = isset($break['day_of_week']) && $break['day_of_week'] !== ''
                ? (int) $break['day_of_week']
                : null;

            $stmt->execute([
                $tenantId,
                (int) $id,
                $dayOfWeek,
                $startTime . ':00',
                $endTime . ':00',
                $break['description'] ?? null,
            ]);
        }

        flash('success', 'Pausas salvas com sucesso!');
        redirect(url('professionals/' . $id . '/schedule'));
    }
}
