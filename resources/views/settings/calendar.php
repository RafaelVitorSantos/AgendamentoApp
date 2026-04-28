<?php
$calendarToken = $calendarToken ?? null;
$integrations  = $integrations  ?? [];
$appUrl        = $appUrl        ?? '';
$googleEnabled = $googleEnabled ?? false;

$feedUrl = $calendarToken
    ? $appUrl . '/calendar/' . $calendarToken['token'] . '.ics'
    : null;

$success = get_flash('success');
$error   = get_flash('error');
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Integrações de Calendário</h1>
            <p class="mt-1 text-sm text-gray-500">Sincronize seus agendamentos com Google Calendar, Apple Calendar ou Outlook.</p>
        </div>
        <a href="<?= url('settings') ?>" class="text-sm text-gray-500 hover:text-gray-700">← Configurações</a>
    </div>

    <?php if ($success): ?>
        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- ── Feed iCal ─────────────────────────────────────────────────────── -->
    <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-6 space-y-4">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Feed iCal (Universal)</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Funciona com qualquer app de calendário: Google, Apple, Outlook, Thunderbird.
                    Atualiza automaticamente a cada hora.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $feedUrl ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                <?= $feedUrl ? 'Ativo' : 'Inativo' ?>
            </span>
        </div>

        <?php if ($feedUrl): ?>
            <div class="space-y-3">
                <label class="block text-sm font-medium text-gray-700">URL do Feed</label>
                <div class="flex gap-2">
                    <input type="text" id="ical-url" readonly value="<?= e($feedUrl) ?>"
                           class="flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-mono text-gray-700 focus:outline-none">
                    <button onclick="copyIcalUrl()" type="button"
                            class="shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors">
                        Copiar
                    </button>
                </div>

                <div class="rounded-lg bg-blue-50 border border-blue-100 p-3 text-xs text-blue-700 space-y-1">
                    <p class="font-medium">Como usar:</p>
                    <p>• <strong>Google Calendar:</strong> Outros calendários → Via URL → cole o endereço acima</p>
                    <p>• <strong>Apple Calendar:</strong> Arquivo → Nova assinatura de calendário → cole o endereço</p>
                    <p>• <strong>Outlook:</strong> Adicionar calendário → Da internet → cole o endereço</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="<?= e($feedUrl) ?>" target="_blank"
                       class="text-sm text-brand-600 hover:text-brand-700 underline">
                        Testar feed ↗
                    </a>
                    <form method="post" action="<?= url('settings/calendar/token/revoke') ?>"
                          onsubmit="return confirm('Revogar o feed iCal desconectará todos os calendários que usam este endereço. Confirmar?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="text-sm text-red-600 hover:text-red-700">
                            Revogar feed
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= url('settings/calendar/token/generate') ?>">
                <?= csrf_field() ?>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/>
                    </svg>
                    Gerar URL do Feed iCal
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- ── Google Calendar OAuth ─────────────────────────────────────────── -->
    <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-6 space-y-4">
        <div class="flex items-start gap-4">
            <div class="shrink-0 h-10 w-10 rounded-lg bg-red-50 flex items-center justify-center">
                <svg class="h-6 w-6" viewBox="0 0 48 48" fill="none">
                    <path d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.6 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.9z" fill="#FFC107"/>
                    <path d="m6.3 14.7 6.6 4.8C14.6 16 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.6 29.5 4 24 4c-7.7 0-14.3 4.4-17.7 10.7z" fill="#FF3D00"/>
                    <path d="M24 44c5.4 0 10.3-2 14-5.4l-6.5-5.5C29.4 35 26.8 36 24 36c-5.3 0-9.7-3.3-11.3-8H6.1C9.5 39.5 16.2 44 24 44z" fill="#4CAF50"/>
                    <path d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.5l6.5 5.5C37.3 37.1 44 32 44 24c0-1.3-.1-2.6-.4-3.9z" fill="#1976D2"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-semibold text-gray-900">Google Calendar</h2>
                <p class="text-sm text-gray-500 mt-0.5">
                    Sincronização bidirecional via OAuth 2.0.
                    <?php if (!$googleEnabled): ?>
                        <span class="text-orange-600">Configure GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET no .env para habilitar.</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php
        $googleIntegrations = array_filter($integrations, fn($i) => $i['provider'] === 'google');
        if (!empty($googleIntegrations)): ?>
            <div class="space-y-2">
                <?php foreach ($googleIntegrations as $intg): ?>
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 text-xs font-bold">
                                <?= strtoupper(substr($intg['provider_account'] ?? 'G', 0, 1)) ?>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate"><?= e($intg['provider_account'] ?? 'Conta Google') ?></p>
                                <p class="text-xs text-gray-500">
                                    <?= e($intg['calendar_name'] ?? 'Agenda não selecionada') ?>
                                    <?php if ($intg['professional_name']): ?>
                                        &middot; <?= e($intg['professional_name']) ?>
                                    <?php endif; ?>
                                    <?php if ($intg['last_sync_at']): ?>
                                        &middot; sync <?= date('d/m H:i', strtotime($intg['last_sync_at'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 ml-4">
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input type="checkbox"
                                       class="sr-only peer"
                                       <?= $intg['sync_enabled'] ? 'checked' : '' ?>
                                       onchange="toggleSync(<?= $intg['id'] ?>, this.checked)">
                                <div class="h-5 w-9 rounded-full bg-gray-200 peer-checked:bg-brand-600 after:absolute after:top-0.5 after:left-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all peer-checked:after:translate-x-4"></div>
                            </label>
                            <form method="post" action="<?= url('settings/calendar/integrations/' . $intg['id'] . '/delete') ?>"
                                  onsubmit="return confirm('Remover integração com Google Calendar?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="text-gray-400 hover:text-red-500 transition-colors" title="Remover">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($googleEnabled): ?>
            <a href="<?= url('oauth/google') ?>"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                <svg class="h-4 w-4" viewBox="0 0 48 48" fill="none">
                    <path d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.6 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.9z" fill="#FFC107"/>
                    <path d="m6.3 14.7 6.6 4.8C14.6 16 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.6 29.5 4 24 4c-7.7 0-14.3 4.4-17.7 10.7z" fill="#FF3D00"/>
                    <path d="M24 44c5.4 0 10.3-2 14-5.4l-6.5-5.5C29.4 35 26.8 36 24 36c-5.3 0-9.7-3.3-11.3-8H6.1C9.5 39.5 16.2 44 24 44z" fill="#4CAF50"/>
                    <path d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.2 5.5l6.5 5.5C37.3 37.1 44 32 44 24c0-1.3-.1-2.6-.4-3.9z" fill="#1976D2"/>
                </svg>
                <?= empty($googleIntegrations) ? 'Conectar Google Calendar' : 'Adicionar outra conta' ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- ── Info sobre provedores adicionais ──────────────────────────────── -->
    <div class="rounded-xl bg-gray-50 border border-gray-200 p-5">
        <h3 class="text-sm font-medium text-gray-700 mb-2">Apple Calendar & Outlook</h3>
        <p class="text-sm text-gray-500">
            Use o <strong>feed iCal</strong> acima para integrar com Apple Calendar (iOS/macOS) e Microsoft Outlook.
            Esses apps suportam assinatura de calendários via URL iCal/WebCal nativamente.
        </p>
    </div>
</div>

<script>
function copyIcalUrl() {
    const input = document.getElementById('ical-url');
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = event.currentTarget;
        const orig = btn.textContent;
        btn.textContent = 'Copiado!';
        btn.classList.add('bg-green-600');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('bg-green-600'); }, 2000);
    }).catch(() => {
        input.select();
        document.execCommand('copy');
    });
}

function toggleSync(integrationId, enabled) {
    fetch(`<?= url('settings/calendar/integrations/') ?>${integrationId}/toggle`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
        body: JSON.stringify({ sync_enabled: enabled }),
    }).catch(() => location.reload());
}
</script>
