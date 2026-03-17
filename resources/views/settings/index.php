<?php
$tenant       = $tenant ?? [];
$user         = $user ?? [];
$subscription = $subscription ?? null;
$tab          = $tab ?? 'company';
$errors       = get_flash('errors') ?? [];

$timezones = ['America/Sao_Paulo', 'America/Fortaleza', 'America/Recife', 'America/Bahia', 'America/Belem', 'America/Manaus', 'America/Porto_Velho', 'America/Boa_Vista', 'America/Rio_Branco', 'America/Noronha'];

$planStatusLabels = ['active' => 'Ativo', 'trialing' => 'Trial', 'past_due' => 'Em atraso', 'cancelled' => 'Cancelado', 'suspended' => 'Suspenso'];
$planStatusColors = ['active' => 'bg-green-100 text-green-700', 'trialing' => 'bg-blue-100 text-blue-700', 'past_due' => 'bg-orange-100 text-orange-700', 'cancelled' => 'bg-gray-100 text-gray-600', 'suspended' => 'bg-red-100 text-red-700'];
?>

<div class="space-y-4">
    <h1 class="text-xl font-semibold text-gray-900">Configurações</h1>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6">
            <?php
            $tabs = [
                'company' => ['Empresa', 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
                'account' => ['Minha Conta', 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
                'plan'    => ['Plano', 'M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z'],
            ];
            foreach ($tabs as $key => [$label, $icon]):
                $active = $tab === $key;
            ?>
                <a href="<?= url('settings?tab=' . $key) ?>"
                   class="flex items-center gap-1.5 border-b-2 px-1 pb-3 text-sm font-medium transition-colors <?= $active ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' ?>">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- ===================== ABA EMPRESA ===================== -->
    <?php if ($tab === 'company'): ?>
        <form method="post" action="<?= url('settings/company') ?>" class="space-y-6 max-w-2xl">
            <?= csrf_field() ?>

            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
                <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Dados da Empresa</h2>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700">Razão Social <span class="text-red-500">*</span></label>
                        <input type="text" name="company_name" id="company_name" required
                               value="<?= e($tenant['company_name'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['company_name']) ? 'border-red-400' : '' ?>">
                    </div>
                    <div>
                        <label for="trade_name" class="block text-sm font-medium text-gray-700">Nome Fantasia</label>
                        <input type="text" name="trade_name" id="trade_name"
                               value="<?= e($tenant['trade_name'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="email" required
                               value="<?= e($tenant['email'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500 <?= isset($errors['email']) ? 'border-red-400' : '' ?>">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                        <input type="text" name="phone" id="phone"
                               value="<?= e($tenant['phone'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="document_number" class="block text-sm font-medium text-gray-700">CNPJ / CPF</label>
                        <input type="text" name="document_number" id="document_number"
                               value="<?= e($tenant['document_number'] ?? '') ?>"
                               placeholder="00.000.000/0001-00"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-gray-700">Fuso horário</label>
                        <select name="timezone" id="timezone" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <?php foreach ($timezones as $tz): ?>
                                <option value="<?= $tz ?>" <?= ($tenant['timezone'] ?? 'America/Sao_Paulo') === $tz ? 'selected' : '' ?>>
                                    <?= str_replace('America/', '', $tz) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
                <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Identidade Visual</h2>
                <div class="flex items-center gap-6">
                    <div>
                        <label for="primary_color" class="block text-sm font-medium text-gray-700 mb-2">Cor principal da marca</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="primary_color" id="primary_color"
                                   value="<?= e($tenant['primary_color'] ?? '#4F46E5') ?>"
                                   class="h-10 w-10 cursor-pointer rounded-lg border border-gray-300 p-0.5">
                            <span class="text-sm text-gray-500">Aparece no link público de agendamento</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">Salvar alterações</button>
            </div>
        </form>

    <!-- ===================== ABA MINHA CONTA ===================== -->
    <?php elseif ($tab === 'account'): ?>
        <div class="space-y-6 max-w-2xl">
            <!-- Perfil -->
            <form method="post" action="<?= url('settings/profile') ?>" class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
                <?= csrf_field() ?>
                <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Perfil do usuário</h2>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Nome <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" required
                               value="<?= e($user['name'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="u_phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                        <input type="text" name="phone" id="u_phone"
                               value="<?= e($user['phone'] ?? '') ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <p class="mt-1 block w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-500"><?= e($user['email'] ?? '') ?></p>
                        <p class="mt-1 text-xs text-gray-400">Para alterar o email, entre em contato com o suporte.</p>
                    </div>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-500">Atualizar perfil</button>
                </div>
            </form>

            <!-- Alterar senha -->
            <form method="post" action="<?= url('settings/password') ?>" class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
                <?= csrf_field() ?>
                <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Alterar senha</h2>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="current_password" class="block text-sm font-medium text-gray-700">Senha atual <span class="text-red-500">*</span></label>
                        <input type="password" name="current_password" id="current_password" required
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">Nova senha <span class="text-red-500">*</span></label>
                        <input type="password" name="new_password" id="new_password" required minlength="8"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-gray-400">Mínimo 8 caracteres</p>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirmar nova senha <span class="text-red-500">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" required
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="submit" class="rounded-lg bg-gray-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">Alterar senha</button>
                </div>
            </form>
        </div>

    <!-- ===================== ABA PLANO ===================== -->
    <?php elseif ($tab === 'plan'): ?>
        <div class="space-y-6 max-w-2xl">
            <?php if ($subscription): ?>
                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 space-y-5">
                    <h2 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3">Plano atual</h2>
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold text-gray-900"><?= e($subscription['name'] ?? '') ?></h3>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $planStatusColors[$subscription['sub_status'] ?? ''] ?? 'bg-gray-100 text-gray-600' ?>">
                                    <?= $planStatusLabels[$subscription['sub_status'] ?? ''] ?? ($subscription['sub_status'] ?? '') ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-500">
                                <?= format_money((float)($subscription['price_monthly'] ?? 0)) ?>/mês
                                <?php if ($subscription['current_period_end']): ?>
                                    · Renova em <?= e(format_date($subscription['current_period_end'])) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4 text-sm">
                        <?php
                        $limits = [
                            'Profissionais' => $subscription['max_professionals'] == -1 ? 'Ilimitado' : $subscription['max_professionals'],
                            'Agend./mês'    => $subscription['max_appointments_month'] == -1 ? 'Ilimitado' : $subscription['max_appointments_month'],
                            'Unidades'      => $subscription['max_units'] == -1 ? 'Ilimitado' : $subscription['max_units'],
                            'Clientes'      => $subscription['max_clients'] == -1 ? 'Ilimitado' : $subscription['max_clients'],
                        ];
                        foreach ($limits as $k => $v):
                        ?>
                            <div class="rounded-lg bg-gray-50 p-3 text-center">
                                <dt class="text-xs text-gray-500"><?= $k ?></dt>
                                <dd class="mt-0.5 font-semibold text-gray-900"><?= $v ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>

                    <div class="space-y-2 pt-1">
                        <?php
                        $features = [
                            'has_reports'     => 'Relatórios completos',
                            'has_whatsapp'    => 'Lembretes via WhatsApp',
                            'has_loyalty'     => 'Programa de fidelidade',
                            'has_financial'   => 'Módulo financeiro',
                            'has_commissions' => 'Comissões automáticas',
                            'has_reviews'     => 'Sistema de avaliações',
                        ];
                        foreach ($features as $key => $label):
                            $has = !empty($subscription[$key]);
                        ?>
                            <div class="flex items-center gap-2 text-sm <?= $has ? 'text-gray-700' : 'text-gray-400' ?>">
                                <?php if ($has): ?>
                                    <svg class="h-4 w-4 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                <?php else: ?>
                                    <svg class="h-4 w-4 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                <?php endif; ?>
                                <?= $label ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="rounded-xl bg-gradient-to-br from-brand-600 to-brand-700 p-6 text-white">
                    <h3 class="text-base font-semibold">Deseja fazer upgrade?</h3>
                    <p class="mt-1 text-sm text-brand-200">Aumente seus limites e acesse recursos exclusivos.</p>
                    <div class="mt-4 flex gap-3">
                        <a href="#" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">Ver planos</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 text-center">
                    <p class="text-sm text-gray-500">Nenhuma assinatura ativa encontrada.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
