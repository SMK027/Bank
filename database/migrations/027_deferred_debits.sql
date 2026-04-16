-- ============================================================
-- Migration 027 — Débit différé (cartes de crédit)
-- ============================================================

SET NAMES utf8mb4;

-- Champ sur le compte : autorisation du débit différé (activé par la modération)
ALTER TABLE `accounts`
    ADD COLUMN `deferred_debit_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `frozen`;

-- Table des opérations à débit différé
CREATE TABLE IF NOT EXISTS `deferred_debits` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `account_id`      INT UNSIGNED    NOT NULL,
    `user_id`         INT UNSIGNED    NOT NULL,
    `amount`          DOUBLE          NOT NULL,
    `category`        VARCHAR(100)    NOT NULL DEFAULT '',
    `comment`         TEXT,
    `operation_date`  DATETIME        NOT NULL COMMENT 'Date réelle de l''opération (achat)',
    `period_end_date` DATE            NOT NULL COMMENT 'Date de fin de période (débit effectif)',
    `status`          ENUM('pending','executed','cancelled') NOT NULL DEFAULT 'pending',
    `transaction_id`  INT UNSIGNED    NULL DEFAULT NULL COMMENT 'Transaction créée à l''exécution',
    `executed_at`     DATETIME        NULL DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_deferred_account`    (`account_id`),
    INDEX `idx_deferred_status`     (`status`),
    INDEX `idx_deferred_period_end` (`period_end_date`),
    CONSTRAINT `fk_deferred_debits_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_deferred_debits_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
