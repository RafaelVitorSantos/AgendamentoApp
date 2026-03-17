# AgendaPRO SaaS — Documento de Arquitetura Completo

## Plataforma SaaS de Agendamento Online Multi-Tenant

**Versão:** 1.0  
**Data:** 2026-03-12  
**Stack:** PHP 8.2+ · MySQL 8.0+ · HTML5 · CSS3 · JavaScript ES6+

---

# 1. VISÃO GERAL DO PRODUTO

## 1.1 O que é o AgendaPRO

O **AgendaPRO** é uma plataforma SaaS (Software as a Service) de agendamento online projetada para atender empresas de serviços com agenda e profissionais — barbearias, clínicas, salões de beleza, estúdios e qualquer negócio que dependa de agendamento de horários.

A plataforma permite que **múltiplas empresas** (tenants) utilizem o mesmo sistema simultaneamente, cada uma com total isolamento de dados, personalização de marca e gestão independente.

## 1.2 Proposta de Valor

| Aspecto | Descrição |
|---------|-----------|
| **Para o dono do negócio** | Gestão completa de agenda, equipe, clientes e financeiro em uma única plataforma |
| **Para o profissional** | Visão clara da agenda, comissões e avaliações |
| **Para o cliente final** | Agendamento online 24/7 com confirmação automática via WhatsApp |
| **Como SaaS** | Receita recorrente (MRR), onboarding automatizado, escalável para milhares de empresas |

## 1.3 Modelo de Negócio

```
┌─────────────────────────────────────────────────────────┐
│                    AgendaPRO SaaS                       │
│                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │
│  │  Plano   │  │  Plano   │  │     Plano            │  │
│  │ Gratuito │  │  Básico  │  │   Profissional       │  │
│  │  R$0/mês │  │ R$79/mês │  │    R$199/mês         │  │
│  │          │  │          │  │                      │  │
│  │ 1 prof.  │  │ 5 prof.  │  │  Ilimitado           │  │
│  │ 50 agend │  │ 500 agend│  │  Ilimitado           │  │
│  │ 1 unid.  │  │ 2 unid.  │  │  10 unidades         │  │
│  │ Sem relat│  │ Básicos  │  │  Completos           │  │
│  │ Sem autom│  │ WhatsApp │  │  WhatsApp+Fidelidade │  │
│  └──────────┘  └──────────┘  └──────────────────────┘  │
│                                                         │
│  Empresa A ──┐                                          │
│  Empresa B ──┤── Banco Compartilhado + tenant_id        │
│  Empresa C ──┘                                          │
└─────────────────────────────────────────────────────────┘
```

## 1.4 Fluxos Principais

### Fluxo de Onboarding
```
Empresa acessa site → Clica em "Criar Conta" → Preenche dados da empresa
→ Cria administrador → Seleciona plano → Wizard de configuração
→ Cadastra serviços → Cadastra profissionais → Define horários
→ Pronto para usar
```

### Fluxo de Agendamento (pelo cliente)
```
Cliente acessa link da empresa → Seleciona unidade → Seleciona serviço
→ Seleciona profissional → Escolhe data/hora → Confirma dados
→ Recebe confirmação WhatsApp → Lembrete automático 24h antes
```

### Fluxo de Atendimento (pela empresa)
```
Dashboard mostra agenda do dia → Profissional visualiza seus horários
→ Cliente chega → Marca como "em atendimento" → Finaliza
→ Registra pagamento → Calcula comissão → Solicita avaliação
```

---

# 2. ARQUITETURA MULTI-TENANT

## 2.1 Análise dos Modelos

### Modelo 1: Banco Compartilhado + tenant_id (ESCOLHIDO)

| Aspecto | Avaliação |
|---------|-----------|
| Complexidade | Baixa |
| Custo de infraestrutura | Baixo |
| Escalabilidade | Alta (até milhares de tenants) |
| Isolamento | Lógico (via coluna tenant_id) |
| Manutenção | Simples — um único schema para migrar |
| Backup | Centralizado |
| Onboarding | Instantâneo — apenas INSERT |

### Modelo 2: Schema por Tenant

| Aspecto | Avaliação |
|---------|-----------|
| Complexidade | Média |
| Custo | Médio |
| Escalabilidade | Limitada (~500 schemas no MySQL) |
| Isolamento | Forte |
| Manutenção | Complexa — migrar N schemas |
| Onboarding | Lento — criar schema + tabelas |

