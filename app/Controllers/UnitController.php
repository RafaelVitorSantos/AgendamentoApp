<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\PlanLimiter;

class UnitController extends Controller
{
    private Unit $model;

    public function __construct()
    {
        $this->model = new Unit();
    }

    public function index(): void
    {
        $this->authorize('units.view');

        $units = $this->model->getAllWithStats();

        $this->render('units.index', [
            'units'     => $units,
            'pageTitle' => 'Unidades',
        ]);
    }

    public function create(): void
    {
        $this->authorize('units.manage');

        $limiter = new PlanLimiter();
        if (!$limiter->canCreateUnit()) {
            flash('error', 'Limite de unidades do seu plano atingido. Faça upgrade.');
            redirect(url('units'));
        }

        $this->render('units.form', [
            'unit'      => null,
            'pageTitle' => 'Nova Unidade',
        ]);
    }

    public function store(): void
    {
        $this->authorize('units.manage');

        $errors = $this->validate([
            'name' => 'required|min:2|max:255',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $slug = generate_slug($this->input('name'));

        $db = \App\Core\Database::getInstance();
        $exists = $db->prepare("SELECT id FROM units WHERE tenant_id = ? AND slug = ? LIMIT 1");
        $exists->execute([$this->tenantId(), $slug]);
        if ($exists->fetch()) {
            $slug .= '-' . substr(uniqid(), -4);
        }

        $id = $this->model->create([
            'name'       => $this->input('name'),
            'slug'       => $slug,
            'phone'      => $this->input('phone'),
            'email'      => $this->input('email'),
            'address_street'       => $this->input('address_street'),
            'address_number'       => $this->input('address_number'),
            'address_complement'   => $this->input('address_complement'),
            'address_neighborhood' => $this->input('address_neighborhood'),
            'address_city'         => $this->input('address_city'),
            'address_state'        => $this->input('address_state'),
            'address_zipcode'      => $this->input('address_zipcode'),
            'timezone'   => $this->input('timezone', 'America/Sao_Paulo'),
            'is_active'  => 1,
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditService::log('create', 'units', $id);
        flash('success', 'Unidade cadastrada com sucesso!');
        redirect(url('units'));
    }

    public function edit(string $id): void
    {
        $this->authorize('units.manage');

        $unit = $this->model->find((int) $id);
        if (!$unit) {
            flash('error', 'Unidade não encontrada.');
            redirect(url('units'));
            return;
        }

        $this->render('units.form', [
            'unit'      => $unit,
            'pageTitle' => 'Editar Unidade',
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('units.manage');

        $unit = $this->model->find((int) $id);
        if (!$unit) {
            flash('error', 'Unidade não encontrada.');
            redirect(url('units'));
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
            'name'       => $this->input('name'),
            'phone'      => $this->input('phone'),
            'email'      => $this->input('email'),
            'address_street'       => $this->input('address_street'),
            'address_number'       => $this->input('address_number'),
            'address_complement'   => $this->input('address_complement'),
            'address_neighborhood' => $this->input('address_neighborhood'),
            'address_city'         => $this->input('address_city'),
            'address_state'        => $this->input('address_state'),
            'address_zipcode'      => $this->input('address_zipcode'),
            'timezone'   => $this->input('timezone', 'America/Sao_Paulo'),
            'is_active'  => $this->input('is_active') ? 1 : 0,
            'updated_at' => now(),
        ]);

        AuditService::log('update', 'units', (int) $id);
        flash('success', 'Unidade atualizada com sucesso!');
        redirect(url('units'));
    }

    public function destroy(string $id): void
    {
        $this->authorize('units.manage');
        $unit = $this->model->find((int) $id);
        if ($unit && $unit['is_default']) {
            flash('error', 'A unidade principal não pode ser removida.');
            redirect(url('units'));
            return;
        }
        $this->model->delete((int) $id);
        AuditService::log('delete', 'units', (int) $id);
        flash('success', 'Unidade removida.');
        redirect(url('units'));
    }
}
