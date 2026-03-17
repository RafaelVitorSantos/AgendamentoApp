<?php
$client    = $client ?? null;
$pageTitle = $pageTitle ?? ($client ? 'Editar Cliente' : 'Novo Cliente');
$errors    = get_flash('errors') ?? [];
$isEdit    = $client !== null;

function form_val($client, $key, $default = '') {
    if ($client !== null && isset($client[$key])) return $client[$key];
    return get_flash('old_' . $key, $default);
}
?>

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="<?= url('clients') ?>" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
    </div>

    <form method="post" action="<?= $isEdit ? url('clients/' . $client['id']) : url('clients') ?>" class="space-y-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-medium text-gray-700">Nome <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" required value="<?= e(form_val($client, 'name')) ?>"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['name']) ? 'border-red-500' : '' ?>">
                <?php if (isset($errors['name'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Telefone <span class="text-red-500">*</span></label>
                <input type="text" name="phone" id="phone" required value="<?= e(form_val($client, 'phone')) ?>"
                       placeholder="(11) 99999-9999"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['phone']) ? 'border-red-500' : '' ?>">
                <?php if (isset($errors['phone'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['phone']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="phone_whatsapp" class="block text-sm font-medium text-gray-700">WhatsApp</label>
                <input type="text" name="phone_whatsapp" id="phone_whatsapp" value="<?= e(form_val($client, 'phone_whatsapp', form_val($client, 'phone'))) ?>"
                       placeholder="Mesmo do telefone se não informado"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div class="sm:col-span-2">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" id="email" value="<?= e(form_val($client, 'email')) ?>"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label for="document_number" class="block text-sm font-medium text-gray-700">CPF</label>
                <input type="text" name="document_number" id="document_number" value="<?= e(form_val($client, 'document_number')) ?>"
                       placeholder="000.000.000-00"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label for="birth_date" class="block text-sm font-medium text-gray-700">Data de nascimento</label>
                <input type="date" name="birth_date" id="birth_date" value="<?= e(form_val($client, 'birth_date')) ?>"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label for="gender" class="block text-sm font-medium text-gray-700">Gênero</label>
                <select name="gender" id="gender" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="N" <?= form_val($client, 'gender', 'N') === 'N' ? 'selected' : '' ?>>Não informado</option>
                    <option value="M" <?= form_val($client, 'gender') === 'M' ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= form_val($client, 'gender') === 'F' ? 'selected' : '' ?>>Feminino</option>
                    <option value="O" <?= form_val($client, 'gender') === 'O' ? 'selected' : '' ?>>Outro</option>
                </select>
            </div>

            <?php if (!$isEdit): ?>
            <div>
                <label for="source" class="block text-sm font-medium text-gray-700">Origem</label>
                <input type="text" name="source" id="source" value="<?= e(form_val($client, 'source')) ?>"
                       placeholder="Instagram, indicação, etc."
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="lgpd_consent" id="lgpd_consent" value="1" <?= form_val($client, 'lgpd_consent') ? 'checked' : '' ?>
                       class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <label for="lgpd_consent" class="ml-2 block text-sm text-gray-700">Cliente autoriza uso de dados (LGPD)</label>
            </div>
            <?php endif; ?>
        </div>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700">Observações</label>
            <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Anotações internas"><?= e(form_val($client, 'notes')) ?></textarea>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
                <?= $isEdit ? 'Salvar' : 'Cadastrar' ?>
            </button>
            <a href="<?= url('clients') ?>" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Voltar
            </a>
        </div>
    </form>
</div>