### Modelo 3: Banco por Tenant

| Aspecto | Avaliação |
|---------|-----------|
| Complexidade | Alta |
| Custo | Alto |
| Escalabilidade | Muito limitada |
| Isolamento | Total |
| Manutenção | Muito complexa |
| Onboarding | Muito lento |

## 2.2 Decisão: Banco Compartilhado + tenant_id

**Justificativa técnica:**

1. **Custo-eficiência:** Um único banco MySQL atende milhares de empresas. O custo de infraestrutura é proporcional ao uso real, não ao número de tenants.

2. **Onboarding instantâneo:** Criar uma nova empresa é apenas um `INSERT INTO tenants`. Não há provisionamento de infraestrutura.

3. **Manutenção simples:** Migrações de schema aplicam-se uma única vez. Não há necessidade de loops por N databases.

4. **Escalabilidade comprovada:** Plataformas como Shopify e Salesforce usam este modelo. Com índices compostos `(tenant_id, ...)` e particionamento, suporta milhões de registros.

5. **Relatórios cross-tenant:** O admin da plataforma pode gerar métricas globais com queries simples.

## 2.3 Identificação do Tenant

```
┌─────────────────────────────────────────────────┐
│              Requisição HTTP                     │
│                                                  │
│  1. Login → JWT contém tenant_id                 │
│  2. Sessão → $_SESSION['tenant_id']              │
│  3. Subdomínio → barbearia-x.agendapro.com.br   │
│  4. Header → X-Tenant-ID (para APIs)             │
└─────────────────────────────────────────────────┘
```

**Estratégia primária:** O `tenant_id` é armazenado no token JWT e na sessão do usuário. Toda query ao banco inclui `WHERE tenant_id = ?`.

**Estratégia secundária (link público):** Para a página de agendamento público, o tenant é identificado pelo slug na URL: `agendapro.com.br/barbearia-do-joao`.

## 2.4 Isolamento de Dados — Camadas de Proteção

### Camada 1: Middleware de Tenant (Request Level)
```php
// Toda requisição autenticada passa por este middleware
class TenantMiddleware {
    public function handle(Request $request): void {
        $tenantId = $request->session()->get('tenant_id');
        if (!$tenantId) {
            throw new UnauthorizedException();
        }
        TenantContext::set($tenantId);
    }
}
```

### Camada 2: Repository Base (Query Level)
```php
// Todo repository herda desta classe
abstract class BaseRepository {
    protected function scopedQuery(string $table): string {
        $tenantId = TenantContext::get();
        return "SELECT * FROM {$table} WHERE tenant_id = ?";
    }

    protected function insert(string $table, array $data): int {
        $data['tenant_id'] = TenantContext::get();
        // ... executa INSERT com tenant_id obrigatório
    }
}
```

### Camada 3: Constraint no Banco (Database Level)
```sql
-- Toda tabela sensível tem tenant_id NOT NULL
ALTER TABLE agendamentos
ADD CONSTRAINT chk_tenant CHECK (tenant_id IS NOT NULL);

-- Índice composto garante performance
CREATE INDEX idx_agendamentos_tenant
ON agendamentos(tenant_id, data_hora, profissional_id);
```

### Camada 4: Auditoria (Monitoring Level)
```
- Log de toda query que retorna dados
- Alerta se query sem tenant_id for executada em tabela sensível
- Revisão periódica de queries lentas
```

## 2.5 Escalabilidade

```
Fase 1 (0-1.000 tenants):
  └── 1 servidor MySQL, 1 servidor PHP

Fase 2 (1.000-10.000 tenants):
  └── MySQL com read replicas
  └── Redis para cache e sessões
  └── 2+ servidores PHP atrás de load balancer

Fase 3 (10.000+ tenants):
  └── Sharding por faixa de tenant_id
  └── Shard 1: tenant_id 1-10000
  └── Shard 2: tenant_id 10001-20000
  └── Connection pooling com ProxySQL
  └── Auto-scaling de servidores PHP
```

---

# 3. MÓDULOS DO SISTEMA

## 3.1 Autenticação e Contas

### Funcionalidades
- Cadastro de empresa (onboarding completo)
- Criação automática do administrador
- Login com email/senha
- Recuperação de senha por email (token temporário)
- Sessão com JWT (API) + session nativa PHP (web)
- Controle de permissões RBAC (Role-Based Access Control)

