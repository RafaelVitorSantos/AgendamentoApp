<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remarcar Agendamento</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg p-8 max-w-lg w-full">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Remarcar Agendamento</h1>
            <p class="text-gray-500 text-sm mt-1">Agendamento atual: <?= date('d/m/Y', strtotime($appointment['date'])) ?> às <?= substr($appointment['start_time'], 0, 5) ?></p>
        </div>

        <?php if (empty($slots)): ?>
            <div class="text-center py-8">
                <p class="text-gray-500">Não há horários disponíveis nos próximos 14 dias.</p>
                <p class="text-gray-400 text-sm mt-2">Entre em contato conosco para remarcar.</p>
            </div>
        <?php else: ?>
            <form method="POST" action="<?= htmlspecialchars(url("booking/reschedule/{$token}")) ?>" id="rescheduleForm">
                <?= csrf_field() ?>

                <div class="space-y-4 max-h-80 overflow-y-auto pr-1">
                    <?php foreach ($slots as $date => $daySlots): ?>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                                <?= date('d/m/Y (l)', strtotime($date)) ?>
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                <?php foreach ($daySlots as $slot): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="date" value="<?= htmlspecialchars($date) ?>"
                                               data-start="<?= htmlspecialchars($slot['start']) ?>"
                                               class="sr-only slot-radio">
                                        <span class="slot-btn block text-center py-2 px-3 border-2 border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:border-indigo-400 hover:bg-indigo-50 transition-colors">
                                            <?= htmlspecialchars($slot['start']) ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="start_time" id="selectedStart">

                <button type="submit" id="submitBtn"
                        class="mt-6 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                    Confirmar Remarcação
                </button>
            </form>

            <script>
            document.querySelectorAll('.slot-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('border-indigo-500','bg-indigo-50'));
                    this.nextElementSibling.classList.add('border-indigo-500','bg-indigo-50');
                    document.getElementById('selectedStart').value = this.dataset.start + ':00';
                    document.getElementById('submitBtn').disabled = false;
                });
            });
            </script>
        <?php endif; ?>

        <a href="<?= htmlspecialchars(url("booking/cancel/{$token}")) ?>"
           class="block text-center mt-4 text-red-500 hover:text-red-600 text-sm">
            Prefere cancelar?
        </a>
    </div>
</body>
</html>
