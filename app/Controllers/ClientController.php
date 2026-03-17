<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;
use App\Services\AuditService;
use App\Services\PlanLimiter;

class ClientController extends Controller
{
    private Client $model;

    public function __construct()
    {
        $this->model = new Client();
    }

    public function index(): void
    {
        $this->authorize('clients.view');

        $search = $this->input('search', '');
        $page   = max(1, (int) $this->input('page', 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        if ($search) {
            $db = \App\Core\Database::getInstance();
            $stmt = $db->prepare(
                "SELECT * FROM clients
                 WHERE tenant_id = ? AND deleted_at IS NULL
                 AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)
                 ORDER BY name ASC LIMIT ? OFFSET ?"
            );
            $term = "%{$search}%";
            $stmt->execute([$this->tenantId(), $term, $term, $term, $limit, $offset]);
            $clients = $stmt->fetchAll();
            $total = $this->model->count();
        } else {
            $clients = $this->model->all([], 'name ASC', $limit, $offset);
            $total   = $this->model->count();
        }

        $this->render('clients.index', [
            'clients'   => $clients,
            'search'    => $search,
            'page'      => $page,
            'totalPages' => ceil($total / $limit),
            'total'     => $total,
            'pageTitle' => 'Clientes',
        ]);
    }

    public function create(): void
    {
        $this->authorize('clients.create');

        $limiter = new PlanLimiter();
        if (!$limiter->canCreateClient()) {
            flash('error', 'Limite de clientes do seu plano atingido. Faça upgrade.');
            back();
        }

        $this->render('clients.form', [
            'client'    => null,
            'pageTitle' => 'Novo Cliente',
        ]);
    }

    public function store(): void
    {
        $this->authorize('clients.create');

        $errors = $this->validate([
            'name'  => 'required|min:2|max:255',
            'phone' => 'required|min:10',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $id = $this->model->create([
            'name'            => $this->input('name'),
            'email'           => $this->input('email'),
            'phone'           => $this->input('phone'),
            'phone_whatsapp'  => $this->input('phone_whatsapp') ?: $this->input('phone'),
            'document_number' => $this->input('document_number'),
            'birth_date'      => $this->input('birth_date') ?: null,
            'gender'          => $this->input('gender') ?: 'N',
            'notes'           => $this->input('notes'),
            'source'          => $this->input('source'),
            'lgpd_consent'    => $this->input('lgpd_consent') ? 1 : 0,
            'lgpd_consent_at' => $this->input('lgpd_consent') ? now() : null,
        ]);

        AuditService::log('create', 'clients', $id);
        flash('success', 'Cliente cadastrado com sucesso!');
        redirect(url('clients'));
    }

    public function edit(string $id): void
    {
        $this->authorize('clients.edit');

        $client = $this->model->find((int) $id);
        if (!$client) {
            flash('error', 'Cliente não encontrado.');
            redirect(url('clients'));
            return;
        }

        $this->render('clients.form', [
            'client'    => $client,
            'pageTitle' => 'Editar Cliente',
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('clients.edit');

        $client = $this->model->find((int) $id);
        if (!$client) {
            flash('error', 'Cliente não encontrado.');
            redirect(url('clients'));
            return;
        }

        $errors = $this->validate([
            'name'  => 'required|min:2|max:255',
            'phone' => 'required|min:10',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $oldData = $client;

        $this->model->update((int) $id, [
            'name'            => $this->input('name'),
            'email'           => $this->input('email'),
            'phone'           => $this->input('phone'),
            'phone_whatsapp'  => $this->input('phone_whatsapp') ?: $this->input('phone'),
            'document_number' => $this->input('document_number'),
            'birth_date'      => $this->input('birth_date') ?: null,
            'gender'          => $this->input('gender') ?: 'N',
            'notes'           => $this->input('notes'),
        ]);

        AuditService::log('update', 'clients', (int) $id, $oldData);
        flash('success', 'Cliente atualizado com sucesso!');
        redirect(url('clients'));
    }

    public function show(string $id): void
    {
        $this->authorize('clients.view');

        $client = $this->model->find((int) $id);
        if (!$client) {
            flash('error', 'Cliente não encontrado.');
            redirect(url('clients'));
            return;
        }

        // Histórico de atendimentos
        $db = \App\Core\Database::getInstance();
        $stmt = $db->prepare(
            "SELECT a.*, s.name as service_name, p.name as professional_name
             FROM appointments a
             JOIN services s ON s.id = a.service_id
             JOIN professionals p ON p.id = a.professional_id
             WHERE a.tenant_id = ? AND a.client_id = ?
             ORDER BY a.date DESC, a.start_time DESC LIMIT 50"
        );
        $stmt->execute([$this->tenantId(), (int) $id]);
        $history = $stmt->fetchAll();

        $this->render('clients.show', [
            'client'    => $client,
            'history'   => $history,
            'pageTitle' => $client['name'],
        ]);
    }

    public function destroy(string $id): void
    {
        $this->authorize('clients.delete');
        $this->model->delete((int) $id);
        AuditService::log('delete', 'clients', (int) $id);
        flash('success', 'Cliente removido.');
        redirect(url('clients'));
    }
}
