<?php
$overview         = $overview ?? [];
$topServices      = $topServices ?? [];
$topProfessionals = $topProfessionals ?? [];
$topClients       = $topClients ?? [];
$byDay            = $byDay ?? [];
$busyHours        = $busyHours ?? [];
$period           = $period ?? 'month';
$dateFrom         = $dateFrom ?? date('Y-m-01');
$dateTo           = $dateTo ?? date('Y-m-d');

$total      = (int)($overview['total_appointments'] ?? 0);
$completed  = (int)($overview['completed'] ?? 0);
$cancelled  = (int)($overview['cancelled'] ?? 0);
$noShows    = (int)($overview['no_shows'] ?? 0);
$revenue    = (float)($overview['revenue'] ?? 0);
$completion = $total > 0 ? round($completed / $total * 100, 1) : 0;
$noShowRate = $total > 0 ? round($noShows / $total * 100, 1) : 0;

// Dados para o gráfico de linha (atendimentos por dia)
$dayLabels    = array_map(fn($r) => date('d/m', strtotime($r['date'])), $byDay);
$dayCompleted = array_map(fn($r) => (int)$r['completed'], $byDay);
$dayTotal     = array_map(fn($r) => (int)$r['total'], $byDay);

$periodLabels = [
    'week'       => 'Esta semana',
    'month'      => 'Este mês',
    'last_month' => 'Mês passado',
    'quarter'    => 'Últimos 3 meses',
    'year'       => 'Este ano',
];
?>

<div class="space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Relatórios</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                <?= e(format_date($dateFrom)) ?> a <?= e(format_date($dateTo)) ?>
                · <?= e($periodLabels[$period] ?? $period) ?>
            </p>
        </div>
        <form method="get" action="<?= url('reports') ?>">
            <select name="period" onchange="this.form.submit()"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                <?php foreach ($periodLabels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $period === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500">Total de agend.</p>
            <p class="mt-1 text-2xl font-bold text-gray-900"><?= $total ?></p>
            <p class="mt-0.5 text-xs text-gray-400"><?= $completed ?> concluídos</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500">Faturamento</p>
            <p class="mt-1 text-2xl font-bold text-green-600"><?= format_money($revenue) ?></p>
            <p class="mt-0.5 text-xs text-gray-400">atendimentos concluídos</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500">Taxa de conclusão</p>
            <p class="mt-1 text-2xl font-bold text-brand-600"><?= $completion ?>%</p>
            <div class="mt-1 w-full bg-gray-100 rounded-full h-1.5">
                <div class="bg-brand-500 h-1.5 rounded-full" style="width: <?= min($completion, 100) ?>%"></div>
            </div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <p class="text-xs font-medium text-gray-500">Taxa de faltas</p>
            <p class="mt-1 text-2xl font-bold <?= $noShowRate > 15 ? 'text-red-600' : 'text-gray-900' ?>"><?= $noShowRate ?>%</p>
            <p class="mt-0.5 text-xs text-gray-400"><?= $noShows ?> faltas · <?= $cancelled ?> cancelamentos</p>
        </div>
    </div>

    <!-- Gráfico de atendimentos por dia -->
    <?php if (!empty($byDay)): ?>
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Atendimentos por dia</h2>
            <canvas id="dailyChart" height="80"></canvas>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <!-- Top Serviços -->
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Top Serviços</h2>
            <?php if (!empty($topServices)): ?>
                <?php $maxSvc = max(array_column($topServices, 'total')); ?>
                <div class="space-y-3">
                    <?php foreach ($topServices as $i => $s): ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-800"><?= e($s['name']) ?></span>
                                <span class="text-gray-500"><?= (int)$s['total'] ?>x · <?= format_money((float)($s['revenue'] ?? 0)) ?></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full bg-brand-500" style="width: <?= $maxSvc > 0 ? round($s['total']/$maxSvc*100) : 0 ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-4">Sem dados para este período</p>
            <?php endif; ?>
        </div>

        <!-- Top Profissionais -->
        <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Top Profissionais</h2>
            <?php if (!empty($topProfessionals)): ?>
                <?php $maxProf = max(array_column($topProfessionals, 'total')); ?>
                <div class="space-y-3">
                    <?php foreach ($topProfessionals as $p): ?>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-medium text-gray-800"><?= e($p['name']) ?></span>
                                <span class="text-gray-500"><?= (int)$p['total'] ?> atend. · <?= format_money((float)($p['revenue'] ?? 0)) ?></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="h-2 rounded-full bg-purple-500" style="width: <?= $maxProf > 0 ? round($p['total']/$maxProf*100) : 0 ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-400 text-center py-4">Sem dados para este período</p>
            <?php endif; ?>
        </div>

        <!-- Horários mais movimentados -->
        <?php if (!empty($busyHours)): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Horários mais movimentados</h2>
                <?php $maxH = max(array_column($busyHours, 'total')); ?>
                <div class="space-y-2">
                    <?php foreach ($busyHours as $h): ?>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-gray-600 w-12 text-right"><?= str_pad($h['hour'], 2, '0', STR_PAD_LEFT) ?>h</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                                <div class="h-4 rounded-full bg-amber-400 flex items-center justify-end pr-1.5"
                                     style="width: <?= $maxH > 0 ? round($h['total']/$maxH*100) : 0 ?>%">
                                    <span class="text-[10px] font-bold text-amber-900"><?= (int)$h['total'] ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Top Clientes -->
        <?php if (!empty($topClients)): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Clientes mais frequentes</h2>
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($topClients as $i => $c): ?>
                        <li class="py-2.5 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600"><?= $i + 1 ?></span>
                                <span class="text-sm font-medium text-gray-800"><?= e($c['name']) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-semibold text-gray-900"><?= format_money((float)($c['spent'] ?? 0)) ?></span>
                                <span class="ml-2 text-xs text-gray-500"><?= (int)$c['visits'] ?> visitas</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($byDay)): ?>
<?php $footerScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
new Chart(document.getElementById("dailyChart"), {
  type: "line",
  data: {
    labels: ' . json_encode($dayLabels) . ',
    datasets: [
      { label: "Agendamentos", data: ' . json_encode($dayTotal) . ', borderColor: "#818CF8", backgroundColor: "rgba(129,140,248,0.1)", fill: true, tension: 0.3, pointRadius: 3 },
      { label: "Concluídos",   data: ' . json_encode($dayCompleted) . ', borderColor: "#22C55E", backgroundColor: "rgba(34,197,94,0.08)", fill: true, tension: 0.3, pointRadius: 3 }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: "bottom" } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});
</script>'; ?>
<?php endif; ?>
