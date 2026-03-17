<?php
$stats   = $todayStats ?? [];
$revenue = $todayRevenue ?? [];
$rate    = $occupancyRate ?? 0;
$usage   = $usage ?? [];
?>

<!-- Cards de estatísticas -->
<div class="grid grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
    <!-- Agendamentos do dia -->
    <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm border border-gray-100 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 sm:h-12 sm:w-12">
                <svg class="h-5 w-5 text-blue-600 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 sm:text-sm">Agendamentos</p>
                <p class="text-xl font-bold text-gray-900 sm:text-2xl"><?= (int)($stats['total_appointments'] ?? 0) ?></p>
            </div>
        </div>
        <div class="mt-3 flex gap-2 text-xs">
            <span class="text-green-600"><?= (int)($stats['completed'] ?? 0) ?> finalizados</span>
            <span class="text-gray-400">|</span>
            <span class="text-yellow-600"><?= (int)($stats['pending'] ?? 0) ?> pendentes</span>
        </div>
    </div>

    <!-- Faturamento do dia -->
    <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm border border-gray-100 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 sm:h-12 sm:w-12">
                <svg class="h-5 w-5 text-green-600 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 sm:text-sm">Faturamento</p>
                <p class="text-xl font-bold text-gray-900 sm:text-2xl"><?= format_money((float)($revenue['income'] ?? 0)) ?></p>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
            Despesas: <?= format_money((float)($revenue['expense'] ?? 0)) ?>
        </div>
    </div>

    <!-- Taxa de ocupação -->
    <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm border border-gray-100 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 sm:h-12 sm:w-12">
                <svg class="h-5 w-5 text-purple-600 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 sm:text-sm">Ocupação</p>
                <p class="text-xl font-bold text-gray-900 sm:text-2xl"><?= $rate ?>%</p>
            </div>
        </div>
        <div class="mt-3">
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-purple-600 h-2 rounded-full transition-all" style="width: <?= min($rate, 100) ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Faltas -->
    <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm border border-gray-100 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 sm:h-12 sm:w-12">
                <svg class="h-5 w-5 text-red-600 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 sm:text-sm">Faltas</p>
                <p class="text-xl font-bold text-gray-900 sm:text-2xl"><?= (int)($stats['no_shows'] ?? 0) ?></p>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
            <?= (int)($stats['cancelled'] ?? 0) ?> cancelamentos hoje
        </div>
    </div>
</div>

