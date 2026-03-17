<?php
$professionals = $professionals ?? [];
$pageTitle     = $pageTitle ?? 'Profissionais';
?>

<div class="space-y-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
            <p class="mt-0.5 text-sm text-gray-500"><?= count($professionals) ?> profissional<?= count($professionals) !== 1 ? 'is' : '' ?> cadastrado<?= count($professionals) !== 1 ? 's' : '' ?></p>
        </div>
        <a href="<?= url('professionals/create') ?>" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
            <svg class="-ml-0.5 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Novo Profissional
        </a>
    </div>

    <?php if (empty($professionals)): ?>
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 px-4 py-16 text-center sm:px-6">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum profissional cadastrado</h3>
            <p class="mt-1 text-sm text-gray-500">Adicione os profissionais que fazem parte da sua equipe.</p>
            <div class="mt-4">
                <a href="<?= url('professionals/create') ?>" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Cadastrar Profissional</a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($professionals as $p): ?>
                <div class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="px-5 py-5">
                        <div class="flex items-start gap-4">
                            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-full text-white text-lg font-bold shadow-sm"
                                 style="background-color: <?= e($p['color'] ?? '#3B82F6') ?>">
                                <?= e(strtoupper(substr($p['name'], 0, 2))) ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-900"><?= e($p['name']) ?></h3>
                                    <?php if (!$p['is_active']): ?>
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">Inativo</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700">Ativo</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($p['email'])): ?>
                                    <p class="text-xs text-gray-500 mt-0.5 truncate"><?= e($p['email']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($p['phone'])): ?>
                                    <p class="text-xs text-gray-500"><?= e($p['phone']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($p['bio'])): ?>
                            <p class="mt-3 text-xs text-gray-600 line-clamp-2"><?= e($p['bio']) ?></p>
                        <?php endif; ?>

                        <div class="mt-3 flex items-center gap-3 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/></svg>
                                <?= (int)($p['service_count'] ?? 0) ?> serviço<?= ((int)($p['service_count'] ?? 0)) !== 1 ? 's' : '' ?>
                            </span>
                            <?php if ($p['commission_default_value'] > 0): ?>
                                <span>
                                    Comissão: <?= $p['commission_default_type'] === 'percentage'
                                        ? (int)$p['commission_default_value'] . '%'
                                        : format_money($p['commission_default_value']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 border-t border-gray-100 bg-gray-50 px-4 py-2.5">
                        <a href="<?= url('professionals/' . $p['id'] . '/edit') ?>" class="flex-1 text-center text-sm font-medium text-brand-600 hover:text-brand-500 py-1">Editar</a>
                        <div class="w-px h-4 bg-gray-200"></div>
                        <a href="<?= url('professionals/' . $p['id'] . '/schedule') ?>" class="flex-1 text-center text-sm font-medium text-purple-600 hover:text-purple-500 py-1" title="Definir horários de atendimento">
                            Horários
                        </a>
                        <div class="w-px h-4 bg-gray-200"></div>
                        <form method="post" action="<?= url('professionals/' . $p['id'] . '/toggle') ?>" class="flex-1 text-center">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full text-sm font-medium py-1 <?= $p['is_active'] ? 'text-gray-500 hover:text-orange-600' : 'text-green-600 hover:text-green-700' ?>">
                                <?= $p['is_active'] ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>
                        <div class="w-px h-4 bg-gray-200"></div>
                        <form method="post" action="<?= url('professionals/' . $p['id'] . '/delete') ?>" class="flex-1 text-center" onsubmit="return confirm('Excluir este profissional?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full text-sm font-medium text-red-600 hover:text-red-500 py-1">Excluir</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