### Perfis (Roles)

| Role | Escopo | Permissões |
|------|--------|------------|
| `super_admin` | Plataforma | Acesso total à plataforma SaaS |
| `tenant_admin` | Empresa | Gestão completa da empresa |
| `manager` | Unidade | Gestão da unidade |
| `professional` | Própria agenda | Visualizar/gerenciar própria agenda |
| `receptionist` | Unidade | Agendar, gerenciar fila |
| `client` | Próprio histórico | Agendar, visualizar histórico |

### Sistema de Permissões Granulares
```
permissions:
  - appointments.view
  - appointments.create
  - appointments.edit
  - appointments.cancel
  - clients.view
  - clients.create
  - clients.edit
  - clients.delete
  - financial.view
  - financial.create
  - financial.reports
  - reports.view
  - settings.manage
  - professionals.manage
  - services.manage
  - units.manage
```

## 3.2 Gestão da Empresa

### Unidades (Filiais)
- Nome, endereço, telefone, email
- Horários de funcionamento por dia da semana
- Intervalos (almoço, etc.)
- Feriados personalizados + nacionais
- Timezone individual
- Status: ativo/inativo

### Profissionais
- Vinculado à empresa (tenant)
- Pode atuar em múltiplas unidades
- Especialidades (N:N com serviços)
- Horário próprio (pode diferir do horário da unidade)
- Intervalos pessoais
- Comissão configurável por serviço
- Foto, bio, cor na agenda

### Serviços
- Nome, descrição
- Categoria
- Duração (em minutos)
- Preço
- Comissão (% ou valor fixo)
- Status: ativo/inativo
- Agendável online: sim/não
- Exige profissional específico: sim/não

### Horários de Funcionamento
```
┌─────────────────────────────────────────┐
│  Segunda    08:00 - 12:00  14:00-20:00  │
│  Terça      08:00 - 12:00  14:00-20:00  │
│  Quarta     08:00 - 12:00  14:00-20:00  │
│  Quinta     08:00 - 12:00  14:00-20:00  │
│  Sexta      08:00 - 12:00  14:00-20:00  │
│  Sábado     08:00 - 14:00               │
│  Domingo    FECHADO                     │
└─────────────────────────────────────────┘
```

## 3.3 Agenda

### Motor de Disponibilidade
```
Slots disponíveis = (Horário da unidade ∩ Horário do profissional)
                  - Agendamentos existentes
                  - Bloqueios manuais
                  - Feriados
                  - Intervalos
```

### Controle de Conflitos
```php
// Pseudo-código do algoritmo de verificação
function isSlotAvailable($professionalId, $startTime, $duration) {
    $endTime = $startTime + $duration;

    // 1. Verificar se está dentro do horário de funcionamento
    // 2. Verificar se não é feriado
    // 3. Verificar se não é intervalo
    // 4. Verificar conflito com agendamentos existentes
    $conflict = SELECT COUNT(*) FROM agendamentos
        WHERE profissional_id = ?
        AND status NOT IN ('cancelado', 'nao_compareceu')
        AND (
            (inicio < $endTime AND fim > $startTime)
        );

    return $conflict === 0;
}
```

### Status do Agendamento
```
agendado → confirmado → em_atendimento → finalizado
    │           │                            │
    ├→ cancelado_cliente                     └→ cobrado
    ├→ cancelado_empresa
    ├→ remarcado
    └→ nao_compareceu
```

### Visualizações
- **Diária:** Timeline por profissional (FullCalendar resourceTimeline)
- **Semanal:** Grade semanal por profissional
- **Mensal:** Calendário com contadores
- **Lista:** Tabela filtrável e ordenável

## 3.4 Clientes

### CRM Básico
- Dados pessoais (nome, telefone, email, CPF opcional)
- Tags personalizáveis
- Observações internas
- Histórico completo de atendimentos
- Frequência de visitas (últimos 30/60/90 dias)
- Ticket médio
- Último atendimento
- Preferências (profissional favorito, serviço favorito)
- Aniversário (para campanhas)

### Métricas por Cliente
```
- Total de visitas
- Ticket médio
- Frequência (dias entre visitas)
- Taxa de faltas
- Último serviço
- Profissional favorito
- Valor total gasto
```

## 3.5 Atendimento

