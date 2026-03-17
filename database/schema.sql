-- ============================================================
-- AgendaPRO SaaS — Schema Completo do Banco de Dados
-- Motor: MySQL 8.0+
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Modelo Multi-Tenant: Banco compartilhado + tenant_id
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. PLANOS E PLATAFORMA (sem tenant_id — tabelas globais)
-- ============================================================

-- Planos de assinatura disponíveis na plataforma
CREATE TABLE IF NOT EXISTS plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(100) NOT NULL UNIQUE,
    description     TEXT NULL,
    price_monthly   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_yearly    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_professionals INT NOT NULL DEFAULT 1 COMMENT '-1 = ilimitado',
    max_appointments_month INT NOT NULL DEFAULT 50 COMMENT '-1 = ilimitado',
    max_units       INT NOT NULL DEFAULT 1 COMMENT '-1 = ilimitado',
    max_clients     INT NOT NULL DEFAULT 100 COMMENT '-1 = ilimitado',
    has_reports     TINYINT(1) NOT NULL DEFAULT 0,
    has_whatsapp    TINYINT(1) NOT NULL DEFAULT 0,
    has_loyalty     TINYINT(1) NOT NULL DEFAULT 0,
    has_financial   TINYINT(1) NOT NULL DEFAULT 0,
    has_commissions TINYINT(1) NOT NULL DEFAULT 0,
    has_reviews     TINYINT(1) NOT NULL DEFAULT 0,
    has_custom_brand TINYINT(1) NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados iniciais dos planos
INSERT INTO plans (name, slug, price_monthly, price_yearly, max_professionals, max_appointments_month, max_units, max_clients, has_reports, has_whatsapp, has_loyalty, has_financial, has_commissions, has_reviews, has_custom_brand, sort_order) VALUES
('Gratuito', 'free', 0.00, 0.00, 1, 50, 1, 100, 0, 0, 0, 0, 0, 0, 0, 1),
('Básico', 'basic', 79.00, 790.00, 5, 500, 2, -1, 1, 1, 0, 1, 0, 1, 1, 2),
('Profissional', 'professional', 199.00, 1990.00, -1, -1, 10, -1, 1, 1, 1, 1, 1, 1, 1, 3);

-- ============================================================
-- 2. TENANTS (Empresas na plataforma)
-- ============================================================

