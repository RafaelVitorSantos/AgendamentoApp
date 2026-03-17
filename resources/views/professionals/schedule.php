<?php
$professional = $professional ?? [];
$units        = $units ?? [];
$workingHours = $workingHours ?? [];   // [unit_id][day_of_week] => row
$breaks       = $breaks ?? [];

$days = [
    0 => 'Domingo',
    1 => 'Segunda',
    2 => 'Terça',
    3 => 'Quarta',
    4 => 'Quinta',
    5 => 'Sexta',
    6 => 'Sábado',
];
$profId = (int) $professional['id'];
?>

<div class="space-y-6" x-data="scheduleApp()">

    <!-- Breadcrumb / Header -->
    <div class="flex items-start gap-3">
        <a href="<?= url('professionals') ?>" class="mt-0.5 text-gray-400 hover:text-gray-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white text-sm font-bold"
                     style="background-color: <?= e($professional['color'] ?? '#6366F1') ?>">
                    <?= e(strtoupper(substr($professional['name'], 0, 2))) ?>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-gray-900"><?= e($professional['name']) ?></h1>
                    <p class="text-sm text-gray-500">Horários de Atendimento</p>
                </div>
            </div>
        </div>
        <a href="<?= url('professionals/' . $profId . '/edit') ?>"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
            Editar perfil
        </a>
    </div>

    <!-- Aviso se não tiver unidades -->
    <?php if (empty($units)): ?>
        <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
            <strong>Atenção:</strong> Nenhuma unidade cadastrada. Cadastre pelo menos uma unidade antes de definir horários.
            <a href="<?= url('units/create') ?>" class="ml-2 font-semibold underline">Criar unidade →</a>
        </div>
    <?php else: ?>

    <!-- Tabs de unidades -->
    <?php if (count($units) > 1): ?>
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-4">
            <?php foreach ($units as $i => $unit): ?>
                <button @click="activeUnit = <?= (int) $unit['id'] ?>"
                        :class="activeUnit === <?= (int) $unit['id'] ?> ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                    <?= e($unit['name']) ?>
                </button>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php else: ?>
        <input type="hidden" x-data x-init="activeUnit = <?= (int) $units[0]['id'] ?>">
    <?php endif; ?>

    <!-- Grade de horários por unidade -->
    <?php foreach ($units as $unit): ?>
        <?php $unitId = (int) $unit['id']; ?>

        <div x-show="activeUnit === <?= $unitId ?>">
            <form method="post" action="<?= url('professionals/' . $profId . '/schedule') ?>">
                <?= csrf_field() ?>

                <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-700">
                            Horários em: <span class="text-brand-600"><?= e($unit['name']) ?></span>
                        </h2>
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
                            Salvar horários
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="w-32 px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Dia</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Ativo</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Início</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Fim</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase text-gray-300">Preview</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($days as $dayNum => $dayName): ?>
                                    <?php
                                    $wh        = $workingHours[$unitId][$dayNum] ?? null;
                                    $isActive  = $wh ? 1 : 0;
                                    $startTime = $wh ? substr($wh['start_time'], 0, 5) : '08:00';
                                    $endTime   = $wh ? substr($wh['end_time'], 0, 5) : '18:00';
                                    $rowKey    = "hours[{$unitId}][{$dayNum}]";
                                    $isWeekend = in_array($dayNum, [0, 6]);
                                    ?>
                                    <tr class="hover:bg-gray-50/50" x-data="{ active: <?= $isActive ? 'true' : 'false' ?> }">
                                        <td class="px-6 py-3">
                                            <span class="text-sm font-medium <?= $isWeekend ? 'text-blue-600' : 'text-gray-900' ?>">
                                                <?= $dayName ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="<?= $rowKey ?>[is_active]" value="1"
                                                       <?= $isActive ? 'checked' : '' ?>
                                                       x-model="active"
                                                       class="sr-only peer">
                                                <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-brand-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-600"></div>
                                            </label>
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="time" name="<?= $rowKey ?>[start_time]"
                                                   value="<?= e($startTime) ?>"
                                                   :disabled="!active"
                                                   class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed w-28">
                                        </td>
                                        <td class="px-6 py-3">
                                            <input type="time" name="<?= $rowKey ?>[end_time]"
                                                   value="<?= e($endTime) ?>"
                                                   :disabled="!active"
                                                   class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed w-28">
                                        </td>
                                        <td class="px-6 py-3">
                                            <span x-show="active" class="text-xs text-gray-400 font-mono">
                                                <?= e($startTime) ?> – <?= e($endTime) ?>
                                            </span>
                                            <span x-show="!active" class="text-xs text-gray-300">Fechado</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-100 px-6 py-3 bg-gray-50/50">
                        <p class="text-xs text-gray-400">
                            💡 Os horários definem quando clientes podem agendar online. Bloqueios manuais sobrescrevem esses horários.
                        </p>
                    </div>
                </div>
            </form>
        </div>
    <?php endforeach; ?>

    <!-- Pausas e Intervalos -->
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden" x-data="breaksApp(<?= htmlspecialchars(json_encode(array_map(fn($b) => [
        'day_of_week'  => $b['day_of_week'] ?? '',
        'start_time'   => substr($b['start_time'] ?? '', 0, 5),
        'end_time'     => substr($b['end_time'] ?? '', 0, 5),
        'description'  => $b['description'] ?? '',
    ], $breaks)), ENT_QUOTES) ?>)">

        <div class="border-b border-gray-100 px-6 py-4 bg-gray-50 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Pausas e Intervalos</h2>
                <p class="text-xs text-gray-500 mt-0.5">Ex: almoço, descanso — serão excluídos dos horários disponíveis</p>
            </div>
        </div>

        <form method="post" action="<?= url('professionals/' . $profId . '/breaks') ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>

            <div class="space-y-3" id="breaks-list">
                <template x-for="(brk, i) in breaks" :key="i">
                    <div class="flex flex-wrap items-center gap-3 rounded-lg bg-gray-50 p-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Dia</label>
                            <select :name="'breaks['+i+'][day_of_week]'" x-model="brk.day_of_week"
                                    class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm w-32">
                                <option value="">Todo dia</option>
                                <?php foreach ($days as $dn => $dname): ?>
                                    <option value="<?= $dn ?>"><?= $dname ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Início</label>
                            <input type="time" :name="'breaks['+i+'][start_time]'" x-model="brk.start_time"
                                   class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm w-28">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Fim</label>
                            <input type="time" :name="'breaks['+i+'][end_time]'" x-model="brk.end_time"
                                   class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm w-28">
                        </div>
                        <div class="flex-1 min-w-32">
                            <label class="block text-xs text-gray-500 mb-1">Descrição</label>
                            <input type="text" :name="'breaks['+i+'][description]'" x-model="brk.description"
                                   placeholder="Ex: Almoço"
                                   class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm w-full">
                        </div>
                        <div class="flex items-end pb-0.5">
                            <button type="button" @click="breaks.splice(i, 1)"
                                    class="rounded-lg p-1.5 text-red-400 hover:bg-red-50 hover:text-red-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </template>

                <div x-show="breaks.length === 0" class="text-sm text-gray-400 text-center py-4">
                    Nenhuma pausa configurada.
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <button type="button" @click="breaks.push({day_of_week:'',start_time:'12:00',end_time:'13:00',description:'Almoço'})"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Adicionar pausa
                </button>
                <button type="submit"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
                    Salvar pausas
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>
</div>

<script>
function scheduleApp() {
    return {
        activeUnit: <?= (int) ($units[0]['id'] ?? 0) ?>,
    }
}

function breaksApp(initialBreaks) {
    return {
        breaks: initialBreaks || [],
    }
}
</script>
