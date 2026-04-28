-- ============================================================
-- Migração 0002: Tokens de cancelamento/remarcação e colunas
-- de controle de lembretes em agendamentos e clientes.
-- ============================================================

-- Adiciona colunas de controle de notificações em appointments
ALTER TABLE appointments
    ADD COLUMN cancel_token        VARCHAR(64)  NULL UNIQUE AFTER notes,
    ADD COLUMN reminder_sent_at    DATETIME     NULL AFTER cancel_token,
    ADD COLUMN survey_sent_at      DATETIME     NULL AFTER reminder_sent_at;

-- Adiciona coluna de controle de lembrete de retorno em clients
ALTER TABLE clients
    ADD COLUMN return_reminder_sent_at DATETIME NULL AFTER lgpd_consent;

-- Tabela de tokens para ações sem login (cancelamento / remarcação via link)
CREATE TABLE IF NOT EXISTS appointment_tokens (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED NOT NULL,
    token          VARCHAR(64)  NOT NULL UNIQUE,
    action         ENUM('cancel','reschedule') NOT NULL,
    expires_at     DATETIME     NOT NULL,
    used_at        DATETIME     NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tokens_token      (token),
    INDEX idx_tokens_appointment (tenant_id, appointment_id),
    FOREIGN KEY (tenant_id)      REFERENCES tenants(id)      ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