### Fila de Atendimento
```
┌──────────────────────────────────────────────┐
│  # │ Cliente    │ Serviço      │ Status      │
│  1 │ João Silva │ Corte        │ Atendendo   │
│  2 │ Maria O.   │ Escova       │ Aguardando  │
│  3 │ Pedro S.   │ Barba        │ Aguardando  │
│  4 │ Ana C.     │ Coloração    │ Na fila     │
└──────────────────────────────────────────────┘
```

### Status de Atendimento
```
na_fila → aguardando → em_atendimento → finalizado
              │
              └→ desistiu
```

### Walk-in (sem agendamento)
- Cliente chega sem agendamento
- Recepcionista adiciona à fila
- Sistema aloca próximo slot disponível
- Prioridade menor que agendamentos confirmados

## 3.6 Financeiro

### Entradas
- Vinculadas a agendamentos finalizados
- Formas de pagamento: dinheiro, cartão crédito, cartão débito, PIX, transferência
- Parcial ou total
- Múltiplas formas de pagamento por transação

### Comissões
```
Receita do serviço: R$ 100,00
Comissão do profissional: 40% = R$ 40,00
Receita líquida empresa: R$ 60,00
```

Modelos de comissão:
- Percentual sobre o serviço
- Valor fixo por serviço
- Escalonada (muda por faixa de faturamento)
- Por tipo de serviço

### Fluxo de Caixa
```
┌───────────────────────────────────────────┐
│           Fluxo de Caixa — Março 2026     │
│                                           │
│  Entradas previstas:    R$ 45.000,00      │
│  Entradas realizadas:   R$ 32.500,00      │
│  Saídas previstas:      R$ 18.000,00      │
│  Saídas realizadas:     R$ 12.300,00      │
│  ─────────────────────────────────        │
│  Saldo previsto:        R$ 27.000,00      │
│  Saldo atual:           R$ 20.200,00      │
└───────────────────────────────────────────┘
```

## 3.7 Relatórios

| Relatório | Descrição | Filtros |
|-----------|-----------|---------|
| Faturamento | Receita por período, serviço, profissional | Período, unidade, profissional |
| Serviços mais vendidos | Ranking de serviços | Período, unidade |
| Horários movimentados | Heatmap de horários | Período, unidade |
| Clientes recorrentes | Top clientes por frequência | Período, mínimo de visitas |
| Taxa de faltas | % de no-shows | Período, profissional |
| Tempo médio | Duração média real vs estimada | Período, serviço |
| Comissões | Comissão por profissional | Período, profissional |
| Ocupação | % de slots preenchidos | Período, profissional |

## 3.8 WhatsApp

### Integração via API (Evolution API ou Z-API)
```
┌─────────────────────────────────────────────┐
│  Gatilho              │  Mensagem            │
│───────────────────────│──────────────────────│
│  Agendamento criado   │  Confirmação         │
│  24h antes            │  Lembrete            │
│  2h antes             │  Lembrete final      │
│  Após atendimento     │  Agradecimento       │
│  Aniversário          │  Parabéns + cupom    │
│  30 dias sem visita   │  Saudade + oferta    │
└─────────────────────────────────────────────┘
```

### Templates de Mensagem
```
Olá {nome_cliente}! 👋
Seu agendamento está confirmado:
📅 {data} às {hora}
✂️ {servico} com {profissional}
📍 {endereco_unidade}

Para cancelar ou remarcar, acesse: {link}
```

## 3.9 Fidelidade

### Programa de Pontos
- X pontos por real gasto
- Pontos resgatáveis por serviços ou descontos
- Expiração configurável (ex: 90 dias)
- Extrato de pontos visível ao cliente

### Programa por Frequência
- "A cada 10 cortes, o 11º é grátis"
- Configurável por serviço ou categoria
- Cartão virtual de fidelidade

## 3.10 Avaliações

### Fluxo
```
Atendimento finalizado → WhatsApp com link de avaliação
→ Cliente avalia (1-5 estrelas) + comentário opcional
→ Métricas atualizadas no dashboard
```

### Métricas
- NPS (Net Promoter Score) por profissional
- Média de avaliação por serviço
- Tendência de satisfação (mensal)

---

# 4. FUNCIONALIDADES SAAS

## 4.1 Onboarding

