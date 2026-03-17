<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Service;
use App\Services\AuditService;

class ServiceController extends Controller
{
    private Service $model;

    public function __construct()
    {
        $this->model = new Service();
    }

    public function index(): void
    {
        $this->authorize('services.view');

        $services   = $this->model->getAllWithCategory();
        $categories = $this->model->getCategories();

        $grouped = [];
        foreach ($services as $s) {
            $cat = $s['category_name'] ?? 'Sem categoria';
            $grouped[$cat][] = $s;
        }

        $this->render('services.index', [
            'grouped'    => $grouped,
            'categories' => $categories,
            'total'      => count($services),
            'pageTitle'  => 'Serviços',
        ]);
    }

    public function create(): void
    {
        $this->authorize('services.manage');
        $this->render('services.form', [
            'service'    => null,
            'categories' => $this->model->getCategories(),
            'pageTitle'  => 'Novo Serviço',
        ]);
    }

    public function store(): void
    {
        $this->authorize('services.manage');

        $errors = $this->validate([
            'name'             => 'required|min:2|max:255',
            'duration_minutes' => 'required|numeric',
            'price'            => 'required',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $categoryId = $this->resolveCategory($this->input('category_id'));

        $id = $this->model->create([
            'category_id'          => $categoryId,
            'name'                 => $this->input('name'),
            'description'          => $this->input('description'),
            'duration_minutes'     => (int) $this->input('duration_minutes'),
            'price'                => (float) str_replace(',', '.', $this->input('price')),
            'commission_type'      => $this->input('commission_type', 'percentage'),
            'commission_value'     => (float) str_replace(',', '.', $this->input('commission_value', '0')),
            'is_online_booking'    => $this->input('is_online_booking') ? 1 : 0,
            'requires_professional'=> $this->input('requires_professional') ? 1 : 0,
            'is_active'            => 1,
            'color'                => $this->input('color', '#6366F1'),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        AuditService::log('create', 'services', $id);
        flash('success', 'Serviço cadastrado com sucesso!');
        redirect(url('services'));
    }

    public function edit(string $id): void
    {
        $this->authorize('services.manage');

        $service = $this->model->find((int) $id);
        if (!$service) {
            flash('error', 'Serviço não encontrado.');
            redirect(url('services'));
            return;
        }

        $this->render('services.form', [
            'service'    => $service,
            'categories' => $this->model->getCategories(),
            'pageTitle'  => 'Editar Serviço',
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('services.manage');

        $service = $this->model->find((int) $id);
        if (!$service) {
            flash('error', 'Serviço não encontrado.');
            redirect(url('services'));
            return;
        }

        $errors = $this->validate([
            'name'             => 'required|min:2|max:255',
            'duration_minutes' => 'required|numeric',
            'price'            => 'required',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $categoryId = $this->resolveCategory($this->input('category_id'));

        $this->model->update((int) $id, [
            'category_id'          => $categoryId ?: null,
            'name'                 => $this->input('name'),
            'description'          => $this->input('description'),
            'duration_minutes'     => (int) $this->input('duration_minutes'),
            'price'                => (float) str_replace(',', '.', $this->input('price')),
            'commission_type'      => $this->input('commission_type', 'percentage'),
            'commission_value'     => (float) str_replace(',', '.', $this->input('commission_value', '0')),
            'is_online_booking'    => $this->input('is_online_booking') ? 1 : 0,
            'requires_professional'=> $this->input('requires_professional') ? 1 : 0,
            'is_active'            => $this->input('is_active') ? 1 : 0,
            'color'                => $this->input('color', '#6366F1'),
            'updated_at'           => now(),
        ]);

        AuditService::log('update', 'services', (int) $id);
        flash('success', 'Serviço atualizado com sucesso!');
        redirect(url('services'));
    }

    public function destroy(string $id): void
    {
        $this->authorize('services.manage');
        $this->model->delete((int) $id);
        AuditService::log('delete', 'services', (int) $id);
        flash('success', 'Serviço removido.');
        redirect(url('services'));
    }

    /**
     * Resolve o category_id vindo do form:
     * - "0" ou vazio → null (sem categoria)
     * - "-1" → cria nova categoria se informou nome, senão null
     * - qualquer int válido → usa diretamente
     */
    private function resolveCategory(mixed $raw): ?int
    {
        if ($raw === '-1' || (int) $raw === -1) {
            $newName = trim((string) $this->input('new_category'));
            return $newName ? $this->model->createCategory($newName) : null;
        }
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    public function toggleStatus(string $id): void
    {
        $this->authorize('services.manage');
        $service = $this->model->find((int) $id);
        if ($service) {
            $this->model->update((int) $id, [
                'is_active'  => $service['is_active'] ? 0 : 1,
                'updated_at' => now(),
            ]);
        }
        back();
    }
}