<!-- Seção principal: próximos agendamentos + sidebar -->
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

    <!-- Próximos agendamentos (2/3 da largura) -->
    <div class="lg:col-span-2">
        <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
            <div class="flex items-center justify-between px-4 py-4 sm:px-6 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Próximos Agendamentos</h2>
                <a href="<?= url('appointments') ?>" class="text-sm font-medium text-brand-600 hover:text-brand-500">Ver todos</a>
            </div>

            <?php if (!empty($upcoming)): ?>
                <ul role="list" class="divide-y divide-gray-100">
                    <?php foreach ($upcoming as $appt): ?>
                        <li class="flex items-center gap-x-4 px-4 py-3 sm:px-6 hover:bg-gray-50 transition-colors">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white text-xs font-bold"
                                 style="background-color: <?= e($appt['professional_color'] ?? '#6366F1') ?>">
                                <?= e(strtoupper(substr($appt['professional_name'] ?? 'P', 0, 2))) ?>
                            </div>
                            <div class="min-w-0 flex-auto">
                                <p class="text-sm font-semibold text-gray-900"><?= e($appt['client_name'] ?? 'Walk-in') ?></p>
                                <p class="text-xs text-gray-500"><?= e($appt['service_name']) ?> com <?= e($appt['professional_name']) ?></p>
                            </div>
                            <div class="flex flex-col items-end">
                                <p class="text-sm font-medium text-gray-900"><?= substr($appt['start_time'], 0, 5) ?></p>
                                <?php
                                $statusLabels = ['scheduled' => 'Agendado', 'confirmed' => 'Confirmado', 'in_progress' => 'Atendendo'];
                                $statusColors = ['scheduled' => 'bg-yellow-100 text-yellow-700', 'confirmed' => 'bg-blue-100 text-blue-700', 'in_progress' => 'bg-purple-100 text-purple-700'];
                                $st = $appt['status'];
                                ?>
                                <span class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium <?= $statusColors[$st] ?? 'bg-gray-100 text-gray-700' ?>">
                                    <?= $statusLabels[$st] ?? $st ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="px-4 py-12 text-center sm:px-6">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">Sem agendamentos</h3>
                    <p class="mt-1 text-sm text-gray-500">Nenhum agendamento restante para hoje.</p>
                    <div class="mt-4">
                        <a href="<?= url('appointments/create') ?>" class="inline-flex items-center rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-500">
                            <svg class="-ml-0.5 mr-1.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a.75.75 0 0 1 .75.75v5.5h5.5a.75.75 0 0 1 0 1.5h-5.5v5.5a.75.75 0 0 1-1.5 0v-5.5h-5.5a.75.75 0 0 1 0-1.5h5.5v-5.5A.75.75 0 0 1 10 3Z"/></svg>
                            Novo Agendamento
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar direita (1/3) -->
    <div class="space-y-6">
        <!-- Top serviços do mês -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
            <div class="px-4 py-4 sm:px-6 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Top Serviços (mês)</h2>
            </div>
            <?php if (!empty($topServices)): ?>
                <ul class="divide-y divide-gray-100">
                    <?php
                    $maxTotal = max(array_column($topServices, 'total'));
                    foreach ($topServices as $i => $srv):
                        $pct = $maxTotal > 0 ? ($srv['total'] / $maxTotal) * 100 : 0;
                    ?>
                        <li class="px-4 py-3 sm:px-6">
                            <div class="flex justify-between text-sm">
                                <span class="font-medium text-gray-900"><?= e($srv['name']) ?></span>
                                <span class="text-gray-500"><?= (int) $srv['total'] ?>x</span>
                            </div>
                            <div class="mt-1.5 w-full bg-gray-100 rounded-full h-1.5">
                                <div class="bg-brand-500 h-1.5 rounded-full" style="width: <?= $pct ?>%"></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="px-4 py-6 text-center text-sm text-gray-500">Sem dados ainda</p>
            <?php endif; ?>
        </div>

        <!-- Alertas -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
            <div class="px-4 py-4 sm:px-6 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Alertas</h2>
            </div>
            <div class="px-4 py-3 sm:px-6 space-y-3">
                <?php if (($pendingCount ?? 0) > 0): ?>
                    <div class="flex items-start gap-2">
                        <svg class="h-5 w-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                        <p class="text-sm text-gray-700"><strong><?= $pendingCount ?></strong> confirmações pendentes</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($birthdays)): ?>
                    <?php foreach ($birthdays as $bd): ?>
                        <div class="flex items-start gap-2">
                            <span class="text-lg">&#127874;</span>
                            <p class="text-sm text-gray-700"><strong><?= e($bd['name']) ?></strong> faz aniversário hoje!</p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (isset($usage['plan_slug']) && $usage['plan_slug'] === 'free'): ?>
                    <div class="flex items-start gap-2">
                        <svg class="h-5 w-5 text-brand-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a.75.75 0 0 1 .75.75v.258a33.186 33.186 0 0 1 6.668.83.75.75 0 0 1-.336 1.461 31.28 31.28 0 0 0-1.103-.232l1.702 7.545a.75.75 0 0 1-.387.832A4.981 4.981 0 0 1 15 14c-.825 0-1.606-.2-2.294-.556a.75.75 0 0 1-.387-.832l1.77-7.849a31.743 31.743 0 0 0-3.339-.364v11.851h1.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5h1.5V5.399a31.753 31.753 0 0 0-3.339.364l1.77 7.849a.75.75 0 0 1-.387.832A4.981 4.981 0 0 1 5 14c-.825 0-1.606-.2-2.294-.556a.75.75 0 0 1-.387-.832l1.702-7.545c-.372.06-.742.13-1.103.232a.75.75 0 0 1-.336-1.462 33.186 33.186 0 0 1 6.668-.829V2.75A.75.75 0 0 1 10 2Z" clip-rule="evenodd"/></svg>
                        <p class="text-sm text-gray-700">Plano Gratuito. <a href="<?= url('settings/billing') ?>" class="font-semibold text-brand-600">Faça upgrade</a></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($pendingCount) && empty($birthdays)): ?>
                    <p class="text-sm text-gray-500">Nenhum alerta no momento.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