### Fluxo de Registro
```
Passo 1: Dados da empresa (nome, segmento, telefone)
Passo 2: Dados do administrador (nome, email, senha)
Passo 3: Seleção do plano
Passo 4: Wizard de configuração
  └── 4a: Cadastrar primeiro serviço
  └── 4b: Cadastrar primeiro profissional
  └── 4c: Definir horários de funcionamento
Passo 5: Dashboard pronto
```

### Ações Automáticas no Registro
```
1. Criar registro na tabela tenants
2. Criar registro na tabela empresas
3. Criar unidade padrão
4. Criar usuário admin com role tenant_admin
5. Criar assinatura no plano gratuito
6. Enviar email de boas-vindas
7. Iniciar trial de 14 dias do plano profissional
```

## 4.2 Planos e Assinaturas

### Comparativo de Planos

| Recurso | Gratuito | Básico (R$79) | Profissional (R$199) |
|---------|----------|---------------|---------------------|
| Profissionais | 1 | 5 | Ilimitado |
| Agendamentos/mês | 50 | 500 | Ilimitado |
| Unidades | 1 | 2 | 10 |
| Clientes | 100 | Ilimitado | Ilimitado |
| Agenda online | Sim | Sim | Sim |
| Relatórios | Não | Básicos | Completos |
| WhatsApp | Não | Lembretes | Completo |
| Fidelidade | Não | Não | Sim |
| Financeiro | Básico | Completo | Completo + Comissões |
| Avaliações | Não | Sim | Sim + NPS |
| Personalização | Não | Logo | Logo + Cores |
| Suporte | Email | Email + Chat | Prioritário |

### Sistema de Billing

```
┌─────────────────────────────────────────────────┐
│              Ciclo de Cobrança                   │
│                                                  │
│  1. Empresa seleciona plano                      │
│  2. Gateway de pagamento processa (Stripe/Asaas) │
│  3. Webhook confirma pagamento                   │
│  4. Sistema ativa/renova assinatura              │
│  5. Cron verifica vencimentos diariamente        │
│  6. Inadimplência > 7 dias → downgrade           │
│  7. Inadimplência > 30 dias → suspensão          │
│  8. Inadimplência > 90 dias → dados preservados  │
│     mas conta bloqueada                          │
└─────────────────────────────────────────────────┘
```

### Controle de Limites
```php
class PlanLimiter {
    public function canCreateProfessional(int $tenantId): bool {
        $plan = $this->getPlan($tenantId);
        $current = $this->countProfessionals($tenantId);
        return $current < $plan->max_professionals;
    }

    public function canCreateAppointment(int $tenantId): bool {
        $plan = $this->getPlan($tenantId);
        $currentMonth = $this->countMonthlyAppointments($tenantId);
        return $plan->max_appointments === -1
            || $currentMonth < $plan->max_appointments;
    }
}
```

---

# 5. SEGURANÇA

## 5.1 Autenticação

### Senhas
- Hash com `password_hash()` usando `PASSWORD_ARGON2ID`
- Custo mínimo de memória: 65536 KB
- Política: mínimo 8 caracteres

### Sessões
- Session ID regenerado após login (`session_regenerate_id(true)`)
- Sessão vinculada ao IP + User-Agent (fingerprint)
- Timeout de inatividade: 30 minutos
- Timeout absoluto: 8 horas
- Sessões armazenadas em Redis (produção)

### JWT (para API)
- Algoritmo: HS256 ou RS256
- Payload: `{user_id, tenant_id, role, exp}`
- Expiração: 1 hora
- Refresh token: 7 dias (rotativo)

## 5.2 Proteções

### SQL Injection
- **100% Prepared Statements** — nenhuma query com concatenação
- PDO com `ATTR_EMULATE_PREPARES = false`
- Validação de tipo em todo input

### XSS (Cross-Site Scripting)
- Output encoding com `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` em toda view
- Content-Security-Policy header
- HttpOnly + Secure em cookies

### CSRF (Cross-Site Request Forgery)
- Token CSRF em todo formulário POST
- Token gerado por sessão + rotação por request
- Validação server-side obrigatória
- Header `SameSite=Strict` nos cookies

### Outros Headers de Segurança
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Referrer-Policy: strict-origin-when-cross-origin
```

## 5.3 LGPD

### Medidas Implementadas
- Consentimento explícito no cadastro de clientes
- Opção de exportar dados pessoais (portabilidade)
- Opção de solicitar exclusão de dados
- Registro de consentimento com timestamp
- Política de privacidade por tenant
- Dados sensíveis criptografados (CPF, telefone opcional)
- Log de quem acessou dados pessoais
- Retenção de dados configurável

### Logs de Auditoria
```sql
-- Toda ação sensível é registrada
INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id,
    old_data, new_data, ip_address, user_agent, created_at)
