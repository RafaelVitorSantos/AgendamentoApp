<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento Confirmado — <?= htmlspecialchars($tenant['trade_name'] ?: $tenant['company_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full bg-gray-50 flex items-center justify-center py-12 px-4">

<?php
$companyName  = htmlspecialchars($tenant['trade_name'] ?: $tenant['company_name']);
$primaryColor = $tenant['primary_color'] ?? '#4F46E5';
$a            = $appointment ?? null;
?>

<div class="mx-auto max-w-md w-full space-y-6 text-center">

    <!-- Ícone de sucesso -->
    <div class="flex justify-center">
        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
            <svg class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
        </div>
    </div>

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Agendamento Confirmado!</h1>
        <p class="mt-2 text-sm text-gray-500">Seu horário foi reservado com sucesso em <strong><?= $companyName ?></strong>.</p>
    </div>

    <?php if ($a): ?>
    <div class="rounded-2xl bg-white shadow-sm border border-gray-100 p-6 text-left space-y-3">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Detalhes do Agendamento</h2>
        <div class="space-y-2">
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                <span class="text-sm text-gray-900"><?= htmlspecialchars($a['service_name'] ?? '') ?></span>
            </div>
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                <span class="text-sm text-gray-900"><?= htmlspecialchars($a['professional_name'] ?? '') ?></span>
            </div>
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                <span class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($a['date'] ?? 'now')) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <svg class="h-5 w-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span class="text-sm text-gray-900"><?= substr($a['start_time'] ?? '', 0, 5) ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
        Guarde este horário! Em breve você pode receber uma confirmação via WhatsApp.
    </div>

    <a href="<?= htmlspecialchars(url('book/' . $tenant['slug'])) ?>"
       class="inline-flex items-center rounded-lg px-6 py-2.5 text-sm font-semibold text-white transition-colors"
       style="background-color: <?= htmlspecialchars($primaryColor) ?>">
        ← Fazer outro agendamento
    </a>
</div>

</body>
</html>
