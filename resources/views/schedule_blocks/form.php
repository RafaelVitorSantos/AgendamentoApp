<?php
$block         = $block ?? null;
$professionals = $professionals ?? [];
$units         = $units ?? [];
$isEdit        = $block !== null;

$startDt   = ($block !== null ? ($block['start_datetime'] ?? null) : null) ?? old('start_datetime', date('Y-m-d\TH:i'));
$endDt     = ($block !== null ? ($block['end_datetime'] ?? null) : null) ?? old('end_datetime', date('Y-m-d\TH:i', strtotime('+1 hour')));
$startDate = ($block !== null && !empty($block['start_datetime'])) ? substr($block['start_datetime'], 0, 10) : old('start_date', date('Y-m-d'));
$endDate   = ($block !== null && !empty($block['end_datetime'])) ? substr($block['end_datetime'], 0, 10) : old('end_date', date('Y-m-d'));
$isAllDay  = ($block !== null && !empty($block['is_all_day'])) || old('is_all_day');
?>

<div class="max-w-2xl mx-auto space-y-6" x-data="{ isAllDay: <?= $isAllDay ? 'true' : 'false' ?> }">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= url('schedule-blocks') ?>" class="text-gray-400 hover:text-gray-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= $isEdit ? 'Editar Bloqueio' : 'Novo Bloqueio de Horário' ?></h1>
    </div>

    <!-- Info -->
    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
        <strong>Atenção:</strong> Bloqueios impedem novos agendamentos no período especificado. Agendamentos já existentes não são afetados.
    </div>

    <!-- Form -->
    <form method="post" action="<?= url($isEdit ? 'schedule-blocks/' . $block['id'] : 'schedule-blocks') ?>" class="space-y-5">
        <?= csrf_field() ?>

        <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-6 space-y-5">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título / Motivo <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="<?= e($block['title'] ?? old('title', '')) ?>"
                       required placeholder="Ex: Férias, Treinamento, Manutenção..."
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_all_day" id="is_all_day" value="1"
                       <?= $isAllDay ? 'checked' : '' ?>
                       x-model="isAllDay"
                       class="h-4 w-4 rounded border-gray-300 text-brand-600">
                <label for="is_all_day" class="text-sm text-gray-700">Dia(s) inteiro(s) — bloquear um ou vários dias</label>
            </div>

            <!-- Modo dia inteiro: data inicial e data final -->
            <div x-show="isAllDay" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data inicial <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" id="start_date"
                           :required="isAllDay"
                           value="<?= e($startDate) ?>"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Data final <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" id="end_date"
                           :required="isAllDay"
                           value="<?= e($endDate) ?>"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <p class="text-xs text-gray-500 sm:col-span-2">O bloqueio vale do início do dia inicial ao fim do dia final. Use a mesma data nos dois campos para um único dia.</p>
            </div>

            <!-- Modo com horário: início e fim (data + hora) -->
            <div x-show="!isAllDay" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2" id="timeFields">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Início (data e hora) <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="start_datetime" id="start_datetime"
                           :required="!isAllDay" :disabled="isAllDay"
                           value="<?= e($startDt) ?>"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 disabled:opacity-75">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fim (data e hora) <span class="text-red-500">*</span></label>
                    <input type="datetime-local" name="end_datetime" id="end_datetime"
                           :required="!isAllDay" :disabled="isAllDay"
                           value="<?= e($endDt) ?>"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 disabled:opacity-75">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Profissional</label>
                    <select name="professional_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Todos os profissionais</option>
                        <?php foreach ($professionals as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= ($block['professional_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Deixe em branco para bloquear todos.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unidade</label>
                    <select name="unit_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">Todas as unidades</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= ($block['unit_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                <textarea name="notes" rows="3"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500 resize-none"
                          placeholder="Opcional"><?= e($block['notes'] ?? old('notes', '')) ?></textarea>
            </div>

        </div>

        <div class="flex gap-3">
            <a href="<?= url('schedule-blocks') ?>"
               class="flex-1 text-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit"
                    class="flex-1 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
                <?= $isEdit ? 'Salvar alterações' : 'Criar Bloqueio' ?>
            </button>
        </div>
    </form>
</div>
