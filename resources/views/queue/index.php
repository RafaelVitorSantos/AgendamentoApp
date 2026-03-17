<?php
$queue        = $queue ?? [];
$units        = $units ?? [];
$currentUnit  = $currentUnit ?? 0;
$stats        = $stats ?? [];
$professionals = $professionals ?? [];
$services     = $services ?? [];
$clients      = $clients ?? [];

$statusLabels = [
    'waiting'     => 'Aguardando',
    'called'      => 'Chamado',
    'in_progress' => 'Em atendimento',
    'completed'   => 'Concluído',
    'cancelled'   => 'Cancelado',
    'no_show'     => 'Falta',
];
$statusColors = [
    'waiting'     => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'called'      => 'bg-blue-100 text-blue-800 border-blue-200',
    'in_progress' => 'bg-purple-100 text-purple-800 border-purple-200',
    'completed'   => 'bg-green-100 text-green-800 border-green-200',
    'cancelled'   => 'bg-gray-100 text-gray-600 border-gray-200',
    'no_show'     => 'bg-red-100 text-red-800 border-red-200',
];
?>

<div class="space-y-5" x-data="{ addModal: false }">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Fila de Atendimento</h1>
        <button @click="addModal = true"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
            <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Adicionar à fila
        </button>
    </div>

    <!-- Seletor de unidade -->
    <?php if (count($units) > 1): ?>
    <div class="flex items-center gap-3">
        <label class="text-sm font-medium text-gray-700">Unidade:</label>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($units as $u): ?>
                <a href="<?= url('queue?unit_id=' . $u['id']) ?>"
                   class="rounded-full px-3 py-1 text-sm font-medium transition-colors <?= (int)$u['id'] === $currentUnit ? 'bg-brand-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                    <?= e($u['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- KPIs do dia -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100 text-center">
            <div class="text-2xl font-bold text-gray-900"><?= $stats['total'] ?? 0 ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Total hoje</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm border border-yellow-100 text-center">
            <div class="text-2xl font-bold text-yellow-600"><?= ($stats['waiting'] ?? 0) + ($stats['called'] ?? 0) ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Aguardando</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm border border-purple-100 text-center">
            <div class="text-2xl font-bold text-purple-600"><?= $stats['in_progress'] ?? 0 ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Em atendimento</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm border border-green-100 text-center">
            <div class="text-2xl font-bold text-green-600"><?= $stats['completed'] ?? 0 ?></div>
            <div class="text-xs text-gray-500 mt-0.5">Atendidos</div>
        </div>
    </div>

    <!-- Fila -->
    <?php if (!empty($queue)): ?>
    <div class="space-y-2">
        <?php foreach ($queue as $i => $entry): ?>
            <?php $st = $entry['status']; ?>
            <div class="group flex items-center gap-4 rounded-xl bg-white px-4 py-4 shadow-sm border <?= str_replace('text-', 'border-l-4 border-l-', explode(' ', $statusColors[$st] ?? 'bg-white border-gray-200')[0]) ?? 'border-gray-100' ?> border border-gray-100 hover:shadow-md transition-shadow">
                <!-- Posição -->
                <div class="flex-shrink-0 flex h-10 w-10 items-center justify-center rounded-full <?= $st === 'in_progress' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600' ?> text-sm font-bold">
                    <?= $st === 'in_progress' ? '▶' : ($i + 1) ?>
                </div>

                <!-- Info cliente -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900"><?= e($entry['display_name'] ?? 'Walk-in') ?></span>
                        <?php if ($entry['priority']): ?>
                            <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">Prioritário</span>
                        <?php endif; ?>
                        <span class="rounded-full border px-2.5 py-0.5 text-xs font-medium <?= $statusColors[$st] ?? 'bg-gray-100 text-gray-700 border-gray-200' ?>">
                            <?= $statusLabels[$st] ?? e($st) ?>
                        </span>
                    </div>
                    <div class="mt-0.5 text-xs text-gray-500">
                        <?php if ($entry['service_name']): ?><?= e($entry['service_name']) ?><?php endif; ?>
                        <?php if ($entry['professional_name']): ?> · <?= e($entry['professional_name']) ?><?php endif; ?>
                        · Entrada: <?= date('H:i', strtotime($entry['checked_in_at'] ?? $entry['created_at'])) ?>
                    </div>
                </div>

                <!-- Ações -->
                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <?php if ($st === 'waiting'): ?>
                        <form method="post" action="<?= url('queue/' . $entry['id'] . '/status') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="called">
                            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500">Chamar</button>
                        </form>
                    <?php elseif ($st === 'called'): ?>
                        <form method="post" action="<?= url('queue/' . $entry['id'] . '/status') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="in_progress">
                            <button type="submit" class="rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-purple-500">Iniciar</button>
                        </form>
                    <?php elseif ($st === 'in_progress'): ?>
                        <form method="post" action="<?= url('queue/' . $entry['id'] . '/status') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500">Concluir</button>
                        </form>
                        <form method="post" action="<?= url('queue/' . $entry['id'] . '/status') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="no_show">
                            <button type="submit" class="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200">Falta</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= url('queue/' . $entry['id'] . '/remove') ?>"
                          onsubmit="return confirm('Remover da fila?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="rounded-xl bg-white py-16 text-center shadow-sm border border-gray-100">
        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
        </svg>
        <h3 class="mt-2 text-sm font-semibold text-gray-900">Fila vazia</h3>
        <p class="mt-1 text-sm text-gray-500">Nenhum cliente na fila agora.</p>
        <button @click="addModal = true" class="mt-4 inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
            + Adicionar cliente
        </button>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Adicionar à fila -->
<div x-show="addModal" x-cloak @click.self="addModal = false"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" @click.stop x-data="addQueueForm()">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Adicionar à Fila</h2>
            <button @click="addModal = false" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
        </div>
        <form method="post" action="<?= url('queue') ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="unit_id" value="<?= $currentUnit ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cliente cadastrado</label>
                <select name="client_id" x-model="clientId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Walk-in (sem cadastro)</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?> <?= $c['phone'] ? '— ' . e($c['phone']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <template x-if="!clientId">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome (walk-in)</label>
                    <input type="text" name="client_name" placeholder="Nome do cliente"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </template>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Serviço</label>
                    <select name="service_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Não informado</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Profissional</label>
                    <select name="professional_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Qualquer</option>
                        <?php foreach ($professionals as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="priority" value="1" id="priority" class="h-4 w-4 rounded border-gray-300 text-brand-600">
                <label for="priority" class="text-sm text-gray-700">Atendimento prioritário</label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                <textarea name="notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none"
                          placeholder="Opcional"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" @click="addModal = false"
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
                <button type="submit"
                        class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
                    Adicionar à fila
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function addQueueForm() {
    return { clientId: '' };
}
</script>
