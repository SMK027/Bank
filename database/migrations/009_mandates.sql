-- ============================================================
-- 009 : Mandats de prélèvement professionnels
-- ============================================================

CREATE TABLE IF NOT EXISTS `mandates` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `number`           VARCHAR(35)      NOT NULL UNIQUE,
    `emitter_account_id` INT UNSIGNED   NOT NULL COMMENT 'Compte pro à créditer',
    `recipient_account_id` INT UNSIGNED NOT NULL COMMENT 'Compte à débiter',
    `description`      VARCHAR(255)     NOT NULL DEFAULT '',
    `amount`           DOUBLE           NOT NULL,
    `type`             ENUM('one_time','recurring') NOT NULL DEFAULT 'one_time',
    `interval_days`    INT UNSIGNED     NULL DEFAULT NULL COMMENT 'Intervalle en jours si récurrent',
    `status`           ENUM('active','revoked') NOT NULL DEFAULT 'active',
    `created_by`       INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'Modérateur créateur',
    `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_mandate_emitter`   (`emitter_account_id`),
    INDEX `idx_mandate_recipient` (`recipient_account_id`),
    INDEX `idx_mandate_status`    (`status`),
    CONSTRAINT `fk_mandate_emitter`   FOREIGN KEY (`emitter_account_id`)   REFERENCES `accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mandate_recipient` FOREIGN KEY (`recipient_account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
