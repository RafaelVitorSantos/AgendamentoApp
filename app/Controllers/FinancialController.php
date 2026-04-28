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

    /**
     * Confirma o recebimento de um lançamento pendente.
     * POST /financial/{id}/confirm
     */
    public function confirm(string $id): void
    {
        $this->authorize('financial.create');

        $tx = $this->model->find((int) $id);
        if (!$tx || $tx['status'] !== 'pending') {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Lançamento não encontrado ou já confirmado.'], 422);
                return;
            }
            flash('error', 'Lançamento não encontrado ou já confirmado.');
            redirect(url('financial'));
            return;
        }

        $paymentMethod = $this->input('payment_method') ?: 'cash';

        $this->model->update((int) $id, [
            'status'         => 'paid',
            'payment_method' => $paymentMethod,
            'paid_at'        => now(),
            'updated_at'     => now(),
        ]);

        AuditService::log('confirm_payment', 'financial_transactions', (int) $id, ['status' => 'pending'], ['status' => 'paid', 'payment_method' => $paymentMethod]);

        if ($this->isAjax()) {
            $this->json(['success' => true]);
            return;
        }

        flash('success', 'Pagamento confirmado!');
        back();
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

    private function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
