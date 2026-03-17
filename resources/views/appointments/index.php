<?php
$appointments  = $appointments ?? [];
$professionals = $professionals ?? [];
$currentDate   = $currentDate ?? date('Y-m-d');
$pageTitle     = $pageTitle ?? 'Agenda';

$statusLabels = [
    'scheduled'             => 'Agendado',
    'confirmed'             => 'Confirmado',
    'in_progress'           => 'Em atendimento',
    'completed'             => 'Concluído',
    'no_show'               => 'Falta',
    'cancelled_by_client'   => 'Cancelado (cliente)',
    'cancelled_by_business' => 'Cancelado',
];
$statusColors = [
    'scheduled'             => 'bg-yellow-100 text-yellow-800',
    'confirmed'             => 'bg-blue-100 text-blue-800',
    'in_progress'           => 'bg-purple-100 text-purple-800',
    'completed'             => 'bg-green-100 text-green-800',
    'no_show'               => 'bg-red-100 text-red-800',
    'cancelled_by_client'   => 'bg-gray-100 text-gray-700',
    'cancelled_by_business' => 'bg-gray-100 text-gray-700',
];
?>

<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/index.global.min.css">

<div class="space-y-4" x-data="appointmentsPage()">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Agenda</h1>
        <div class="flex items-center gap-2">
            <a href="<?= url('schedule-blocks') ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                Bloquear horário
            </a>
            <a href="<?= url('appointments/create') ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
                <svg class="-ml-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Novo Agendamento
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6">
            <button @click="activeTab = 'calendar'" :class="activeTab === 'calendar' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                Calendário
            </button>
            <button @click="activeTab = 'list'" :class="activeTab === 'list' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-2 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                Lista
            </button>
        </nav>
    </div>

    <!-- Filtro de Profissional (compartilhado) -->
    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-3 shadow-sm border border-gray-100">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">Profissional:</label>
            <select x-model="selectedProfessional" @change="onProfessionalChange()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">Todos</option>
                <?php foreach ($professionals as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- View mode buttons (only for calendar tab) -->
        <template x-if="activeTab === 'calendar'">
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button @click="changeView('dayGridMonth')" :class="calView === 'dayGridMonth' ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1.5 font-medium transition-colors">Mês</button>
                <button @click="changeView('timeGridWeek')" :class="calView === 'timeGridWeek' ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1.5 font-medium border-l border-gray-200 transition-colors">Semana</button>
                <button @click="changeView('timeGridDay')" :class="calView === 'timeGridDay' ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1.5 font-medium border-l border-gray-200 transition-colors">Dia</button>
                <button @click="changeView('listWeek')" :class="calView === 'listWeek' ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                        class="px-3 py-1.5 font-medium border-l border-gray-200 transition-colors">Semana lista</button>
            </div>
        </template>
        <!-- Date filter (only for list tab) -->
        <template x-if="activeTab === 'list'">
            <form method="get" action="<?= url('appointments') ?>" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="list">
                <input type="hidden" name="professional_id" :value="selectedProfessional">
                <label class="text-sm font-medium text-gray-700">Data:</label>
                <input type="date" name="date" value="<?= e($currentDate) ?>"
                       class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500">
                <button type="submit" class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200">Filtrar</button>
            </form>
        </template>
    </div>

    <!-- CALENDÁRIO FullCalendar -->
    <div x-show="activeTab === 'calendar'" class="rounded-xl bg-white shadow-sm border border-gray-100 p-4">
        <div id="calendar" style="min-height: 600px;"></div>
    </div>

    <!-- Modal de detalhes do evento -->
    <div x-show="eventModal.open" x-cloak @click.self="eventModal.open = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl p-6 space-y-4" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900" x-text="eventModal.title"></h3>
                <button @click="eventModal.open = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                </button>
            </div>
            <div class="space-y-2 text-sm text-gray-700">
                <div class="flex gap-2"><span class="font-medium w-28">Cliente:</span> <span x-text="eventModal.client || 'Walk-in'"></span></div>
                <div class="flex gap-2"><span class="font-medium w-28">Serviço:</span> <span x-text="eventModal.service"></span></div>
                <div class="flex gap-2"><span class="font-medium w-28">Profissional:</span> <span x-text="eventModal.professional"></span></div>
                <div class="flex gap-2"><span class="font-medium w-28">Horário:</span> <span x-text="eventModal.time"></span></div>
                <div class="flex gap-2"><span class="font-medium w-28">Status:</span>
                    <span :class="statusBadgeClass(eventModal.status)" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" x-text="statusLabel(eventModal.status)"></span>
                </div>
                <template x-if="eventModal.notes">
                    <div class="flex gap-2"><span class="font-medium w-28">Obs.:</span> <span x-text="eventModal.notes"></span></div>
                </template>
            </div>
            <div class="flex gap-2 pt-2">
                <template x-if="canAdvance(eventModal.status)">
                    <form :action="'<?= url('appointments/') ?>' + eventModal.id + '/status'" method="post" class="flex-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" :value="nextStatus(eventModal.status)">
                        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500" x-text="advanceLabel(eventModal.status)"></button>
                    </form>
                </template>
                <template x-if="canAdvance(eventModal.status)">
                    <form :action="'<?= url('appointments/') ?>' + eventModal.id + '/cancel'" method="post"
                          @submit.prevent="if(confirm('Cancelar este agendamento?')) $el.submit()">
                        <?= csrf_field() ?>
                        <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Cancelar</button>
                    </form>
                </template>
            </div>
        </div>
    </div>

    <!-- LISTA de agendamentos -->
    <div x-show="activeTab === 'list'" class="overflow-hidden rounded-xl bg-white shadow-sm border border-gray-100">
        <?php if (!empty($appointments)): ?>
            <ul class="divide-y divide-gray-100">
                <?php foreach ($appointments as $a): ?>
                    <?php
                    $st = $a['status'];
                    $canChange = !in_array($st, ['cancelled_by_client', 'cancelled_by_business', 'completed', 'no_show'], true);
                    ?>
                    <li class="flex flex-col gap-2 px-4 py-4 sm:px-6 sm:flex-row sm:items-center sm:justify-between hover:bg-gray-50/50">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white text-xs font-bold"
                                 style="background-color: <?= e($a['professional_color'] ?? '#6366F1') ?>">
                                <?= e(strtoupper(substr($a['professional_name'] ?? 'P', 0, 2))) ?>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900"><?= e($a['client_name'] ?? 'Walk-in') ?></p>
                                <p class="text-xs text-gray-500"><?= e($a['service_name']) ?> · <?= e($a['professional_name']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="text-sm font-mono font-medium text-gray-900"><?= substr($a['start_time'], 0, 5) ?> – <?= substr($a['end_time'] ?? '', 0, 5) ?></span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?= $statusColors[$st] ?? 'bg-gray-100 text-gray-700' ?>">
                                <?= $statusLabels[$st] ?? e($st) ?>
                            </span>
                            <?php if ($canChange): ?>
                                <form method="post" action="<?= url('appointments/' . $a['id'] . '/status') ?>" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status" value="<?= $st === 'scheduled' ? 'confirmed' : ($st === 'confirmed' ? 'in_progress' : 'completed') ?>">
                                    <button type="submit" class="text-sm font-medium text-brand-600 hover:text-brand-500">
                                        <?= $st === 'scheduled' ? 'Confirmar' : ($st === 'confirmed' ? 'Iniciar' : 'Concluir') ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= url('appointments/' . $a['id'] . '/cancel') ?>" class="inline"
                                      onsubmit="return confirm('Cancelar este agendamento?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-500">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="px-4 py-16 text-center sm:px-6">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900">Nenhum agendamento</h3>
                <p class="mt-1 text-sm text-gray-500">Não há agendamentos para esta data.</p>
                <div class="mt-4">
                    <a href="<?= url('appointments/create') ?>" class="inline-flex items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">
                        + Novo Agendamento
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.11/locales/pt-br.global.min.js"></script>

<script>
function appointmentsPage() {
    return {
        activeTab: '<?= (($_GET['tab'] ?? '') === 'list') ? 'list' : 'calendar' ?>',
        selectedProfessional: '<?= (int)($_GET['professional_id'] ?? 0) ?: '' ?>',
        calView: 'timeGridWeek',
        calendar: null,
        eventModal: {
            open: false, id: null, title: '', client: '', service: '',
            professional: '', time: '', status: '', notes: ''
        },

        init() {
            this.$nextTick(() => this.initCalendar());
        },

        initCalendar() {
            const el = document.getElementById('calendar');
            if (!el) return;

            this.calendar = new FullCalendar.Calendar(el, {
                locale: 'pt-br',
                initialView: 'timeGridWeek',
                initialDate: '<?= $currentDate ?>',
                headerToolbar: {
                    left:   'prev,next today',
                    center: 'title',
                    right:  ''
                },
                slotMinTime: '06:00:00',
                slotMaxTime: '23:00:00',
                allDaySlot: false,
                height: 'auto',
                nowIndicator: true,
                eventClick: (info) => this.openEventModal(info.event),
                dateClick: (info) => {
                    window.location.href = '<?= url('appointments/create') ?>?date=' + info.dateStr.split('T')[0];
                },
                events: (fetchInfo, successCallback, failureCallback) => {
                    const params = new URLSearchParams({
                        start: fetchInfo.startStr.split('T')[0],
                        end:   fetchInfo.endStr.split('T')[0],
                    });
                    if (this.selectedProfessional) {
                        params.append('professional_id', this.selectedProfessional);
                    }
                    fetch('<?= url('api/appointments/events') ?>?' + params)
                        .then(r => r.json())
                        .then(data => successCallback(data))
                        .catch(() => failureCallback());
                },
                eventDidMount(info) {
                    const status = info.event.extendedProps.status;
                    if (['cancelled_by_client','cancelled_by_business','no_show'].includes(status)) {
                        info.el.style.opacity = '0.5';
                        info.el.style.textDecoration = 'line-through';
                    }
                },
                buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia', list: 'Lista' },
                noEventsText: 'Nenhum agendamento',
            });
            this.calendar.render();
        },

        changeView(view) {
            this.calView = view;
            if (this.calendar) this.calendar.changeView(view);
        },

        onProfessionalChange() {
            if (this.calendar) this.calendar.refetchEvents();
        },

        openEventModal(event) {
            const p = event.extendedProps;
            const start = event.start ? event.start.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'}) : '';
            const end   = event.end   ? event.end.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'}) : '';
            this.eventModal = {
                open: true,
                id: event.id,
                title: event.title,
                client: p.client,
                service: p.service,
                professional: p.professional,
                time: start + (end ? ' – ' + end : ''),
                status: p.status,
                notes: p.notes,
            };
        },

        canAdvance(status) {
            return ['scheduled','confirmed','in_progress'].includes(status);
        },

        nextStatus(status) {
            const map = { scheduled: 'confirmed', confirmed: 'in_progress', in_progress: 'completed' };
            return map[status] || '';
        },

        advanceLabel(status) {
            const map = { scheduled: 'Confirmar', confirmed: 'Iniciar atendimento', in_progress: 'Concluir' };
            return map[status] || 'Avançar';
        },

        statusLabel(status) {
            const labels = {
                scheduled: 'Agendado', confirmed: 'Confirmado', in_progress: 'Em atendimento',
                completed: 'Concluído', no_show: 'Falta',
                cancelled_by_client: 'Cancelado (cliente)', cancelled_by_business: 'Cancelado',
            };
            return labels[status] || status;
        },

        statusBadgeClass(status) {
            const map = {
                scheduled: 'bg-yellow-100 text-yellow-800',
                confirmed: 'bg-blue-100 text-blue-800',
                in_progress: 'bg-purple-100 text-purple-800',
                completed: 'bg-green-100 text-green-800',
                no_show: 'bg-red-100 text-red-800',
                cancelled_by_client: 'bg-gray-100 text-gray-700',
                cancelled_by_business: 'bg-gray-100 text-gray-700',
            };
            return map[status] || 'bg-gray-100 text-gray-700';
        },
    }
}
</script>
