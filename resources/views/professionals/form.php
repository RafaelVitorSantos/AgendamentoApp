<?php
$professional = $professional ?? null;
$services     = $services ?? [];
$assigned     = $assigned ?? [];
$pageTitle    = $pageTitle ?? ($professional ? 'Editar Profissional' : 'Novo Profissional');
$errors       = get_flash('errors') ?? [];
$isEdit       = $professional !== null;

function prof($p, $key, $default = '') {
    if ($p !== null && isset($p[$key])) return $p[$key];
    return get_flash('old_' . $key, $default);
}
?>

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="<?= url('professionals') ?>" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
    </div>

    <form method="post" action="<?= $isEdit ? url('professionals/' . $professional['id']) : url('professionals') ?>" class="space-y-6">
        <?= csrf_field() ?>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
            <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Dados do Profissional</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700">Nome completo <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required value="<?= e(prof($professional, 'name')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['name']) ? 'border-red-400' : '' ?>">
                    <?php if (isset($errors['name'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="email" value="<?= e(prof($professional, 'email')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                    <input type="text" name="phone" id="phone" value="<?= e(prof($professional, 'phone')) ?>"
                           placeholder="(11) 99999-9999"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label for="color" class="block text-sm font-medium text-gray-700">Cor na agenda</label>
                    <div class="mt-1 flex items-center gap-3">
                        <input type="color" name="color" id="color" value="<?= e(prof($professional, 'color', '#3B82F6')) ?>"
                               class="h-10 w-10 cursor-pointer rounded-lg border border-gray-300 p-0.5">
                        <span class="text-sm text-gray-500">Identificação visual na agenda</span>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                               <?= prof($professional, 'is_active', '1') ? 'checked' : '' ?>>
                        <label for="is_active" class="text-sm text-gray-700">Profissional ativo</label>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <label for="bio" class="block text-sm font-medium text-gray-700">Bio / Especialidade</label>
                <textarea name="bio" id="bio" rows="2" placeholder="Ex: Especialista em coloração e escova"
                          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500"><?= e(prof($professional, 'bio')) ?></textarea>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
            <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Comissão Padrão</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="commission_default_type" class="block text-sm font-medium text-gray-700">Tipo</label>
                    <select name="commission_default_type" id="commission_default_type"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="percentage" <?= prof($professional, 'commission_default_type', 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentual (%)</option>
                        <option value="fixed" <?= prof($professional, 'commission_default_type') === 'fixed' ? 'selected' : '' ?>>Valor fixo (R$)</option>
                    </select>
                </div>
                <div>
                    <label for="commission_default_value" class="block text-sm font-medium text-gray-700">Valor</label>
                    <input type="number" name="commission_default_value" id="commission_default_value" min="0" step="0.01"
                           value="<?= e(prof($professional, 'commission_default_value', '0')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>

        <?php if (!empty($services)): ?>
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Serviços que realiza</h2>
                    <p class="text-sm text-gray-500 mt-1">Selecione os serviços que este profissional pode realizar</p>
                </div>
                <?php
                $groupedSvcs = [];
                foreach ($services as $s) {
                    $cat = $s['category_name'] ?? 'Sem categoria';
                    $groupedSvcs[$cat][] = $s;
                }
                ?>
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    <?php foreach ($groupedSvcs as $catName => $svcs): ?>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= e($catName) ?></p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($svcs as $s): ?>
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
                                    <input type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>"
                                           class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                           <?= in_array((int) $s['id'], array_map('intval', $assigned)) ? 'checked' : '' ?>>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate"><?= e($s['name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= (int) $s['duration_minutes'] ?> min · <?= format_money((float) $s['price']) ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
                <?= $isEdit ? 'Salvar alterações' : 'Cadastrar Profissional' ?>
            </button>
            <a href="<?= url('professionals') ?>" class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>
