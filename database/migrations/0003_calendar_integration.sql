-- ============================================================
-- Migração 0003: Integração de Calendários + Fix jobs.payload
-- ============================================================

-- FIX CRÍTICO: jobs.payload era JSON NOT NULL, mas o worker usa
-- PHP serialize() que não é JSON válido. Convertendo para TEXT.
ALTER TABLE jobs MODIFY COLUMN payload TEXT NOT NULL;
ALTER TABLE failed_jobs MODIFY COLUMN payload TEXT NOT NULL;

-- Adiciona coluna para rastrear o event_id externo por agendamento
ALTER TABLE appointments
    ADD COLUMN calendar_synced_at DATETIME NULL AFTER survey_sent_at;

-- Tokens de feed iCal (por profissional ou por tenant)
CREATE TABLE IF NOT EXISTS calendar_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    professional_id INT UNSIGNED NULL,
    token           CHAR(64) NOT NULL UNIQUE COMMENT 'Token criptograficamente seguro (32 bytes hex)',
    scope           ENUM('professional','tenant') NOT NULL DEFAULT 'professional',
    revoked_at      DATETIME NULL,
    last_accessed_at DATETIME NULL,
    access_count    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_cal_tokens_tenant (tenant_id),
    INDEX idx_cal_tokens_professional (professional_id),
    INDEX idx_cal_tokens_token (token),
    FOREIGN KEY (tenant_id)      REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Integrações OAuth com provedores externos (Google, Microsoft)
CREATE TABLE IF NOT EXISTS calendar_integrations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NOT NULL,
    professional_id     INT UNSIGNED NULL,
    provider            ENUM('google','microsoft') NOT NULL,
    provider_account    VARCHAR(255) NULL COMMENT 'Email da conta Google/Microsoft autorizada',
    calendar_id         VARCHAR(500) NULL COMMENT 'ID da agenda selecionada',
    calendar_name       VARCHAR(255) NULL,
    access_token        TEXT NULL COMMENT 'Criptografado com APP_KEY',
    refresh_token       TEXT NULL COMMENT 'Criptografado com APP_KEY',
    token_expires_at    DATETIME NULL,
    webhook_channel_id  VARCHAR(255) NULL COMMENT 'Google: channel.id do push notification',
    webhook_resource_id VARCHAR(255) NULL COMMENT 'Google: resource.id',
    webhook_expires_at  DATETIME NULL,
    sync_enabled        TINYINT(1) NOT NULL DEFAULT 1,
    sync_direction      ENUM('push_only','pull_only','bidirectional') NOT NULL DEFAULT 'push_only',
    last_sync_at        DATETIME NULL,
    sync_token          VARCHAR(500) NULL COMMENT 'Google: syncToken para sync incremental',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_integration_user_provider (user_id, provider),
    INDEX idx_cal_int_tenant (tenant_id),
    INDEX idx_cal_int_webhook (webhook_channel_id),
    FOREIGN KEY (tenant_id)      REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mapa de eventos externos ↔ agendamentos internos
CREATE TABLE IF NOT EXISTS calendar_event_map (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    integration_id  INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NOT NULL,
    external_event_id VARCHAR(500) NOT NULL COMMENT 'ID do evento no Google/Microsoft',
    provider        ENUM('google','microsoft') NOT NULL,
    etag            VARCHAR(255) NULL COMMENT 'ETag do Google para detecção de mudanças',
    synced_at       DATETIME NOT NULL,
    sync_direction  ENUM('push','pull') NOT NULL DEFAULT 'push',

    UNIQUE KEY uq_event_map (integration_id, external_event_id),
    INDEX idx_event_map_appointment (tenant_id, appointment_id),
    FOREIGN KEY (tenant_id)      REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (integration_id) REFERENCES calendar_integrations(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de sincronizações (debug e auditoria)
CREATE TABLE IF NOT EXISTS calendar_sync_logs (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT UNSIGNED NOT NULL,
    integration_id INT UNSIGNED NULL,
    appointment_id INT UNSIGNED NULL,
    provider       VARCHAR(20) NOT NULL,
    action         ENUM('push_create','push_update','push_delete','pull_create','pull_update','pull_delete','webhook','token_refresh') NOT NULL,
    status         ENUM('success','failed','skipped') NOT NULL,
    http_status    SMALLINT UNSIGNED NULL,
    error          TEXT NULL,
    duration_ms    INT UNSIGNED NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sync_log_tenant (tenant_id, created_at),
    INDEX idx_sync_log_integration (integration_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