CREATE TABLE IF NOT EXISTS tenants (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid            CHAR(36) NOT NULL UNIQUE,
    company_name    VARCHAR(255) NOT NULL,
    trade_name      VARCHAR(255) NULL COMMENT 'Nome fantasia',
    slug            VARCHAR(100) NOT NULL UNIQUE COMMENT 'Identificador na URL pública',
    document_number VARCHAR(20) NULL COMMENT 'CNPJ ou CPF',
    email           VARCHAR(255) NOT NULL,
    phone           VARCHAR(20) NULL,
    logo_url        VARCHAR(500) NULL,
    primary_color   VARCHAR(7) NULL DEFAULT '#4F46E5' COMMENT 'Cor da marca (hex)',
    timezone        VARCHAR(50) NOT NULL DEFAULT 'America/Sao_Paulo',
    locale          VARCHAR(10) NOT NULL DEFAULT 'pt_BR',
    status          ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'trial',
    trial_ends_at   TIMESTAMP NULL,
    settings        JSON NULL COMMENT 'Configurações gerais do tenant em JSON',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL COMMENT 'Soft delete',

    INDEX idx_tenants_slug (slug),
    INDEX idx_tenants_status (status),
    INDEX idx_tenants_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ASSINATURAS
-- ============================================================

CREATE TABLE IF NOT EXISTS subscriptions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    plan_id         INT UNSIGNED NOT NULL,
    status          ENUM('active','past_due','cancelled','suspended','trialing') NOT NULL DEFAULT 'trialing',
    billing_cycle   ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    current_period_start TIMESTAMP NULL,
    current_period_end   TIMESTAMP NULL,
    gateway_subscription_id VARCHAR(255) NULL COMMENT 'ID no gateway de pagamento',
    gateway_customer_id VARCHAR(255) NULL,
    cancelled_at    TIMESTAMP NULL,
    cancel_reason   TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_subscriptions_tenant (tenant_id),
    INDEX idx_subscriptions_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de pagamentos de assinatura
CREATE TABLE IF NOT EXISTS subscription_payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    status          ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    payment_method  VARCHAR(50) NULL,
    gateway_payment_id VARCHAR(255) NULL,
    paid_at         TIMESTAMP NULL,
    invoice_url     VARCHAR(500) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sub_payments_tenant (tenant_id),
    INDEX idx_sub_payments_subscription (subscription_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. USUÁRIOS E PERMISSÕES
-- ============================================================

-- Perfis (roles) disponíveis
CREATE TABLE IF NOT EXISTS roles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) NOT NULL UNIQUE,
    display_name    VARCHAR(100) NOT NULL,
    description     VARCHAR(255) NULL,
    is_system       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Role de sistema (não editável)',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, display_name, description, is_system) VALUES
('super_admin', 'Super Administrador', 'Administrador da plataforma SaaS', 1),
('tenant_admin', 'Administrador', 'Administrador da empresa', 1),
('manager', 'Gerente', 'Gerente de unidade', 1),
('professional', 'Profissional', 'Profissional que atende', 1),
('receptionist', 'Recepcionista', 'Atendente da recepção', 1);

-- Permissões granulares
CREATE TABLE IF NOT EXISTS permissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    display_name    VARCHAR(150) NOT NULL,
    module          VARCHAR(50) NOT NULL COMMENT 'Módulo: appointments, clients, financial, etc.',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, display_name, module) VALUES
('appointments.view', 'Visualizar agendamentos', 'appointments'),
('appointments.create', 'Criar agendamentos', 'appointments'),
('appointments.edit', 'Editar agendamentos', 'appointments'),
('appointments.cancel', 'Cancelar agendamentos', 'appointments'),
('clients.view', 'Visualizar clientes', 'clients'),
('clients.create', 'Criar clientes', 'clients'),
('clients.edit', 'Editar clientes', 'clients'),
('clients.delete', 'Excluir clientes', 'clients'),
('services.view', 'Visualizar serviços', 'services'),
('services.manage', 'Gerenciar serviços', 'services'),
('professionals.view', 'Visualizar profissionais', 'professionals'),
('professionals.manage', 'Gerenciar profissionais', 'professionals'),
('units.view', 'Visualizar unidades', 'units'),
('units.manage', 'Gerenciar unidades', 'units'),
('financial.view', 'Visualizar financeiro', 'financial'),
('financial.create', 'Criar lançamentos', 'financial'),
('financial.reports', 'Relatórios financeiros', 'financial'),
('reports.view', 'Visualizar relatórios', 'reports'),
('settings.manage', 'Gerenciar configurações', 'settings'),
('queue.manage', 'Gerenciar fila', 'queue'),
('loyalty.manage', 'Gerenciar fidelidade', 'loyalty'),
('reviews.view', 'Visualizar avaliações', 'reviews'),
('whatsapp.manage', 'Gerenciar WhatsApp', 'whatsapp');

-- Vínculo role <-> permissions
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id         INT UNSIGNED NOT NULL,
    permission_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administrador da empresa (tenant_admin) tem acesso a todos os módulos
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'tenant_admin';

-- Usuários do sistema (todos os tenants)
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NULL COMMENT 'NULL para super_admin da plataforma',
    role_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    phone           VARCHAR(20) NULL,
    avatar_url      VARCHAR(500) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    last_login_at   TIMESTAMP NULL,
    last_login_ip   VARCHAR(45) NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires_at TIMESTAMP NULL,
    remember_token  VARCHAR(100) NULL,
    settings        JSON NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,

    UNIQUE INDEX idx_users_email (email),
    INDEX idx_users_tenant (tenant_id),
    INDEX idx_users_tenant_role (tenant_id, role_id),
    INDEX idx_users_reset_token (password_reset_token),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissões extras por usuário (override do role)
