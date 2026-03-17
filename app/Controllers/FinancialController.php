<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FinancialTransaction;
use App\Services\AuditService;

class FinancialController extends Controller
{
    private FinancialTransaction $model;

    public function __construct()
    {
        $this->model = new FinancialTransaction();
    }

    public function index(): void
    {
        $this->authorize('financial.view');

        $type  = $this->input('type', '');
        $month = $this->input('month', date('Y-m'));
        $page  = max(1, (int) $this->input('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $transactions = $this->model->getFiltered($type, $month, $limit, $offset);
        $total        = $this->model->countFiltered($type, $month);
        $summary      = $this->model->getSummary($month);
        $chartData    = $this->model->getMonthlyChart(6);

        $this->render('financial.index', [
            'transactions' => $transactions,
            'summary'      => $summary,
            'chartData'    => $chartData,
            'type'         => $type,
            'month'        => $month,
            'page'         => $page,
            'totalPages'   => max(1, (int) ceil($total / $limit)),
            'total'        => $total,
            'pageTitle'    => 'Financeiro',
        ]);
    }

    public function create(): void
    {
        $this->authorize('financial.create');

        $this->render('financial.form', [
            'transaction' => null,
            'categories'  => $this->model->getCategories(),
            'pageTitle'   => 'Novo Lançamento',
        ]);
    }

    public function store(): void
    {
        $this->authorize('financial.create');

        $errors = $this->validate([
            'type'           => 'required',
            'description'    => 'required|min:2|max:255',
            'amount'         => 'required',
            'reference_date' => 'required',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $id = $this->model->create([
            'type'           => $this->input('type'),
            'description'    => $this->input('description'),
            'amount'         => (float) str_replace(',', '.', $this->input('amount')),
            'category_id'    => $this->input('category_id') ?: null,
            'payment_method' => $this->input('payment_method') ?: null,
            'status'         => $this->input('status', 'paid'),
            'reference_date' => $this->input('reference_date'),
            'due_date'       => $this->input('due_date') ?: null,
            'paid_at'        => $this->input('status') === 'paid' ? now() : null,
            'notes'          => $this->input('notes'),
            'created_by'     => $this->userId(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        AuditService::log('create', 'financial_transactions', $id);
        flash('success', 'Lançamento registrado com sucesso!');
        redirect(url('financial?month=' . date('Y-m', strtotime($this->input('reference_date')))));
    }

    public function destroy(string $id): void
    {
        $this->authorize('financial.create');

        $tx = $this->model->find((int) $id);
        if (!$tx) {
            flash('error', 'Lançamento não encontrado.');
            redirect(url('financial'));
            return;
        }

        $this->model->update((int) $id, ['status' => 'cancelled', 'updated_at' => now()]);
        AuditService::log('delete', 'financial_transactions', (int) $id);
        flash('success', 'Lançamento cancelado.');
        back();
    }
}
