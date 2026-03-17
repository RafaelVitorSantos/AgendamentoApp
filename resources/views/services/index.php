<?php
$grouped    = $grouped ?? [];
$categories = $categories ?? [];
$total      = (int) ($total ?? 0);
$pageTitle  = $pageTitle ?? 'Serviços';
?>

<div class="space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
            <p class="mt-0.5 text-sm text-gray-500"><?= $total ?> serviço<?= $total !== 1 ? 's' : '' ?> cadastrado<?= $total !== 1 ? 's' : '' ?></p>
        </div>
        <a href="<?= url('services/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Novo Serviço
        </a>
    </div>

    <?php if (empty($grouped)): ?>
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 px-4 py-16 text-center sm:px-6">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/>
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum serviço cadastrado</h3>
            <p class="mt-1 text-sm text-gray-500">Cadastre os serviços que sua empresa oferece.</p>
            <div class="mt-4">
                <a href="<?= url('services/create') ?>" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Cadastrar Serviço</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $categoryName => $services): ?>
            <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
                <div class="flex items-center justify-between bg-gray-50 px-4 py-3 sm:px-6 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide"><?= e($categoryName) ?></h2>
                    <span class="text-xs font-medium text-gray-500"><?= count($services) ?> serviço<?= count($services) !== 1 ? 's' : '' ?></span>
                </div>
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($services as $s): ?>
                        <li class="flex flex-col gap-3 px-4 py-4 sm:px-6 sm:flex-row sm:items-center sm:justify-between hover:bg-gray-50/40 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-9 w-9 flex-shrink-0 rounded-lg" style="background-color: <?= e($s['color'] ?? '#6366F1') ?>1a; border: 2px solid <?= e($s['color'] ?? '#6366F1') ?>">
                                    <div class="flex h-full w-full items-center justify-center">
                                        <div class="h-3 w-3 rounded-full" style="background-color: <?= e($s['color'] ?? '#6366F1') ?>"></div>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-gray-900"><?= e($s['name']) ?></p>
                                        <?php if (!$s['is_active']): ?>
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">Inativo</span>
                                        <?php endif; ?>
                                        <?php if ($s['is_online_booking']): ?>
                                            <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700">Online</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-gray-500"><?= (int) $s['duration_minutes'] ?> min · <?= format_money((float) $s['price']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <form method="post" action="<?= url('services/' . $s['id'] . '/toggle') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="text-xs font-medium <?= $s['is_active'] ? 'text-gray-500 hover:text-red-600' : 'text-green-600 hover:text-green-700' ?>">
                                        <?= $s['is_active'] ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                                <a href="<?= url('services/' . $s['id'] . '/edit') ?>" class="text-sm font-medium text-brand-600 hover:text-brand-500">Editar</a>
                                <form method="post" action="<?= url('services/' . $s['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('Excluir este serviço?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500">Excluir</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
