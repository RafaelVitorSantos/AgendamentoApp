<?php
$services      = $services ?? [];
$professionals = $professionals ?? [];
$clients       = $clients ?? [];
$units         = $units ?? [];
$pageTitle     = $pageTitle ?? 'Novo Agendamento';
$errors        = get_flash('errors') ?? [];
$preDate       = $_GET['date'] ?? date('Y-m-d');
?>

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="<?= url('appointments') ?>" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
    </div>

    <form method="post" action="<?= url('appointments') ?>" class="space-y-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
        <?= csrf_field() ?>

        <!-- Unidade (obrigatório para verificação de horários) -->
        <?php if (count($units) > 1): ?>
        <div>
            <label for="unit_id" class="block text-sm font-medium text-gray-700">Unidade <span class="text-red-500">*</span></label>
            <select name="unit_id" id="unit_id" required
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                <?php foreach ($units as $u): ?>
                    <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif (!empty($units)): ?>
            <input type="hidden" name="unit_id" value="<?= (int) $units[0]['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <label for="professional_id" class="block text-sm font-medium text-gray-700">Profissional <span class="text-red-500">*</span></label>
                <select name="professional_id" id="professional_id" required
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['professional_id']) ? 'border-red-500' : '' ?>">
                    <option value="">Selecione</option>
                    <?php foreach ($professionals as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['professional_id'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['professional_id']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="service_id" class="block text-sm font-medium text-gray-700">Serviço <span class="text-red-500">*</span></label>
                <select name="service_id" id="service_id" required
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['service_id']) ? 'border-red-500' : '' ?>">
                    <option value="">Selecione</option>
                    <?php
                    $currentCat = null;
                    foreach ($services as $s):
                        if (($s['category_name'] ?? '') !== $currentCat):
                            $currentCat = $s['category_name'] ?? 'Sem categoria';
                            if ($currentCat): ?>
                                <optgroup label="<?= e($currentCat) ?>">
                            <?php endif;
                        endif;
                    ?>
                        <option value="<?= (int) $s['id'] ?>" data-duration="<?= (int)($s['duration_minutes'] ?? 0) ?>"><?= e($s['name']) ?> (<?= (int)($s['duration_minutes'] ?? 0) ?> min)</option>
                    <?php
                    endforeach;
                    if ($currentCat): ?></optgroup><?php endif;
                    ?>
                </select>
                <?php if (isset($errors['service_id'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['service_id']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <label for="client_id" class="block text-sm font-medium text-gray-700">Cliente</label>
            <select name="client_id" id="client_id" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">Walk-in / Não informado</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700">Data <span class="text-red-500">*</span></label>
                <input type="date" name="date" id="date" required value="<?= e(get_flash('old_date', $preDate)) ?>"
                       min="<?= date('Y-m-d') ?>"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['date']) ? 'border-red-500' : '' ?>">
                <?php if (isset($errors['date'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['date']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="start_time" class="block text-sm font-medium text-gray-700">Horário <span class="text-red-500">*</span></label>
                <input type="time" name="start_time" id="start_time" required value="<?= e(get_flash('old_start_time', '')) ?>"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['start_time']) ? 'border-red-500' : '' ?>">
                <?php if (isset($errors['start_time'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['start_time']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
            <textarea name="notes" id="notes" rows="2" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Opcional"><?= e(get_flash('old_notes', '')) ?></textarea>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
                Agendar
            </button>
            <a href="<?= url('appointments') ?>" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Voltar
            </a>
        </div>
    </form>
</div>
