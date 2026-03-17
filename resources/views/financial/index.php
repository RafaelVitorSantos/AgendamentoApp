<?php
$transactions = $transactions ?? [];
$summary      = $summary ?? ['income' => 0, 'expense' => 0, 'balance' => 0, 'count_income' => 0, 'count_expense' => 0];
$chartData    = $chartData ?? [];
$type         = $type ?? '';
$month        = $month ?? date('Y-m');
$page         = (int) ($page ?? 1);
$totalPages   = max(1, (int) ($totalPages ?? 1));
$total        = (int) ($total ?? 0);

$methodLabels = [
    'cash'        => 'Dinheiro',
    'credit_card' => 'Crédito',
    'debit_card'  => 'Débito',
    'pix'         => 'PIX',
    'transfer'    => 'Transferência',
    'other'       => 'Outro',
];
$statusLabels = [
    'paid'      => 'Pago',
    'pending'   => 'Pendente',
    'cancelled' => 'Cancelado',
    'refunded'  => 'Estornado',
];
$statusColors = [
    'paid'      => 'bg-green-100 text-green-700',
    'pending'   => 'bg-yellow-100 text-yellow-700',
    'cancelled' => 'bg-gray-100 text-gray-600',
    'refunded'  => 'bg-red-100 text-red-700',
];

// Prepara dados do gráfico
$chartMonths  = array_column($chartData, 'month');
$chartIncome  = array_column($chartData, 'income');
$chartExpense = array_column($chartData, 'expense');
$chartLabels  = array_map(fn($m) => date('M/y', strtotime($m . '-01')), $chartMonths);
?>

<div class="space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Financeiro</h1>
        <a href="<?= url('financial/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Novo Lançamento
        </a>
    </div>

    <!-- Cards de resumo -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50">
                    <svg class="h-5 w-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.519l2.74-1.22m0 0-5.94-2.28m5.94 2.28-2.28 5.941"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Entradas</p>
                    <p class="text-xl font-bold text-green-600"><?= format_money($summary['income']) ?></p>
                    <p class="text-xs text-gray-400"><?= $summary['count_income'] ?> lançamento<?= $summary['count_income'] !== 1 ? 's' : '' ?></p>
                </div>
            </div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50">
                    <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Saídas</p>
                    <p class="text-xl font-bold text-red-600"><?= format_money($summary['expense']) ?></p>
                    <p class="text-xs text-gray-400"><?= $summary['count_expense'] ?> lançamento<?= $summary['count_expense'] !== 1 ? 's' : '' ?></p>
                </div>
            </div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg <?= $summary['balance'] >= 0 ? 'bg-blue-50' : 'bg-orange-50' ?>">
                    <svg class="h-5 w-5 <?= $summary['balance'] >= 0 ? 'text-blue-600' : 'text-orange-600' ?>" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-500">Saldo</p>
                    <p class="text-xl font-bold <?= $summary['balance'] >= 0 ? 'text-blue-600' : 'text-orange-600' ?>"><?= format_money($summary['balance']) ?></p>
                    <p class="text-xs text-gray-400"><?= date('M/Y', strtotime($month . '-01')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico mensal + Filtros -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <?php if (!empty($chartData)): ?>
        <div class="lg:col-span-2 rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Fluxo dos últimos 6 meses</h2>
            <canvas id="finChart" height="100"></canvas>
        </div>
        <?php endif; ?>

        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100 flex flex-col gap-4">
            <h2 class="text-sm font-semibold text-gray-700">Filtros</h2>
            <form method="get" action="<?= url('financial') ?>" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Mês</label>
                    <input type="month" name="month" value="<?= e($month) ?>"
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                    <select name="type" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="" <?= $type === '' ? 'selected' : '' ?>>Todos</option>
                        <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Entradas</option>
                        <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Saídas</option>
                    </select>
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Filtrar</button>
            </form>
        </div>
    </div>

    <!-- Tabela de lançamentos -->
    <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
        <div class="px-4 py-3 sm:px-6 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Lançamentos</h2>
            <span class="text-xs text-gray-500"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
        </div>
        <?php if (!empty($transactions)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Categoria</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Forma</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($transactions as $t): ?>
                            <tr class="hover:bg-gray-50/40">
                                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap"><?= e(format_date($t['reference_date'])) ?></td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <?= e($t['description']) ?>
                                    <span class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium <?= $t['type'] === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= $t['type'] === 'income' ? 'Entrada' : 'Saída' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 hidden sm:table-cell"><?= e($t['category_name'] ?? '—') ?></td>
                                <td class="px-4 py-3 text-sm text-gray-500 hidden sm:table-cell"><?= e($methodLabels[$t['payment_method'] ?? ''] ?? '—') ?></td>
                                <td class="px-4 py-3 hidden md:table-cell">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium <?= $statusColors[$t['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                        <?= $statusLabels[$t['status']] ?? e($t['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold whitespace-nowrap <?= $t['type'] === 'income' ? 'text-green-700' : 'text-red-700' ?>">
                                    <?= $t['type'] === 'income' ? '+' : '-' ?><?= format_money((float) $t['amount']) ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <?php if ($t['status'] !== 'cancelled'): ?>
                                        <form method="post" action="<?= url('financial/' . $t['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('Cancelar este lançamento?');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-500">Cancelar</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">Cancelado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 sm:px-6">
                    <p class="text-sm text-gray-500"><?= $total ?> lançamentos</p>
                    <nav class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="<?= url('financial') ?>?page=<?= $page - 1 ?>&month=<?= $month ?>&type=<?= $type ?>" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Anterior</a>
                        <?php endif; ?>
                        <span class="rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700"><?= $page ?> / <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= url('financial') ?>?page=<?= $page + 1 ?>&month=<?= $month ?>&type=<?= $type ?>" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Próxima</a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="px-4 py-12 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
                <p class="mt-2 text-sm text-gray-500">Nenhum lançamento para este período.</p>
                <a href="<?= url('financial/create') ?>" class="mt-3 inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Novo Lançamento</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($chartData)): ?>
<?php $footerScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
const ctx = document.getElementById("finChart");
new Chart(ctx, {
  type: "bar",
  data: {
    labels: ' . json_encode($chartLabels) . ',
    datasets: [
      { label: "Entradas", data: ' . json_encode(array_map('floatval', $chartIncome)) . ', backgroundColor: "rgba(34,197,94,0.7)", borderRadius: 4 },
      { label: "Saídas",   data: ' . json_encode(array_map('floatval', $chartExpense)) . ', backgroundColor: "rgba(239,68,68,0.7)", borderRadius: 4 }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: "bottom" } },
    scales: {
      y: { ticks: { callback: v => "R$ " + v.toLocaleString("pt-BR") } }
    }
  }
});
</script>'; ?>
<?php endif; ?>
