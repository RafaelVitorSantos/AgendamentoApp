<?php
$transaction = $transaction ?? null;
$categories  = $categories ?? [];
$pageTitle   = $pageTitle ?? 'Novo Lançamento';
$errors      = get_flash('errors') ?? [];

$incomeCategories  = array_filter($categories, fn($c) => $c['type'] === 'income');
$expenseCategories = array_filter($categories, fn($c) => $c['type'] === 'expense');
?>

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="<?= url('financial') ?>" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
    </div>

    <form method="post" action="<?= url('financial') ?>" class="space-y-6 max-w-2xl" x-data="{ type: 'income' }">
        <?= csrf_field() ?>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">

            <!-- Tipo: Entrada / Saída -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 rounded-lg border-2 p-3 cursor-pointer transition-colors"
                           :class="type === 'income' ? 'border-green-400 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="type" value="income" x-model="type" class="sr-only" checked>
                        <div class="flex h-8 w-8 items-center justify-center rounded-full" :class="type === 'income' ? 'bg-green-100' : 'bg-gray-100'">
                            <svg class="h-4 w-4" :class="type === 'income' ? 'text-green-600' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.519l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.941"/></svg>
                        </div>
                        <span class="text-sm font-medium" :class="type === 'income' ? 'text-green-700' : 'text-gray-600'">Entrada</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border-2 p-3 cursor-pointer transition-colors"
                           :class="type === 'expense' ? 'border-red-400 bg-red-50' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="type" value="expense" x-model="type" class="sr-only">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full" :class="type === 'expense' ? 'bg-red-100' : 'bg-gray-100'">
                            <svg class="h-4 w-4" :class="type === 'expense' ? 'text-red-600' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181"/></svg>
                        </div>
                        <span class="text-sm font-medium" :class="type === 'expense' ? 'text-red-700' : 'text-gray-600'">Saída</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700">Descrição <span class="text-red-500">*</span></label>
                    <input type="text" name="description" id="description" required
                           value="<?= e(get_flash('old_description', '')) ?>"
                           placeholder="Ex: Pagamento serviço, Aluguel, etc."
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['description']) ? 'border-red-400' : '' ?>">
                    <?php if (isset($errors['description'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['description']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700">Valor <span class="text-red-500">*</span></label>
                    <div class="mt-1 flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">R$</span>
                        <input type="number" name="amount" id="amount" required min="0.01" step="0.01"
                               value="<?= e(get_flash('old_amount', '')) ?>"
                               class="block w-full rounded-r-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['amount']) ? 'border-red-400' : '' ?>">
                    </div>
                    <?php if (isset($errors['amount'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['amount']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="reference_date" class="block text-sm font-medium text-gray-700">Data de competência <span class="text-red-500">*</span></label>
                    <input type="date" name="reference_date" id="reference_date" required
                           value="<?= e(get_flash('old_reference_date', date('Y-m-d'))) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700">Categoria</label>
                    <select name="category_id" id="category_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Sem categoria</option>
                        <optgroup label="Entradas" x-show="type === 'income'">
                            <?php foreach ($incomeCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Saídas" x-show="type === 'expense'">
                            <?php foreach ($expenseCategories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700">Forma de pagamento</label>
                    <select name="payment_method" id="payment_method" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Não informado</option>
                        <option value="cash">Dinheiro</option>
                        <option value="credit_card">Cartão de Crédito</option>
                        <option value="debit_card">Cartão de Débito</option>
                        <option value="pix">PIX</option>
                        <option value="transfer">Transferência</option>
                        <option value="other">Outro</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="paid">Pago</option>
                        <option value="pending">Pendente</option>
                    </select>
                </div>

                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700">Vencimento</label>
                    <input type="date" name="due_date" id="due_date"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
                <textarea name="notes" id="notes" rows="2"
                          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">Registrar</button>
            <a href="<?= url('financial') ?>" class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>