CREATE TABLE IF NOT EXISTS user_permissions (
    user_id         INT UNSIGNED NOT NULL,
    permission_id   INT UNSIGNED NOT NULL,
    granted         TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=concedida, 0=negada',
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessões de usuário
CREATE TABLE IF NOT EXISTS user_sessions (
    id              VARCHAR(128) PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    tenant_id       INT UNSIGNED NULL,
    ip_address      VARCHAR(45) NOT NULL,
    user_agent      TEXT NULL,
    payload         TEXT NULL,
    last_activity   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_last_activity (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. UNIDADES (Filiais)
-- ============================================================

CREATE TABLE IF NOT EXISTS units (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(100) NOT NULL,
    phone           VARCHAR(20) NULL,
    email           VARCHAR(255) NULL,
    address_street  VARCHAR(255) NULL,
    address_number  VARCHAR(20) NULL,
    address_complement VARCHAR(100) NULL,
    address_neighborhood VARCHAR(100) NULL,
    address_city    VARCHAR(100) NULL,
    address_state   VARCHAR(2) NULL,
    address_zipcode VARCHAR(10) NULL,
    latitude        DECIMAL(10,8) NULL,
    longitude       DECIMAL(11,8) NULL,
    timezone        VARCHAR(50) NULL COMMENT 'Se diferir do tenant',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    is_default      TINYINT(1) NOT NULL DEFAULT 0,
    settings        JSON NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,

    INDEX idx_units_tenant (tenant_id),
    UNIQUE INDEX idx_units_tenant_slug (tenant_id, slug),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horários de funcionamento da unidade
CREATE TABLE IF NOT EXISTS unit_working_hours (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NOT NULL,
    day_of_week     TINYINT UNSIGNED NOT NULL COMMENT '0=Dom, 1=Seg ... 6=Sáb',
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,

    INDEX idx_uwh_tenant_unit (tenant_id, unit_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feriados e datas especiais
CREATE TABLE IF NOT EXISTS holidays (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NULL COMMENT 'NULL = todas as unidades',
    title           VARCHAR(255) NOT NULL,
    date            DATE NOT NULL,
    is_recurring    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Repete todo ano',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_holidays_tenant_date (tenant_id, date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. CATEGORIAS E SERVIÇOS
-- ============================================================

CREATE TABLE IF NOT EXISTS service_categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    description     VARCHAR(255) NULL,
    sort_order      INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_service_categories_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    price           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    commission_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    commission_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_online_booking TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Disponível para agendamento online',
    requires_professional TINYINT(1) NOT NULL DEFAULT 1,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    color           VARCHAR(7) NULL COMMENT 'Cor na agenda',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,

    INDEX idx_services_tenant (tenant_id),
    INDEX idx_services_tenant_category (tenant_id, category_id),
    INDEX idx_services_tenant_active (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. PROFISSIONAIS
-- ============================================================

CREATE TABLE IF NOT EXISTS professionals (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL COMMENT 'Vinculado a um usuário do sistema',
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NULL,
    phone           VARCHAR(20) NULL,
    avatar_url      VARCHAR(500) NULL,
    bio             TEXT NULL,
    color           VARCHAR(7) NULL DEFAULT '#3B82F6' COMMENT 'Cor na agenda',
    commission_default_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    commission_default_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,

    INDEX idx_professionals_tenant (tenant_id),
    INDEX idx_professionals_tenant_active (tenant_id, is_active),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profissionais vinculados a unidades (N:N)
CREATE TABLE IF NOT EXISTS professional_units (
    professional_id INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NOT NULL,
    tenant_id       INT UNSIGNED NOT NULL,
    PRIMARY KEY (professional_id, unit_id),
    INDEX idx_pu_tenant (tenant_id),
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serviços que o profissional realiza (N:N) com comissão customizada
CREATE TABLE IF NOT EXISTS professional_services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    service_id      INT UNSIGNED NOT NULL,
    custom_duration INT UNSIGNED NULL COMMENT 'Duração diferenciada para este profissional',
    custom_price    DECIMAL(10,2) NULL,
    commission_type ENUM('percentage','fixed') NULL COMMENT 'NULL = usar padrão do serviço',
    commission_value DECIMAL(10,2) NULL,

    UNIQUE INDEX idx_ps_professional_service (professional_id, service_id),
    INDEX idx_ps_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horários de trabalho do profissional
CREATE TABLE IF NOT EXISTS professional_working_hours (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NOT NULL,
    day_of_week     TINYINT UNSIGNED NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,

    INDEX idx_pwh_tenant_prof (tenant_id, professional_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intervalos do profissional (almoço, descanso, etc.)
CREATE TABLE IF NOT EXISTS professional_breaks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    day_of_week     TINYINT UNSIGNED NULL COMMENT 'NULL = todos os dias',
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    description     VARCHAR(100) NULL,

    INDEX idx_pb_tenant_prof (tenant_id, professional_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. CLIENTES
-- ============================================================

CREATE TABLE IF NOT EXISTS clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NULL,
    phone           VARCHAR(20) NULL,
    phone_whatsapp  VARCHAR(20) NULL,
    document_number VARCHAR(20) NULL COMMENT 'CPF',
    birth_date      DATE NULL,
    gender          ENUM('M','F','O','N') NULL COMMENT 'M=Masc, F=Fem, O=Outro, N=Não informado',
    address_street  VARCHAR(255) NULL,
    address_number  VARCHAR(20) NULL,
    address_complement VARCHAR(100) NULL,
    address_neighborhood VARCHAR(100) NULL,
    address_city    VARCHAR(100) NULL,
    address_state   VARCHAR(2) NULL,
    address_zipcode VARCHAR(10) NULL,
    notes           TEXT NULL COMMENT 'Observações internas',
    tags            JSON NULL COMMENT '["vip", "frequente"]',
    preferred_professional_id INT UNSIGNED NULL,
    source          VARCHAR(50) NULL COMMENT 'instagram, indicacao, google, etc.',
    lgpd_consent    TINYINT(1) NOT NULL DEFAULT 0,
    lgpd_consent_at TIMESTAMP NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_visit_at   TIMESTAMP NULL,
    total_visits    INT UNSIGNED NOT NULL DEFAULT 0,
    total_spent     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_no_shows  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,

    INDEX idx_clients_tenant (tenant_id),
    INDEX idx_clients_tenant_phone (tenant_id, phone),
    INDEX idx_clients_tenant_email (tenant_id, email),
    INDEX idx_clients_tenant_name (tenant_id, name),
    INDEX idx_clients_last_visit (tenant_id, last_visit_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (preferred_professional_id) REFERENCES professionals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. AGENDAMENTOS
-- ============================================================

CREATE TABLE IF NOT EXISTS appointments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NULL COMMENT 'NULL para walk-in sem cadastro',
    professional_id INT UNSIGNED NOT NULL,
    service_id      INT UNSIGNED NOT NULL,
    date            DATE NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    duration_minutes INT UNSIGNED NOT NULL,
    price           DECIMAL(10,2) NOT NULL,
    status          ENUM(
                        'scheduled',
                        'confirmed',
                        'in_progress',
                        'completed',
                        'cancelled_by_client',
                        'cancelled_by_business',
                        'rescheduled',
                        'no_show'
                    ) NOT NULL DEFAULT 'scheduled',
    source          ENUM('online','manual','walkin','whatsapp') NOT NULL DEFAULT 'manual',
    notes           TEXT NULL,
    internal_notes  TEXT NULL COMMENT 'Notas visíveis apenas à equipe',
    confirmed_at    TIMESTAMP NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    cancelled_at    TIMESTAMP NULL,
    cancel_reason   VARCHAR(255) NULL,
    rescheduled_from_id INT UNSIGNED NULL COMMENT 'ID do agendamento original',
    created_by      INT UNSIGNED NULL COMMENT 'Usuário que criou',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_appointments_tenant_date (tenant_id, date),
    INDEX idx_appointments_tenant_prof_date (tenant_id, professional_id, date),
    INDEX idx_appointments_tenant_unit_date (tenant_id, unit_id, date),
    INDEX idx_appointments_tenant_client (tenant_id, client_id),
    INDEX idx_appointments_tenant_status (tenant_id, status),
    INDEX idx_appointments_conflict (professional_id, date, start_time, end_time, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (rescheduled_from_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bloqueios de horário (férias, ausências, etc.)
CREATE TABLE IF NOT EXISTS schedule_blocks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NULL COMMENT 'NULL = bloqueio da unidade inteira',
    unit_id         INT UNSIGNED NULL,
    title           VARCHAR(255) NOT NULL,
    start_datetime  DATETIME NOT NULL,
    end_datetime    DATETIME NOT NULL,
    is_all_day      TINYINT(1) NOT NULL DEFAULT 0,
    recurrence_rule VARCHAR(255) NULL COMMENT 'RRULE do iCalendar para bloqueios recorrentes',
    notes           TEXT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_blocks_tenant_dates (tenant_id, start_datetime, end_datetime),
    INDEX idx_blocks_tenant_prof (tenant_id, professional_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. FILA DE ATENDIMENTO E LISTA DE ESPERA
-- ============================================================

CREATE TABLE IF NOT EXISTS service_queue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NULL,
    professional_id INT UNSIGNED NULL,
    service_id      INT UNSIGNED NULL,
    appointment_id  INT UNSIGNED NULL COMMENT 'Vinculado a agendamento, se existir',
    client_name     VARCHAR(255) NULL COMMENT 'Para walk-in sem cadastro',
    position        INT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('waiting','called','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'waiting',
    priority        TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=normal, 1=prioritário',
    checked_in_at   TIMESTAMP NULL,
    called_at       TIMESTAMP NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    estimated_wait_minutes INT UNSIGNED NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_queue_tenant_unit_status (tenant_id, unit_id, status),
    INDEX idx_queue_tenant_date (tenant_id, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS waitlist (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NULL,
    client_name     VARCHAR(255) NULL,
    client_phone    VARCHAR(20) NULL,
    service_id      INT UNSIGNED NULL,
    professional_id INT UNSIGNED NULL,
    preferred_date  DATE NULL,
    preferred_time_start TIME NULL,
    preferred_time_end TIME NULL,
    status          ENUM('waiting','notified','booked','expired','cancelled') NOT NULL DEFAULT 'waiting',
    notified_at     TIMESTAMP NULL,
    booked_appointment_id INT UNSIGNED NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_waitlist_tenant_status (tenant_id, status),
    INDEX idx_waitlist_tenant_date (tenant_id, preferred_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL,
    FOREIGN KEY (booked_appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. FINANCEIRO
-- ============================================================

CREATE TABLE IF NOT EXISTS financial_categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    type            ENUM('income','expense') NOT NULL,
    color           VARCHAR(7) NULL,
    is_system       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Categorias padrão (não deletáveis)',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_fin_cat_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_transactions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NULL,
    category_id     INT UNSIGNED NULL,
    appointment_id  INT UNSIGNED NULL COMMENT 'Vinculado ao atendimento, se aplicável',
    type            ENUM('income','expense') NOT NULL,
    description     VARCHAR(255) NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    payment_method  ENUM('cash','credit_card','debit_card','pix','transfer','other') NULL,
    status          ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'paid',
    due_date        DATE NULL,
    paid_at         TIMESTAMP NULL,
    reference_date  DATE NOT NULL COMMENT 'Data de competência',
    notes           TEXT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_fin_trans_tenant_date (tenant_id, reference_date),
    INDEX idx_fin_trans_tenant_type (tenant_id, type),
    INDEX idx_fin_trans_tenant_status (tenant_id, status),
    INDEX idx_fin_trans_appointment (tenant_id, appointment_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES financial_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comissões calculadas
CREATE TABLE IF NOT EXISTS commissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NOT NULL,
    service_id      INT UNSIGNED NOT NULL,
    service_price   DECIMAL(10,2) NOT NULL,
    commission_type ENUM('percentage','fixed') NOT NULL,
    commission_rate DECIMAL(10,2) NOT NULL COMMENT 'Percentual ou valor fixo aplicado',
    commission_amount DECIMAL(10,2) NOT NULL COMMENT 'Valor final da comissão',
    status          ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    paid_at         TIMESTAMP NULL,
    reference_month CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_commissions_tenant_prof (tenant_id, professional_id),
    INDEX idx_commissions_tenant_month (tenant_id, reference_month),
    INDEX idx_commissions_tenant_status (tenant_id, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. FIDELIDADE
-- ============================================================

-- Programas de fidelidade configuráveis
CREATE TABLE IF NOT EXISTS loyalty_programs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    type            ENUM('points','frequency') NOT NULL DEFAULT 'points',
    points_per_currency DECIMAL(10,2) NULL COMMENT 'Pontos ganhos por R$1 gasto',
    frequency_target INT UNSIGNED NULL COMMENT 'Nº de visitas para recompensa',
    frequency_service_id INT UNSIGNED NULL COMMENT 'Serviço específico para contar',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_loyalty_prog_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (frequency_service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recompensas disponíveis
CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    program_id      INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    points_required INT UNSIGNED NULL COMMENT 'Pontos necessários para resgatar',
    reward_type     ENUM('free_service','discount_percentage','discount_fixed','custom') NOT NULL,
    reward_value    DECIMAL(10,2) NULL,
    reward_service_id INT UNSIGNED NULL COMMENT 'Serviço gratuito como recompensa',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_loyalty_rewards_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES loyalty_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saldo e histórico de pontos do cliente
CREATE TABLE IF NOT EXISTS loyalty_points (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    program_id      INT UNSIGNED NOT NULL,
    points_balance  INT NOT NULL DEFAULT 0,
    total_earned    INT NOT NULL DEFAULT 0,
    total_redeemed  INT NOT NULL DEFAULT 0,
    visit_count     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Para programas de frequência',
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_loyalty_points_client_program (tenant_id, client_id, program_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES loyalty_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transações de fidelidade (ganho e resgate)
CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    program_id      INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NULL,
    type            ENUM('earn','redeem','expire','adjust') NOT NULL,
    points          INT NOT NULL,
    description     VARCHAR(255) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_loyalty_trans_tenant_client (tenant_id, client_id),
    INDEX idx_loyalty_trans_date (tenant_id, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES loyalty_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. AVALIAÇÕES
-- ============================================================

CREATE TABLE IF NOT EXISTS reviews (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NOT NULL,
    service_id      INT UNSIGNED NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL COMMENT '1 a 5 estrelas',
    comment         TEXT NULL,
    is_public       TINYINT(1) NOT NULL DEFAULT 1,
    response        TEXT NULL COMMENT 'Resposta da empresa',
    responded_at    TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_reviews_tenant (tenant_id),
    INDEX idx_reviews_tenant_prof (tenant_id, professional_id),
    INDEX idx_reviews_tenant_rating (tenant_id, rating),
    UNIQUE INDEX idx_reviews_appointment (appointment_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. MENSAGENS E NOTIFICAÇÕES
-- ============================================================

-- Templates de mensagens configuráveis por tenant
CREATE TABLE IF NOT EXISTS message_templates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    trigger_event   VARCHAR(50) NOT NULL COMMENT 'appointment_created, reminder_24h, review_request, etc.',
    channel         ENUM('whatsapp','email','sms') NOT NULL DEFAULT 'whatsapp',
    subject         VARCHAR(255) NULL,
    body            TEXT NOT NULL COMMENT 'Template com placeholders: {nome_cliente}, {data}, etc.',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_msg_templates_tenant_event (tenant_id, trigger_event),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de mensagens enviadas
CREATE TABLE IF NOT EXISTS messages_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    template_id     INT UNSIGNED NULL,
    client_id       INT UNSIGNED NULL,
    appointment_id  INT UNSIGNED NULL,
    channel         ENUM('whatsapp','email','sms') NOT NULL,
    recipient       VARCHAR(255) NOT NULL COMMENT 'Telefone ou email',
    subject         VARCHAR(255) NULL,
    body            TEXT NOT NULL,
    status          ENUM('queued','sent','delivered','failed','read') NOT NULL DEFAULT 'queued',
    external_id     VARCHAR(255) NULL COMMENT 'ID da API externa',
    error_message   TEXT NULL,
    sent_at         TIMESTAMP NULL,
    delivered_at    TIMESTAMP NULL,
    read_at         TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_messages_tenant_date (tenant_id, created_at),
    INDEX idx_messages_tenant_status (tenant_id, status),
    INDEX idx_messages_appointment (tenant_id, appointment_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notificações internas (in-app)
CREATE TABLE IF NOT EXISTS notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    type            VARCHAR(50) NOT NULL COMMENT 'appointment_new, payment_received, etc.',
    title           VARCHAR(255) NOT NULL,
    body            TEXT NULL,
    data            JSON NULL COMMENT 'Dados extras para link/ação',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    read_at         TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_notifications_user_read (tenant_id, user_id, is_read),
    INDEX idx_notifications_date (tenant_id, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. LOGS E AUDITORIA
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NULL,
    user_id         INT UNSIGNED NULL,
    action          VARCHAR(50) NOT NULL COMMENT 'create, update, delete, login, etc.',
    entity_type     VARCHAR(50) NOT NULL COMMENT 'Nome da tabela/entidade',
    entity_id       INT UNSIGNED NULL,
    old_data        JSON NULL,
    new_data        JSON NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(500) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_audit_tenant_date (tenant_id, created_at),
    INDEX idx_audit_tenant_entity (tenant_id, entity_type, entity_id),
    INDEX idx_audit_user (user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de acesso (login/logout)
CREATE TABLE IF NOT EXISTS access_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    tenant_id       INT UNSIGNED NULL,
    action          ENUM('login_success','login_failed','logout','password_reset','password_changed') NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    user_agent      VARCHAR(500) NULL,
    metadata        JSON NULL COMMENT 'Dados extras (ex: motivo da falha)',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_access_user (user_id),
    INDEX idx_access_tenant_date (tenant_id, created_at),
    INDEX idx_access_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. FILA DE JOBS (para processos assíncronos)
-- ============================================================

CREATE TABLE IF NOT EXISTS jobs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue           VARCHAR(50) NOT NULL DEFAULT 'default',
    payload         JSON NOT NULL,
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    available_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at     TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    failed_at       TIMESTAMP NULL,
    error_message   TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_jobs_queue_available (queue, available_at),
    INDEX idx_jobs_reserved (reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS failed_jobs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue           VARCHAR(50) NOT NULL,
    payload         JSON NOT NULL,
    error_message   TEXT NOT NULL,
    failed_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. CONFIGURAÇÕES E CACHE
-- ============================================================

CREATE TABLE IF NOT EXISTS tenant_settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    `key`           VARCHAR(100) NOT NULL,
    `value`         TEXT NULL,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_tenant_settings_key (tenant_id, `key`),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FIM DO SCHEMA
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;
