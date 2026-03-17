<?php
$clients    = $clients ?? [];
$search     = $search ?? '';
$page       = (int) ($page ?? 1);
$totalPages = max(1, (int) ($totalPages ?? 1));
$total      = (int) ($total ?? 0);
$pageTitle  = $pageTitle ?? 'Clientes';
?>

<div class="space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
        <a href="<?= url('clients/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Novo Cliente
        </a>
    </div>

    <form method="get" action="<?= url('clients') ?>" class="flex gap-2">
        <input type="search" name="search" value="<?= e($search) ?>" placeholder="Nome, telefone ou email..."
               class="block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 sm:max-w-xs">
        <button type="submit" class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200">Buscar</button>
        <?php if ($search): ?>
            <a href="<?= url('clients') ?>" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Limpar</a>
        <?php endif; ?>
    </form>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
        <?php if (!empty($clients)): ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($clients as $c): ?>
                    <li class="flex flex-col gap-2 px-4 py-4 sm:px-6 sm:flex-row sm:items-center sm:justify-between hover:bg-gray-50/50">
                        <div class="min-w-0">
                            <a href="<?= url('clients/' . $c['id']) ?>" class="font-semibold text-gray-900 hover:text-brand-600"><?= e($c['name']) ?></a>
                            <?php if (!empty($c['phone'])): ?>
                                <p class="text-sm text-gray-500"><?= e($c['phone']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($c['email'])): ?>
                                <p class="text-xs text-gray-400"><?= e($c['email']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a href="<?= url('clients/' . $c['id'] . '/edit') ?>" class="text-sm font-medium text-brand-600 hover:text-brand-500">Editar</a>
                            <form method="post" action="<?= url('clients/' . $c['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('Excluir este cliente?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500">Excluir</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($totalPages > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 sm:px-6">
                    <p class="text-sm text-gray-500"><?= $total ?> cliente(s)</p>
                    <nav class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="<?= url('clients') ?>?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Anterior</a>
                        <?php endif; ?>
                        <span class="rounded border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-700"><?= $page ?> / <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= url('clients') ?>?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Próxima</a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="px-4 py-16 text-center sm:px-6">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z"/>
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum cliente</h3>
                <p class="mt-1 text-sm text-gray-500"><?= $search ? 'Nenhum resultado para essa busca.' : 'Cadastre seu primeiro cliente.' ?></p>
                <?php if (!$search): ?>
                    <div class="mt-4">
                        <a href="<?= url('clients/create') ?>" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Novo Cliente</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
