<?php
$calendars     = $calendars     ?? [];
$integrationId = $integrationId ?? 0;
?>

<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-xl font-semibold text-gray-900">Selecionar Agenda do Google</h1>
        <p class="mt-1 text-sm text-gray-500">Escolha qual agenda receberá os agendamentos sincronizados.</p>
    </div>

    <?php if (empty($calendars)): ?>
        <div class="rounded-lg bg-orange-50 border border-orange-200 px-4 py-4 text-sm text-orange-800">
            Nenhuma agenda encontrada na sua conta Google. Verifique se você autorizou o acesso ao Google Calendar.
        </div>
        <a href="<?= url('settings/calendar') ?>" class="text-sm text-brand-600 hover:underline">← Voltar</a>
    <?php else: ?>
        <form method="post" action="<?= url('settings/calendar/google/select') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="integration_id" value="<?= (int) $integrationId ?>">

            <div class="rounded-xl bg-white border border-gray-100 shadow-sm divide-y divide-gray-100">
                <?php foreach ($calendars as $cal): ?>
                    <label class="flex items-center gap-4 px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors">
                        <input type="radio" name="calendar_id" value="<?= e($cal['id']) ?>"
                               data-name="<?= e($cal['summary'] ?? $cal['id']) ?>"
                               required
                               class="h-4 w-4 text-brand-600 border-gray-300">
                        <input type="hidden" name="calendar_name" id="cal-name-hidden">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900"><?= e($cal['summary'] ?? $cal['id']) ?></p>
                            <?php if (!empty($cal['description'])): ?>
                                <p class="text-xs text-gray-500 truncate"><?= e($cal['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (($cal['primary'] ?? false)): ?>
                            <span class="shrink-0 text-xs rounded-full bg-blue-100 text-blue-700 px-2 py-0.5 font-medium">Principal</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700 transition-colors">
                    Usar esta agenda
                </button>
                <a href="<?= url('settings/calendar') ?>"
                   class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Propaga o nome da agenda selecionada para o hidden field
document.querySelectorAll('input[name="calendar_id"]').forEach(radio => {
    radio.addEventListener('change', () => {
        document.getElementById('cal-name-hidden').value = radio.dataset.name;
    });
});
</script>
