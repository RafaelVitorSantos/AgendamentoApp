<?php
$client    = $client ?? null;
$history   = $history ?? [];
$pageTitle = $pageTitle ?? 'Cliente';

$statusLabels = [
    'scheduled' => 'Agendado',
    'confirmed' => 'Confirmado',
    'in_progress' => 'Em atendimento',
    'completed' => 'Concluído',
    'no_show' => 'Falta',
    'cancelled_by_client' => 'Cancelado (cliente)',
    'cancelled_by_business' => 'Cancelado',
];
?>

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="<?= url('clients') ?>" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= e($client['name'] ?? 'Cliente') ?></h1>
        <a href="<?= url('clients/' . $client['id'] . '/edit') ?>" class="ml-auto rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Editar</a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Contato</h2>
            <dl class="mt-3 space-y-2">
                <?php if (!empty($client['phone'])): ?>
                    <div>
                        <dt class="text-xs text-gray-500">Telefone</dt>
                        <dd class="text-sm font-medium text-gray-900"><?= e($client['phone']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($client['phone_whatsapp']) && $client['phone_whatsapp'] !== $client['phone']): ?>
                    <div>
                        <dt class="text-xs text-gray-500">WhatsApp</dt>
                        <dd class="text-sm font-medium text-gray-900"><?= e($client['phone_whatsapp']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($client['email'])): ?>
                    <div>
                        <dt class="text-xs text-gray-500">Email</dt>
                        <dd class="text-sm font-medium text-gray-900"><?= e($client['email']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (!empty($client['birth_date'])): ?>
                    <div>
                        <dt class="text-xs text-gray-500">Nascimento</dt>
                        <dd class="text-sm font-medium text-gray-900"><?= e(date('d/m/Y', strtotime($client['birth_date']))) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (empty($client['phone']) && empty($client['email'])): ?>
                    <p class="text-sm text-gray-500">Nenhum contato cadastrado.</p>
                <?php endif; ?>
            </dl>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Resumo</h2>
            <dl class="mt-3 space-y-2">
                <div>
                    <dt class="text-xs text-gray-500">Visitas</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= (int) ($client['total_visits'] ?? 0) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Total gasto</dt>
                    <dd class="text-sm font-medium text-gray-900"><?= format_money((float) ($client['total_spent'] ?? 0)) ?></dd>
                </div>
                <?php if (!empty($client['last_visit_at'])): ?>
                    <div>
                        <dt class="text-xs text-gray-500">Última visita</dt>
                        <dd class="text-sm font-medium text-gray-900"><?= e(date('d/m/Y', strtotime($client['last_visit_at']))) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <?php if (!empty($client['notes'])): ?>
        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Observações</h2>
            <p class="mt-2 text-sm text-gray-700 whitespace-pre-wrap"><?= e($client['notes']) ?></p>
        </div>
    <?php endif; ?>

    <div class="rounded-xl bg-white shadow-sm border border-gray-100">
        <div class="px-4 py-4 sm:px-6 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Histórico de atendimentos</h2>
        </div>
        <?php if (!empty($history)): ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($history as $a): ?>
                    <li class="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-6 hover:bg-gray-50/50">
                        <span class="text-sm font-medium text-gray-900"><?= e(date('d/m/Y', strtotime($a['date']))) ?> <?= substr($a['start_time'], 0, 5) ?></span>
                        <span class="text-sm text-gray-600">— <?= e($a['service_name']) ?> com <?= e($a['professional_name']) ?></span>
                        <span class="ml-auto inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            <?= $a['status'] === 'completed' ? 'bg-green-100 text-green-800' : (in_array($a['status'], ['cancelled_by_client', 'cancelled_by_business', 'no_show'], true) ? 'bg-gray-100 text-gray-700' : 'bg-yellow-100 text-yellow-800') ?>">
                            <?= $statusLabels[$a['status']] ?? e($a['status']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="px-4 py-8 text-center sm:px-6">
                <p class="text-sm text-gray-500">Nenhum atendimento registrado.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