VALUES (?, ?, 'UPDATE', 'clients', ?, ?, ?, ?, ?, NOW());
```

---

# 6. UX E RESPONSIVIDADE

## 6.1 Princípios de Design

- **Mobile First:** Todo design começa pelo mobile e escala para desktop
- **Framework CSS:** Tailwind CSS 3.x (utilitário, leve, customizável)
- **Ícones:** Heroicons (integra com Tailwind)
- **Agenda:** FullCalendar 6.x (responsivo, touch-friendly)
- **Gráficos:** Chart.js 4.x (leve, responsivo)
- **Tabelas:** DataTables ou tabela customizada com scroll horizontal

## 6.2 Layout Responsivo

### Mobile (< 768px)
```
┌─────────────────────┐
│  ☰  AgendaPRO  🔔   │  ← Header fixo
├─────────────────────┤
│                     │
│    Conteúdo da      │
│      página         │
│                     │
│                     │
├─────────────────────┤
│ 🏠  📅  ➕  👥  ≡  │  ← Bottom navigation
└─────────────────────┘
```

### Desktop (≥ 1024px)
```
┌────────────────────────────────────────────────────┐
│  AgendaPRO        🔍 Busca      🔔  👤 Admin  ▼   │
├──────────┬─────────────────────────────────────────┤
│          │                                         │
│ Dashboard│          Conteúdo Principal              │
│ Agenda   │                                         │
│ Clientes │                                         │
│ Serviços │                                         │
│ Equipe   │                                         │
│ Financ.  │                                         │
│ Relatór. │                                         │
│ Config.  │                                         │
│          │                                         │
│          │                                         │
└──────────┴─────────────────────────────────────────┘
     ↑ Sidebar fixa
