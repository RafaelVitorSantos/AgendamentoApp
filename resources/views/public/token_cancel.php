<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancelar Agendamento</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Cancelar Agendamento</h1>
        </div>

        <div class="bg-gray-50 rounded-xl p-4 mb-6 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Data</span>
                <span class="font-medium"><?= date('d/m/Y', strtotime($appointment['date'])) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Horário</span>
                <span class="font-medium"><?= substr($appointment['start_time'], 0, 5) ?> – <?= substr($appointment['end_time'], 0, 5) ?></span>
            </div>
        </div>

        <p class="text-gray-600 text-sm text-center mb-6">
            Tem certeza que deseja cancelar este agendamento? Esta ação não pode ser desfeita.
        </p>

        <form method="POST" action="<?= htmlspecialchars(url("booking/cancel/{$token}")) ?>">
            <?= csrf_field() ?>
            <button type="submit"
                    class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-3 rounded-xl transition-colors">
                Confirmar Cancelamento
            </button>
        </form>

        <a href="<?= htmlspecialchars(url("booking/reschedule/{$token}")) ?>"
           class="block text-center mt-3 text-indigo-600 hover:text-indigo-700 text-sm font-medium">
            Prefere remarcar?
        </a>
    </div>
</body>
</html>
