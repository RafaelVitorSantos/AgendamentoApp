-- ============================================================
-- Migração v2: Tabelas para Fila de Atendimento e Bloqueios
-- Execute este script se seu banco foi criado antes da v2.
-- ============================================================

-- Fila de atendimento (walk-in e agendamentos)
CREATE TABLE IF NOT EXISTS service_queue (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    unit_id         INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NULL,
    professional_id INT UNSIGNED NULL,
    service_id      INT UNSIGNED NULL,
    appointment_id  INT UNSIGNED NULL,
    client_name     VARCHAR(255) NULL,
    position        INT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('waiting','called','in_progress','completed','cancelled','no_show') NOT NULL DEFAULT 'waiting',
    priority        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    checked_in_at   TIMESTAMP NULL,
    called_at       TIMESTAMP NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    estimated_wait_minutes INT UNSIGNED NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_queue_tenant_unit_status (tenant_id, unit_id, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bloqueios de horário (férias, ausências, etc.)
CREATE TABLE IF NOT EXISTS schedule_blocks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    professional_id INT UNSIGNED NULL,
    unit_id         INT UNSIGNED NULL,
    title           VARCHAR(255) NOT NULL,
    start_datetime  DATETIME NOT NULL,
    end_datetime    DATETIME NOT NULL,
    is_all_day      TINYINT(1) NOT NULL DEFAULT 0,
    recurrence_rule VARCHAR(255) NULL,
    notes           TEXT NULL,
    created_by      INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_blocks_tenant_dates (tenant_id, start_datetime, end_datetime),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
