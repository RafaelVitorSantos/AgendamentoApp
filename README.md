# AgendaPRO

Sistema SaaS multi-tenant de agendamentos para salões, clínicas e prestadores de serviços. Construído com Next.js 16, Prisma, MySQL e Redis.

## Funcionalidades

- Agendamentos com controle de conflitos e slots disponíveis
- Agenda online pública por slug do tenant (`/book/[slug]`)
- Fila de atendimento presencial em tempo real
- Gestão de clientes com conformidade LGPD
- Controle financeiro com categorias e relatórios
- Comissões por profissional ou serviço
- Programa de fidelidade com pontos e recompensas
- Avaliações de atendimento
- Integração com Google Calendar (sincronização bidirecional)
- Feed iCal por profissional
- Envio de e-mails transacionais (confirmação, lembrete, cancelamento)
- Integração com WhatsApp via Evolution API / Z-API
- Multiplanilha (Grátis, Básico, Profissional, Enterprise)
- Logs de auditoria por tenant

## Stack

| Camada | Tecnologia |
|---|---|
| Framework | Next.js 16 (App Router) |
| Linguagem | TypeScript 5 |
| Banco de dados | MySQL 8 via Prisma 7 |
| Fila de jobs | BullMQ + Redis 7 |
| Autenticação | JWT (jose) com sessões em banco |
| UI | Tailwind CSS 4 + shadcn/ui + Base UI |
| Formulários | React Hook Form + Zod 4 |
| Estado global | Zustand |
| Datas | date-fns 4 |
| E-mail | Nodemailer |

---

## Pré-requisitos

