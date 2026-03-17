<?php
$blocks = $blocks ?? [];
?>

<div class="space-y-4">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Bloqueios de Horário</h1>
            <p class="mt-1 text-sm text-gray-500">Bloqueia períodos para que não apareçam como disponíveis no agendamento.</p>
        </div>
        <a href="<?= url('schedule-blocks/create') ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
            <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Novo Bloqueio
        </a>
    </div>

    <!-- Lista de bloqueios -->
    <?php if (!empty($blocks)): ?>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Título</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Início</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fim</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Profissional</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Unidade</th>
                        <th class="relative px-6 py-3"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach ($blocks as $block): ?>
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full bg-gray-700 flex-shrink-0"></span>
                                    <span class="text-sm font-medium text-gray-900"><?= e($block['title']) ?></span>
                                    <?php if ($block['is_all_day']): ?>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Dia todo</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($block['notes']): ?>
                                    <p class="mt-0.5 text-xs text-gray-500"><?= e($block['notes']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?= date('d/m/Y', strtotime($block['start_datetime'])) ?>
                                <?php if (!$block['is_all_day']): ?>
                                    <span class="text-gray-500"><?= date('H:i', strtotime($block['start_datetime'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?= date('d/m/Y', strtotime($block['end_datetime'])) ?>
                                <?php if (!$block['is_all_day']): ?>
                                    <span class="text-gray-500"><?= date('H:i', strtotime($block['end_datetime'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= $block['professional_name'] ? e($block['professional_name']) : '<span class="text-gray-400">Todos</span>' ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?= $block['unit_name'] ? e($block['unit_name']) : '<span class="text-gray-400">Todas</span>' ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="post" action="<?= url('schedule-blocks/' . $block['id'] . '/delete') ?>"
                                      onsubmit="return confirm('Remover este bloqueio?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="rounded-xl bg-white py-16 text-center shadow-sm border border-gray-100">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum bloqueio ativo</h3>
            <p class="mt-1 text-sm text-gray-500">Crie bloqueios para indisponibilizar períodos na agenda.</p>
            <div class="mt-4">
                <a href="<?= url('schedule-blocks/create') ?>" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
                    + Novo Bloqueio
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
