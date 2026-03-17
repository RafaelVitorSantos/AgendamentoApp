<?php
$units     = $units ?? [];
$pageTitle = $pageTitle ?? 'Unidades';
?>

<div class="space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
            <p class="mt-0.5 text-sm text-gray-500"><?= count($units) ?> unidade<?= count($units) !== 1 ? 's' : '' ?> cadastrada<?= count($units) !== 1 ? 's' : '' ?></p>
        </div>
        <a href="<?= url('units/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Nova Unidade
        </a>
    </div>

    <?php if (empty($units)): ?>
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 px-4 py-16 text-center sm:px-6">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhuma unidade cadastrada</h3>
            <p class="mt-1 text-sm text-gray-500">Adicione as unidades/filiais da sua empresa.</p>
            <div class="mt-4">
                <a href="<?= url('units/create') ?>" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Cadastrar Unidade</a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($units as $u): ?>
                <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="px-5 py-5 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h3 class="text-sm font-semibold text-gray-900"><?= e($u['name']) ?></h3>
                                        <?php if ($u['is_default']): ?>
                                            <span class="inline-flex items-center rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-medium text-brand-700">Principal</span>
                                        <?php endif; ?>
                                        <?php if (!$u['is_active']): ?>
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">Inativa</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5 text-sm text-gray-600">
                            <?php if (!empty($u['address_street'])): ?>
                                <div class="flex items-start gap-1.5">
                                    <svg class="h-4 w-4 flex-shrink-0 mt-0.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                                    <span class="leading-snug">
                                        <?= e($u['address_street']) ?><?= $u['address_number'] ? ', ' . e($u['address_number']) : '' ?>
                                        <?php if ($u['address_city']): ?><br><span class="text-gray-500"><?= e($u['address_city']) ?><?= $u['address_state'] ? '/' . e($u['address_state']) : '' ?></span><?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($u['phone'])): ?>
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 6Z"/></svg>
                                    <?= e($u['phone']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex gap-4 text-xs text-gray-500 pt-1 border-t border-gray-100">
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                                <?= (int)($u['professional_count'] ?? 0) ?> profissional<?= ((int)($u['professional_count'] ?? 0)) !== 1 ? 'is' : '' ?>
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                <?= (int)($u['today_appointments'] ?? 0) ?> agend. hoje
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 border-t border-gray-100 bg-gray-50 px-4 py-2.5">
                        <a href="<?= url('units/' . $u['id'] . '/edit') ?>" class="flex-1 text-center text-sm font-medium text-brand-600 hover:text-brand-500 py-1">Editar</a>
                        <?php if (!$u['is_default']): ?>
                            <div class="w-px h-4 bg-gray-200"></div>
                            <form method="post" action="<?= url('units/' . $u['id'] . '/delete') ?>" class="flex-1 text-center" onsubmit="return confirm('Excluir esta unidade?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="w-full text-sm font-medium text-red-600 hover:text-red-500 py-1">Excluir</button>
                            </form>
                        <?php else: ?>
                            <div class="w-px h-4 bg-gray-200"></div>
                            <span class="flex-1 text-center text-xs text-gray-400 py-1">Unidade principal</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
