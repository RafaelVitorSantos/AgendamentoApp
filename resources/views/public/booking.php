<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Agendar') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: {
                brand: {
                    50: '#EEF2FF', 100: '#E0E7FF', 500: '#6366F1', 600: '#4F46E5', 700: '#4338CA'
                }
            }}}
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full bg-gray-50">

<?php
$companyName  = htmlspecialchars($tenant['trade_name'] ?: $tenant['company_name']);
$primaryColor = $tenant['primary_color'] ?? '#4F46E5';
$slug         = $tenant['slug'];
$services     = $services ?? [];
$units        = $units ?? [];
?>

<!-- Header da empresa -->
<header class="bg-white shadow-sm">
    <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 flex items-center gap-3">
        <?php if ($tenant['logo_url']): ?>
            <img src="<?= htmlspecialchars($tenant['logo_url']) ?>" alt="Logo" class="h-10 w-10 rounded-full object-cover">
        <?php else: ?>
            <div class="h-10 w-10 rounded-full flex items-center justify-center text-white font-bold text-lg"
                 style="background-color: <?= htmlspecialchars($primaryColor) ?>">
                <?= strtoupper(substr($companyName, 0, 1)) ?>
            </div>
        <?php endif; ?>
        <div>
            <h1 class="text-lg font-bold text-gray-900"><?= $companyName ?></h1>
            <p class="text-xs text-gray-500">Agendamento Online</p>
        </div>
    </div>
</header>

