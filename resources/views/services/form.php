<?php
$service    = $service ?? null;
$categories = $categories ?? [];
$pageTitle  = $pageTitle ?? ($service ? 'Editar Serviço' : 'Novo Serviço');
$errors     = get_flash('errors') ?? [];
$isEdit     = $service !== null;

function svc($service, $key, $default = '') {
    if ($service !== null && isset($service[$key])) return $service[$key];
    return get_flash('old_' . $key, $default);
}
?>

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="<?= url('services') ?>" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
    </div>

    <form method="post" action="<?= $isEdit ? url('services/' . $service['id']) : url('services') ?>" class="space-y-6">
        <?= csrf_field() ?>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-6">
            <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Informações do Serviço</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700">Nome do serviço <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required value="<?= e(svc($service, 'name')) ?>"
                           placeholder="Ex: Corte Masculino"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['name']) ? 'border-red-400' : '' ?>">
                    <?php if (isset($errors['name'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700">Categoria</label>
                    <select name="category_id" id="category_id"
                            onchange="document.getElementById('new_category_row').style.display=this.value==='-1'?'block':'none';"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="0">Sem categoria</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= (int) svc($service, 'category_id') === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                        <option value="-1">+ Nova categoria...</option>
                    </select>
                    <div id="new_category_row" style="display:none;" class="mt-2">
                        <input type="text" name="new_category" id="new_category" placeholder="Nome da nova categoria"
                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>

                <div>
                    <label for="color" class="block text-sm font-medium text-gray-700">Cor na agenda</label>
                    <div class="mt-1 flex items-center gap-3">
                        <input type="color" name="color" id="color" value="<?= e(svc($service, 'color', '#6366F1')) ?>"
                               class="h-10 w-10 cursor-pointer rounded-lg border border-gray-300 p-0.5">
                        <span class="text-sm text-gray-500">Identifica o serviço na agenda</span>
                    </div>
                </div>

                <div>
                    <label for="duration_minutes" class="block text-sm font-medium text-gray-700">Duração <span class="text-red-500">*</span></label>
                    <div class="mt-1 flex rounded-lg shadow-sm">
                        <input type="number" name="duration_minutes" id="duration_minutes" required min="5" step="5"
                               value="<?= e(svc($service, 'duration_minutes', '30')) ?>"
                               class="block w-full rounded-l-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['duration_minutes']) ? 'border-red-400' : '' ?>">
                        <span class="inline-flex items-center rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">min</span>
                    </div>
                    <?php if (isset($errors['duration_minutes'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['duration_minutes']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700">Preço <span class="text-red-500">*</span></label>
                    <div class="mt-1 flex rounded-lg shadow-sm">
                        <span class="inline-flex items-center rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500">R$</span>
                        <input type="number" name="price" id="price" required min="0" step="0.01"
                               value="<?= e(svc($service, 'price', '0.00')) ?>"
                               class="block w-full rounded-r-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['price']) ? 'border-red-400' : '' ?>">
                    </div>
                    <?php if (isset($errors['price'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['price']) ?></p><?php endif; ?>
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Descrição</label>
                <textarea name="description" id="description" rows="2" placeholder="Opcional"
                          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500"><?= e(svc($service, 'description')) ?></textarea>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
            <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Comissão</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="commission_type" class="block text-sm font-medium text-gray-700">Tipo de comissão</label>
                    <select name="commission_type" id="commission_type"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="percentage" <?= svc($service, 'commission_type', 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentual (%)</option>
                        <option value="fixed" <?= svc($service, 'commission_type') === 'fixed' ? 'selected' : '' ?>>Valor fixo (R$)</option>
                    </select>
                </div>
                <div>
                    <label for="commission_value" class="block text-sm font-medium text-gray-700">Valor da comissão</label>
                    <input type="number" name="commission_value" id="commission_value" min="0" step="0.01"
                           value="<?= e(svc($service, 'commission_value', '0')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="mt-1 text-xs text-gray-500">Percentual ou valor fixo conforme o tipo</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-4">
            <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Configurações</h2>

            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_online_booking" id="is_online_booking" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                           <?= svc($service, 'is_online_booking', '1') ? 'checked' : '' ?>>
                    <label for="is_online_booking" class="text-sm text-gray-700">Disponível para agendamento online</label>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="requires_professional" id="requires_professional" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                           <?= svc($service, 'requires_professional', '1') ? 'checked' : '' ?>>
                    <label for="requires_professional" class="text-sm text-gray-700">Exige profissional específico</label>
                </div>
                <?php if ($isEdit): ?>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                               <?= svc($service, 'is_active', '1') ? 'checked' : '' ?>>
                        <label for="is_active" class="text-sm text-gray-700">Serviço ativo</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
                <?= $isEdit ? 'Salvar alterações' : 'Cadastrar Serviço' ?>
            </button>
            <a href="<?= url('services') ?>" class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>