- [Node.js](https://nodejs.org/) >= 20
- [npm](https://www.npmjs.com/) >= 10
- [MySQL](https://www.mysql.com/) 8.0 (local ou Docker)
- [Redis](https://redis.io/) 7 (local ou Docker)

---

## Instalação local (sem Docker)

### 1. Clone o repositório

```bash
git clone https://github.com/seu-usuario/agendapro.git
cd agendapro
```

### 2. Instale as dependências

```bash
npm install
```

### 3. Configure as variáveis de ambiente

Copie o arquivo de exemplo e preencha os valores:

```bash
cp .env .env.local
```

Edite `.env.local` com suas configurações:

```env
# Banco de dados MySQL
DATABASE_URL="mysql://root:senha@localhost:3306/agendapro"

# URL pública da aplicação
NEXT_PUBLIC_APP_URL="http://localhost:3000"
NEXT_PUBLIC_APP_NAME="AgendaPRO"

# JWT — gere uma chave segura com: openssl rand -base64 32
JWT_SECRET="sua_chave_secreta_forte_aqui"
JWT_TTL="28800"          # 8 horas (segundos)
JWT_REFRESH_TTL="604800" # 7 dias (segundos)

# E-mail SMTP (ex.: Mailtrap para dev, SendGrid para produção)
MAIL_HOST="smtp.mailtrap.io"
MAIL_PORT="587"
MAIL_USER=""
MAIL_PASS=""
MAIL_FROM="noreply@agendapro.com.br"
MAIL_FROM_NAME="AgendaPRO"

# Redis (BullMQ)
REDIS_HOST="localhost"
REDIS_PORT="6379"
REDIS_PASSWORD=""

# WhatsApp (opcional)
WHATSAPP_API_URL=""
WHATSAPP_API_TOKEN=""
WHATSAPP_INSTANCE=""

# Google Calendar OAuth (opcional)
GOOGLE_CLIENT_ID=""
GOOGLE_CLIENT_SECRET=""
GOOGLE_REDIRECT_URI="http://localhost:3000/api/oauth/google/callback"

# Pagamentos Asaas (opcional)
ASAAS_API_KEY=""
ASAAS_ENVIRONMENT="sandbox"

# Chave AES para tokens OAuth (32 bytes) — gere com: openssl rand -base64 32
APP_KEY="sua_chave_aes_de_32_bytes_aqui"
```

### 4. Crie o banco de dados

Crie o banco `agendapro` no MySQL:

```sql
CREATE DATABASE agendapro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Execute as migrations

```bash
npm run db:migrate
```

> Se estiver em desenvolvimento rápido sem controle de histórico, pode usar `npm run db:push` no lugar.

### 6. Popule o banco com dados iniciais (seed)

```bash
npm run db:seed
```

Isso cria:
- Roles: `super_admin`, `tenant_admin`, `manager`, `professional`, `receptionist`
- Planos: Grátis, Básico, Profissional, Enterprise
- Tenant demo com login de acesso:

| Campo | Valor |
|---|---|
| E-mail | `admin@demo.com` |
| Senha | `demo123456` |
| Slug | `demo` |

### 7. Inicie o servidor de desenvolvimento

```bash
npm run dev
```

Acesse [http://localhost:3000](http://localhost:3000).

---

## Instalação com Docker

A forma mais simples de subir toda a infraestrutura (app + MySQL + Redis) de uma vez.

### 1. Clone e configure o `.env`

```bash
git clone https://github.com/seu-usuario/agendapro.git
cd agendapro
cp .env .env.local
# Edite .env.local conforme necessário (os valores padrão do docker-compose já funcionam)
```

### 2. Suba os serviços

```bash
docker compose up -d
```

Isso inicia três containers:

| Container | Serviço | Porta |
|---|---|---|
| `agendapro_db` | MySQL 8 | 3306 |
| `agendapro_redis` | Redis 7 | 6379 |
| `agendapro_app` | Next.js | 3000 |

As credenciais padrão do banco já estão configuradas no `docker-compose.yml`:

```
DATABASE_URL=mysql://agendapro:agendapro123@db:3306/agendapro
```

### 3. Execute migrations e seed dentro do container

```bash
docker compose exec app npm run db:migrate
docker compose exec app npm run db:seed
```

### 4. Acesse a aplicação

Abra [http://localhost:3000](http://localhost:3000).

### Parar e remover os containers

```bash
docker compose down          # para os containers
docker compose down -v       # para e remove os volumes (apaga dados do banco)
```

---

## Scripts disponíveis

| Script | Descrição |
|---|---|
| `npm run dev` | Inicia em modo desenvolvimento com hot-reload |
| `npm run build` | Gera o Prisma client e faz o build de produção |
| `npm start` | Inicia o servidor de produção |
| `npm run lint` | Executa o ESLint |
| `npm run db:generate` | Regenera o Prisma Client após alterações no schema |
| `npm run db:push` | Sincroniza o schema com o banco sem criar migration |
| `npm run db:migrate` | Cria e executa migrations de desenvolvimento |
| `npm run db:studio` | Abre o Prisma Studio (interface visual do banco) |
| `npm run db:seed` | Popula o banco com dados iniciais |

---

## Estrutura de pastas

```
agendapro/
├── prisma/
│   ├── schema.prisma       # Schema completo do banco
│   └── seed.ts             # Seed inicial (planos, roles, tenant demo)
├── src/
│   ├── app/
│   │   ├── (auth)/         # Páginas de login e cadastro
│   │   ├── (dashboard)/    # Painel administrativo (agendamentos, clientes, etc.)
│   │   ├── api/            # Route handlers da API REST
│   │   │   ├── auth/       # Login, logout, registro, /me
│   │   │   ├── appointments/
│   │   │   ├── clients/
│   │   │   ├── professionals/
│   │   │   ├── services/
│   │   │   ├── units/
│   │   │   ├── financial/
│   │   │   ├── queue/
│   │   │   ├── book/[slug]/  # API pública de agendamento online
│   │   │   ├── calendar/   # Feed iCal
│   │   │   └── lgpd/       # Endpoints de conformidade LGPD
│   │   └── book/           # Página pública de agendamento
│   ├── components/         # Componentes React reutilizáveis
│   ├── lib/
│   │   ├── db.ts           # Instância global do Prisma Client
│   │   ├── auth.ts         # Helpers de autenticação JWT
│   │   ├── tenant.ts       # Resolução de tenant por requisição
│   │   ├── mail.ts         # Envio de e-mails com Nodemailer
│   │   ├── ical.ts         # Geração de feeds iCal
│   │   ├── appointments.ts # Lógica de negócio de agendamentos
│   │   ├── audit.ts        # Registro de logs de auditoria
│   │   └── types.ts        # Tipos TypeScript globais
│   └── middleware.ts       # Middleware de autenticação e tenant
├── Dockerfile
├── docker-compose.yml
└── .env
```

---

## Modelo de dados resumido

O sistema é multi-tenant: todos os dados de negócio estão vinculados a um `Tenant`.

```
Tenant
 ├── Users (com Roles e Permissions)
 ├── Units (unidades/filiais)
 ├── Professionals → Services (N:M)
 ├── Clients
 ├── Appointments → FinancialTransactions, Commissions, Reviews
 ├── ServiceQueue (fila presencial)
 ├── LoyaltyProgram → LoyaltyPoints, LoyaltyRewards
 ├── MessageTemplates + MessagesLog
 ├── CalendarIntegrations (Google Calendar)
 └── Subscriptions → Plan
```

---

## Geração de chaves

Para gerar os valores de `JWT_SECRET` e `APP_KEY` com segurança:

```bash
# Linux/macOS/WSL
openssl rand -base64 32

# PowerShell (Windows)
[Convert]::ToBase64String((1..32 | ForEach-Object { [byte](Get-Random -Max 256) }))
```

---

## Primeiros passos após instalar

1. Acesse `/login` e entre com `admin@demo.com` / `demo123456`
2. Explore o painel em `/dashboard`
3. Acesse a agenda online pública em `/book/demo`
4. Use `npm run db:studio` para inspecionar os dados no browser

---

## Deploy em produção

Para o build de produção:

```bash
npm run build
npm start
```

O `Dockerfile` já está configurado com multi-stage build para uma imagem enxuta. Certifique-se de definir todas as variáveis de ambiente no ambiente de hospedagem antes de iniciar o container.