<main class="mx-auto max-w-3xl px-4 py-8 sm:px-6" x-data="bookingApp('<?= $slug ?>')">

    <!-- Breadcrumb / steps -->
    <div class="flex items-center gap-2 mb-8 text-sm">
        <template x-for="(stepName, idx) in ['Serviço', 'Profissional', 'Data & Hora', 'Confirmar']" :key="idx">
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <span :class="step > idx + 1 ? 'bg-green-500 text-white' : (step === idx + 1 ? 'bg-brand-600 text-white' : 'bg-gray-200 text-gray-600')"
                          class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold">
                        <template x-if="step > idx + 1">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        </template>
                        <template x-if="step <= idx + 1">
                            <span x-text="idx + 1"></span>
                        </template>
                    </span>
                    <span :class="step === idx + 1 ? 'text-brand-600 font-semibold' : (step > idx + 1 ? 'text-green-600' : 'text-gray-400')"
                          x-text="stepName" class="hidden sm:block"></span>
                </div>
                <span x-show="idx < 3" class="text-gray-300">›</span>
            </div>
        </template>
    </div>

    <!-- PASSO 1: Serviço -->
    <div x-show="step === 1" class="space-y-4">
        <h2 class="text-xl font-semibold text-gray-900">Escolha o Serviço</h2>

        <div x-show="loading" class="text-center py-8 text-gray-500">Carregando...</div>

        <?php
        $byCategory = [];
        foreach ($services as $svc) {
            $cat = $svc['category_name'] ?? 'Outros';
            $byCategory[$cat][] = $svc;
        }
        ?>

        <?php if (empty($services)): ?>
            <div class="rounded-xl bg-white p-8 text-center shadow-sm border border-gray-100">
                <p class="text-gray-500">Nenhum serviço disponível para agendamento online.</p>
            </div>
        <?php else: ?>
            <?php foreach ($byCategory as $cat => $svcs): ?>
                <?php if (count($byCategory) > 1): ?>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($cat) ?></h3>
                <?php endif; ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <?php foreach ($svcs as $svc): ?>
                        <button @click="selectService(<?= htmlspecialchars(json_encode($svc)) ?>)"
                                :class="selected.service?.id === <?= (int) $svc['id'] ?> ? 'ring-2 ring-brand-600 bg-brand-50' : 'bg-white hover:bg-gray-50'"
                                class="w-full text-left rounded-xl border border-gray-200 p-4 transition-all shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($svc['name']) ?></p>
                                    <?php if ($svc['description']): ?>
                                        <p class="mt-0.5 text-xs text-gray-500"><?= htmlspecialchars($svc['description']) ?></p>
                                    <?php endif; ?>
                                    <p class="mt-1.5 text-xs text-gray-500">⏱ <?= (int) $svc['duration_minutes'] ?> min</p>
                                </div>
                                <span class="flex-shrink-0 text-sm font-bold text-gray-900">
                                    <?= $svc['price'] > 0 ? 'R$ ' . number_format((float)$svc['price'], 2, ',', '.') : 'A consultar' ?>
                                </span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (count($units) > 1): ?>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Unidade</label>
                <select x-model="selected.unit_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Qualquer unidade</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> — <?= htmlspecialchars($u['address_city'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" x-data x-init="$store" :value="selected.unit_id = <?= (int) ($units[0]['id'] ?? 0) ?>">
        <?php endif; ?>

        <div class="flex justify-end">
            <button @click="goStep(2)" :disabled="!selected.service"
                    :class="selected.service ? 'bg-brand-600 hover:bg-brand-500 text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                    class="rounded-lg px-6 py-2.5 text-sm font-semibold transition-colors">
                Próximo →
            </button>
        </div>
    </div>

    <!-- PASSO 2: Profissional -->
    <div x-show="step === 2" class="space-y-4">
        <h2 class="text-xl font-semibold text-gray-900">Escolha o Profissional</h2>

        <div x-show="loading" class="text-center py-8 text-gray-500">Carregando profissionais...</div>

        <div x-show="!loading && professionals.length === 0" class="rounded-xl bg-white p-8 text-center shadow-sm border border-gray-100">
            <p class="text-gray-500">Nenhum profissional disponível para este serviço.</p>
        </div>

        <div x-show="!loading" class="grid gap-3 sm:grid-cols-2">
            <template x-for="prof in professionals" :key="prof.id">
                <button @click="selectProfessional(prof)"
                        :class="selected.professional?.id === prof.id ? 'ring-2 ring-brand-600 bg-brand-50' : 'bg-white hover:bg-gray-50'"
                        class="text-left rounded-xl border border-gray-200 p-4 transition-all shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-white text-sm font-bold"
                             :style="'background-color: ' + (prof.color || '#6366F1')">
                            <span x-text="prof.name.substring(0,2).toUpperCase()"></span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900" x-text="prof.name"></p>
                            <p x-show="prof.bio" class="text-xs text-gray-500" x-text="prof.bio"></p>
                        </div>
                    </div>
                </button>
            </template>
        </div>

        <!-- Opção "qualquer profissional" -->
        <button x-show="!loading && professionals.length > 0"
                @click="selectProfessional({id: 0, name: 'Qualquer profissional', color: '#9CA3AF'})"
                :class="selected.professional?.id === 0 ? 'ring-2 ring-brand-600 bg-brand-50' : 'bg-white hover:bg-gray-50'"
                class="w-full text-left rounded-xl border border-gray-200 border-dashed p-4 transition-all text-sm text-gray-600">
            ✦ Sem preferência — aceito qualquer profissional disponível
        </button>

        <div class="flex gap-3">
            <button @click="step = 1" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">← Voltar</button>
            <button @click="goStep(3)" :disabled="!selected.professional"
                    :class="selected.professional ? 'bg-brand-600 hover:bg-brand-500 text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                    class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors">Próximo →</button>
        </div>
    </div>

    <!-- PASSO 3: Data e Hora -->
    <div x-show="step === 3" class="space-y-4">
        <h2 class="text-xl font-semibold text-gray-900">Escolha a Data e Hora</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                <input type="date" x-model="selected.date" :min="today" @change="loadSlots()"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div x-show="selected.date">
            <p class="text-sm font-medium text-gray-700 mb-2">Horários disponíveis:</p>
            <div x-show="loading" class="text-sm text-gray-500">Verificando disponibilidade...</div>
            <div x-show="!loading && slots.length === 0 && selected.date" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
                Nenhum horário disponível nesta data. Tente outra data.
            </div>
            <div x-show="!loading && slots.length > 0" class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                <template x-for="slot in slots" :key="slot">
                    <button @click="selected.start_time = slot"
                            :class="selected.start_time === slot ? 'bg-brand-600 text-white ring-2 ring-brand-500' : 'bg-white text-gray-700 hover:bg-brand-50 hover:text-brand-600'"
                            class="rounded-lg border border-gray-200 px-2 py-2 text-sm font-medium text-center transition-colors"
                            x-text="slot"></button>
                </template>
            </div>
        </div>

        <div class="flex gap-3">
            <button @click="step = 2" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">← Voltar</button>
            <button @click="goStep(4)" :disabled="!selected.date || !selected.start_time"
                    :class="(selected.date && selected.start_time) ? 'bg-brand-600 hover:bg-brand-500 text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'"
                    class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors">Próximo →</button>
        </div>
    </div>

    <!-- PASSO 4: Confirmar -->
    <div x-show="step === 4" class="space-y-4">
        <h2 class="text-xl font-semibold text-gray-900">Confirmar Agendamento</h2>

        <!-- Resumo -->
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-5 space-y-3">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Resumo</h3>
            <div class="grid grid-cols-2 gap-y-2 text-sm">
                <span class="text-gray-500">Serviço:</span>
                <span class="font-medium text-gray-900" x-text="selected.service?.name"></span>
                <span class="text-gray-500">Profissional:</span>
                <span class="font-medium text-gray-900" x-text="selected.professional?.name"></span>
                <span class="text-gray-500">Data:</span>
                <span class="font-medium text-gray-900" x-text="formatDate(selected.date)"></span>
                <span class="text-gray-500">Horário:</span>
                <span class="font-medium text-gray-900" x-text="selected.start_time"></span>
                <span class="text-gray-500">Duração:</span>
                <span class="font-medium text-gray-900" x-text="selected.service?.duration_minutes + ' min'"></span>
                <span class="text-gray-500">Valor:</span>
                <span class="font-bold text-brand-600" x-text="selected.service?.price > 0 ? 'R$ ' + parseFloat(selected.service.price).toFixed(2).replace('.', ',') : 'A consultar'"></span>
            </div>
        </div>

        <!-- Form de dados -->
        <form action="<?= htmlspecialchars(url('book/' . $slug)) ?>" method="post" class="rounded-xl bg-white shadow-sm border border-gray-100 p-5 space-y-4">
            <input type="hidden" name="service_id" :value="selected.service?.id">
            <input type="hidden" name="professional_id" :value="selected.professional?.id">
            <input type="hidden" name="unit_id" :value="selected.unit_id">
            <input type="hidden" name="date" :value="selected.date">
            <input type="hidden" name="start_time" :value="selected.start_time">

            <h3 class="text-sm font-semibold text-gray-700">Seus dados</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo <span class="text-red-500">*</span></label>
                    <input type="text" name="client_name" required placeholder="Seu nome"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp / Telefone <span class="text-red-500">*</span></label>
                    <input type="tel" name="client_phone" required placeholder="(11) 99999-9999"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="client_email" placeholder="seu@email.com"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                <textarea name="notes" rows="2" placeholder="Alguma informação importante?"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm resize-none"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" @click="step = 3"
                        class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">← Voltar</button>
                <button type="submit"
                        class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors"
                        style="background-color: <?= htmlspecialchars($primaryColor) ?>">
                    Confirmar Agendamento ✓
                </button>
            </div>
        </form>
    </div>

