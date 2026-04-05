-- ============================================================
-- Migration 004 — Prélèvements (direct debits)
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `direct_debits` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `mandate_number`  VARCHAR(35)     NOT NULL,
    `scheduled_at`    DATETIME        NOT NULL,
    `executed_at`     DATETIME        NULL DEFAULT NULL,
    `amount`          DOUBLE          NOT NULL,
    `motif`           VARCHAR(255)    NULL DEFAULT NULL,
    `from_account_id` INT UNSIGNED    NULL DEFAULT NULL COMMENT 'NULL = banque (pas de compte créditeur)',
    `to_account_id`   INT UNSIGNED    NOT NULL COMMENT 'Compte débité',
    `debit_tx_id`     INT UNSIGNED    NULL DEFAULT NULL,
    `credit_tx_id`    INT UNSIGNED    NULL DEFAULT NULL,
    `status`          ENUM('scheduled','success','failed','cancelled') NOT NULL DEFAULT 'scheduled',
    `created_by`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_dd_status`       (`status`),
    INDEX `idx_dd_scheduled`    (`scheduled_at`),
    INDEX `idx_dd_to_account`   (`to_account_id`),
    INDEX `idx_dd_from_account` (`from_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
