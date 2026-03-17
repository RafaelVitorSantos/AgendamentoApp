<?php
$unit      = $unit ?? null;
$pageTitle = $pageTitle ?? ($unit ? 'Editar Unidade' : 'Nova Unidade');
$errors    = get_flash('errors') ?? [];
$isEdit    = $unit !== null;

function uval($unit, $key, $default = '') {
    if ($unit !== null && isset($unit[$key])) return $unit[$key];
    return get_flash('old_' . $key, $default);
}

$timezones = ['America/Sao_Paulo', 'America/Fortaleza', 'America/Recife', 'America/Bahia', 'America/Belem', 'America/Manaus', 'America/Porto_Velho', 'America/Boa_Vista', 'America/Rio_Branco', 'America/Noronha'];
$states = ['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'];
?>

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="<?= url('units') ?>" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <h1 class="text-xl font-semibold text-gray-900"><?= e($pageTitle) ?></h1>
    </div>

    <form method="post" action="<?= $isEdit ? url('units/' . $unit['id']) : url('units') ?>" class="space-y-6">
        <?= csrf_field() ?>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
            <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Identificação</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700">Nome da unidade <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required value="<?= e(uval($unit, 'name')) ?>"
                           placeholder="Ex: Unidade Centro, Filial Norte"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['name']) ? 'border-red-400' : '' ?>">
                    <?php if (isset($errors['name'])): ?><p class="mt-1 text-sm text-red-600"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                    <input type="text" name="phone" id="phone" value="<?= e(uval($unit, 'phone')) ?>"
                           placeholder="(11) 3000-0000"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="email" value="<?= e(uval($unit, 'email')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700">Fuso horário</label>
                    <select name="timezone" id="timezone" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?= $tz ?>" <?= uval($unit, 'timezone', 'America/Sao_Paulo') === $tz ? 'selected' : '' ?>>
                                <?= str_replace('America/', '', $tz) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isEdit): ?>
                    <div class="flex items-center gap-3 sm:col-span-2">
                        <input type="checkbox" name="is_active" id="is_active" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                               <?= uval($unit, 'is_active', '1') ? 'checked' : '' ?>>
                        <label for="is_active" class="text-sm text-gray-700">Unidade ativa</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
            <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Endereço</h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-6">
                <div class="sm:col-span-4">
                    <label for="address_street" class="block text-sm font-medium text-gray-700">Logradouro</label>
                    <input type="text" name="address_street" id="address_street" value="<?= e(uval($unit, 'address_street')) ?>"
                           placeholder="Rua, Avenida, etc."
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-2">
                    <label for="address_number" class="block text-sm font-medium text-gray-700">Número</label>
                    <input type="text" name="address_number" id="address_number" value="<?= e(uval($unit, 'address_number')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-3">
                    <label for="address_complement" class="block text-sm font-medium text-gray-700">Complemento</label>
                    <input type="text" name="address_complement" id="address_complement" value="<?= e(uval($unit, 'address_complement')) ?>"
                           placeholder="Sala, Loja, Andar"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-3">
                    <label for="address_neighborhood" class="block text-sm font-medium text-gray-700">Bairro</label>
                    <input type="text" name="address_neighborhood" id="address_neighborhood" value="<?= e(uval($unit, 'address_neighborhood')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-3">
                    <label for="address_city" class="block text-sm font-medium text-gray-700">Cidade</label>
                    <input type="text" name="address_city" id="address_city" value="<?= e(uval($unit, 'address_city')) ?>"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-1">
                    <label for="address_state" class="block text-sm font-medium text-gray-700">UF</label>
                    <select name="address_state" id="address_state" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">-</option>
                        <?php foreach ($states as $uf): ?>
                            <option value="<?= $uf ?>" <?= uval($unit, 'address_state') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="address_zipcode" class="block text-sm font-medium text-gray-700">CEP</label>
                    <input type="text" name="address_zipcode" id="address_zipcode" value="<?= e(uval($unit, 'address_zipcode')) ?>"
                           placeholder="00000-000"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">
                <?= $isEdit ? 'Salvar alterações' : 'Cadastrar Unidade' ?>
            </button>
            <a href="<?= url('units') ?>" class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
</div>