</main>

<!-- Footer -->
<footer class="mt-12 border-t border-gray-200 py-4 text-center text-xs text-gray-400">
    Agendamento via <strong>AgendaPRO</strong> &mdash; Plataforma de Agendamento Online
</footer>

<script>
function bookingApp(slug) {
    return {
        step: 1,
        loading: false,
        slug: slug,
        today: new Date().toISOString().split('T')[0],
        selected: {
            service: null,
            professional: null,
            unit_id: <?= (int) ($units[0]['id'] ?? 0) ?>,
            date: '',
            start_time: '',
        },
        professionals: [],
        slots: [],

        async selectService(svc) {
            this.selected.service = svc;
            this.selected.professional = null;
            this.selected.start_time = '';
        },

        async goStep(n) {
            if (n === 2 && this.selected.service) {
                await this.loadProfessionals();
            }
            this.step = n;
        },

        async loadProfessionals() {
            this.loading = true;
            try {
                const r = await fetch(`<?= url('book/') ?>${this.slug}/api/professionals?service_id=` + this.selected.service.id);
                this.professionals = await r.json();
            } catch(e) { this.professionals = []; }
            this.loading = false;
        },

        selectProfessional(prof) {
            this.selected.professional = prof;
            this.selected.start_time = '';
        },

        async loadSlots() {
            if (!this.selected.date || !this.selected.professional || !this.selected.service) return;
            this.loading = true;
            this.slots = [];
            try {
                const params = new URLSearchParams({
                    service_id: this.selected.service.id,
                    professional_id: this.selected.professional.id || 0,
                    date: this.selected.date,
                    unit_id: this.selected.unit_id || 0,
                });
                const r = await fetch(`<?= url('book/') ?>${this.slug}/api/slots?` + params);
                const data = await r.json();
                this.slots = data.slots || [];
            } catch(e) { this.slots = []; }
            this.loading = false;
        },

        formatDate(d) {
            if (!d) return '';
            const [y, m, day] = d.split('-');
            return `${day}/${m}/${y}`;
        },
    }
}
</script>
</body>
</html>