```

## 6.3 Navegação

### Menu Principal (por role)

**Admin da empresa:**
- Dashboard
- Agenda
- Clientes
- Serviços
- Equipe
- Financeiro
- Relatórios
- Fidelidade
- Avaliações
- Configurações

**Profissional:**
- Minha Agenda
- Meus Clientes
- Minhas Comissões
- Meu Perfil

**Recepcionista:**
- Agenda
- Fila de Atendimento
- Clientes
- Caixa do Dia

## 6.4 Componentes Responsivos

### Agenda Mobile
- Visualização de lista (padrão no mobile)
- Swipe para navegar entre dias
- Pull to refresh
- FAB (Floating Action Button) para novo agendamento
- Tap no horário abre detalhes

### Formulários
- Campos empilhados no mobile (1 coluna)
- Grid de 2-3 colunas no desktop
- Validação inline em tempo real
- Máscaras de input (telefone, CPF)
- Autocomplete para clientes

### Tabelas
- No mobile: cards empilháveis ao invés de tabelas
- Ações visíveis com swipe ou menu de 3 pontos
- Paginação simplificada no mobile

---

# 7. DASHBOARD

## 7.1 Layout do Dashboard

```
┌──────────────────────────────────────────────────────┐
│  Bom dia, João!          📍 Unidade Centro  ▼        │
├──────────────────────────────────────────────────────┤
│                                                      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐  │
│  │ 📅 12    │ │ 💰 R$    │ │ 📊 78%   │ │ ❌ 2   │  │
│  │ Agend.   │ │ 1.450    │ │ Ocupação │ │ Faltas │  │
│  │ hoje     │ │ hoje     │ │ hoje     │ │ hoje   │  │
│  └──────────┘ └──────────┘ └──────────┘ └────────┘  │
│                                                      │
│  ┌────────────────────────────────────────────────┐  │
│  │         Próximos Agendamentos                  │  │
│  │                                                │  │
│  │  09:00  João Silva — Corte — Carlos (prof.)    │  │
│  │  09:30  Maria O. — Escova — Ana (prof.)        │  │
│  │  10:00  Pedro S. — Barba — Carlos (prof.)      │  │
│  │  10:30  [disponível]                           │  │
│  │  11:00  Ana C. — Coloração — Bia (prof.)       │  │
│  └────────────────────────────────────────────────┘  │
│                                                      │
│  ┌─────────────────────┐ ┌────────────────────────┐  │
│  │ Serviços Populares  │ │  Alertas               │  │
│  │                     │ │                        │  │
│  │ 1. Corte     45%    │ │ ⚠️ 3 confirmações     │  │
│  │ 2. Barba     25%    │ │    pendentes           │  │
│  │ 3. Escova    15%    │ │ ⚠️ Plano vence em     │  │
│  │ 4. Coloração 10%    │ │    5 dias              │  │
│  │ 5. Outros     5%    │ │ 🎂 2 aniversariantes  │  │
│  └─────────────────────┘ └────────────────────────┘  │
│                                                      │
│  ┌────────────────────────────────────────────────┐  │
│  │    Faturamento Semanal (gráfico de barras)     │  │
│  │    ████                                        │  │
│  │    ██████                                      │  │
│  │    ████████                                    │  │
│  │    ███████                                     │  │
│  │    █████████                                   │  │
│  │    Seg  Ter  Qua  Qui  Sex  Sáb  Dom          │  │
│  └────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────┘
```

## 7.2 Widgets do Dashboard

| Widget | Dados | Atualização |
|--------|-------|-------------|
| Agendamentos do dia | COUNT hoje por status | Real-time (polling 60s) |
| Faturamento do dia | SUM entradas hoje | Real-time |
| Taxa de ocupação | Slots preenchidos / total slots | Real-time |
| Faltas do dia | COUNT no-shows hoje | Real-time |
| Próximos agendamentos | Lista ordenada por hora | Real-time |
| Serviços populares | Top 5 do mês | Cache 1h |
| Faturamento semanal | Gráfico 7 dias | Cache 15min |
| Alertas | Confirmações pendentes, vencimentos | Real-time |
| Aniversariantes | Clientes com aniversário hoje | Cache 24h |

---

# 8. INFRAESTRUTURA

## 8.1 Ambiente de Desenvolvimento (WAMP)

```
Windows + WAMP64
├── Apache 2.4
├── PHP 8.2
├── MySQL 8.0
├── Redis (Windows build)
└── Composer
```

## 8.2 Ambiente de Produção

### Opção 1: VPS (Fase Inicial)
```
DigitalOcean / Hetzner / Contabo
├── Ubuntu 22.04 LTS
├── Nginx (reverse proxy + static files)
├── PHP 8.2-FPM
├── MySQL 8.0
├── Redis 7
├── Certbot (SSL/Let's Encrypt)
├── Supervisor (workers de fila)
└── Cron jobs
```

### Opção 2: Cloud Escalável (Fase Crescimento)
```
AWS / GCP / DigitalOcean
├── Load Balancer
├── 2+ App Servers (PHP-FPM)
├── RDS MySQL (managed, multi-AZ)
├── ElastiCache Redis (managed)
├── S3 / Spaces (uploads, backups)
├── CloudFront / CDN (assets estáticos)
├── SQS / Redis Queue (filas)
└── CloudWatch / Grafana (monitoramento)
```

## 8.3 Cache

```
┌─────────────────────────────────────────┐
│            Estratégia de Cache           │
│                                         │
│  Redis (in-memory):                     │
│  ├── Sessões de usuário (TTL: 30min)    │
│  ├── Dados do tenant (TTL: 1h)          │
│  ├── Dados do plano (TTL: 1h)           │
│  ├── Slots disponíveis (TTL: 5min)      │
│  ├── Dashboard widgets (TTL: 1-15min)   │
│  ├── Rate limiting                      │
│  └── Filas de jobs                      │
│                                         │
│  Browser Cache:                         │
│  ├── CSS/JS (1 ano, versionado)         │
│  ├── Imagens (1 ano)                    │
│  └── Fontes (1 ano)                     │
│                                         │
│  CDN:                                   │
│  ├── Assets estáticos                   │
│  └── Imagens de perfil/logo             │
└─────────────────────────────────────────┘
```

## 8.4 Filas (Job Queue)

```
Jobs assíncronos:
├── SendWhatsAppMessage (enviar mensagem)
├── SendEmailNotification (enviar email)
├── ProcessPaymentWebhook (processar pagamento)
├── GenerateReport (gerar relatório pesado)
├── SendAppointmentReminder (lembrete 24h/2h)
├── CalculateCommissions (calcular comissões)
├── CleanupExpiredSessions (limpeza)
└── ProcessPlanDowngrade (downgrade automático)

Workers: Supervisord rodando N workers PHP
Driver: Redis (desenvolvimento) / SQS (produção)
```

## 8.5 Backups

```
Diário: mysqldump completo → S3 (retenção 30 dias)
Horário: Binary log incremental
Semanal: Snapshot do servidor completo
Teste: Restore automático mensal para validação
```

## 8.6 Monitoramento

```
Uptime: UptimeRobot / Pingdom (checks a cada 1min)
APM: Sentry (erros PHP/JS)
Métricas: Grafana + Prometheus
Logs: Centralizados com rotação (Monolog → arquivo/syslog)
Alertas: Email + Telegram para erros críticos
```

---

# 9. ROADMAP DE DESENVOLVIMENTO

## FASE 1 — MVP Funcional (8-10 semanas)

### Semana 1-2: Fundação
- [ ] Estrutura do projeto (MVC, autoload, router)
- [ ] Banco de dados — tabelas core (tenants, users, etc.)
- [ ] Sistema de autenticação (login, registro, sessão)
- [ ] Middleware de tenant
- [ ] Layout base (Tailwind, sidebar, bottom nav)

### Semana 3-4: Gestão
- [ ] CRUD de unidades
- [ ] CRUD de serviços + categorias
- [ ] CRUD de profissionais
- [ ] Configuração de horários de funcionamento
- [ ] CRUD de clientes

### Semana 5-6: Agenda
- [ ] Motor de disponibilidade
- [ ] Criação de agendamentos
- [ ] Agenda visual (FullCalendar)
- [ ] Cancelamento e remarcação
- [ ] Bloqueio de horários
- [ ] Detecção de conflitos

### Semana 7-8: Página Pública + Básicos
- [ ] Página pública de agendamento
- [ ] Dashboard básico
- [ ] Fila de atendimento
- [ ] Onboarding wizard
- [ ] Planos e controle de limites

### Semana 9-10: Polimento
- [ ] Responsividade completa
- [ ] Testes de isolamento multi-tenant
- [ ] Correções de UX
- [ ] Deploy em VPS
- [ ] SSL e domínio

**Entrega:** Sistema funcional onde empresas se cadastram e gerenciam agendamentos.

## FASE 2 — Automação e CRM (6 semanas)

### Semana 11-13: WhatsApp + Notificações
- [ ] Integração com API de WhatsApp
- [ ] Confirmação automática
- [ ] Lembrete 24h antes
- [ ] Templates de mensagem configuráveis
- [ ] Fila de envio de mensagens

### Semana 14-16: CRM + Avaliações
- [ ] Histórico detalhado do cliente
- [ ] Métricas por cliente
- [ ] Tags e segmentação
- [ ] Sistema de avaliações pós-atendimento
- [ ] NPS por profissional
- [ ] Lista de espera

**Entrega:** Automação de comunicação e CRM básico.

## FASE 3 — Financeiro Completo (6 semanas)

### Semana 17-19: Financeiro
- [ ] Entradas e saídas
- [ ] Formas de pagamento
- [ ] Comissões por profissional
- [ ] Categorias financeiras
- [ ] Fluxo de caixa

### Semana 20-22: Relatórios + Fidelidade
- [ ] Relatório de faturamento
- [ ] Relatório de serviços
- [ ] Relatório de ocupação
- [ ] Relatório de comissões
- [ ] Programa de fidelidade (pontos)
- [ ] Exportação PDF/Excel

**Entrega:** Módulo financeiro e relatórios completos.

## FASE 4 — Escala SaaS (6 semanas)

### Semana 23-25: Billing + Infraestrutura
- [ ] Integração gateway de pagamento (Asaas/Stripe)
- [ ] Cobrança automática de planos
- [ ] Webhooks de pagamento
- [ ] Upgrade/downgrade automático
- [ ] Nota fiscal de assinatura

### Semana 26-28: Performance + Escala
- [ ] Redis em produção
- [ ] CDN para assets
- [ ] Read replicas MySQL
- [ ] Otimização de queries
- [ ] Rate limiting
- [ ] Painel admin da plataforma (super_admin)
- [ ] Métricas de uso da plataforma

**Entrega:** SaaS escalável e comercialmente viável.

---

Documento gerado em 2026-03-12. Versão 1.0.
