-- ============================================================
-- Schéma initial BankApp — MariaDB / MySQL
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── Utilisateurs ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username`    VARCHAR(80)     NOT NULL,
    `email`       VARCHAR(255)    NOT NULL UNIQUE,
    `password`    VARCHAR(255)    NOT NULL,
    `global_role` ENUM('user','moderator') NOT NULL DEFAULT 'user',
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Comptes bancaires ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `accounts` (
    `id`        INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`   INT UNSIGNED    NOT NULL,
    `name`      VARCHAR(255)    NOT NULL,
    `currency`  VARCHAR(10)     NOT NULL DEFAULT 'EUR',
    `overdraft` DOUBLE          NOT NULL DEFAULT 0,
    `type`      VARCHAR(30)     NOT NULL DEFAULT 'standard',
    `frozen`    TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Transactions ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transactions` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `account_id`   INT UNSIGNED    NOT NULL,
    `user_id`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `type`         ENUM('income','expense') NOT NULL,
    `amount`       DOUBLE          NOT NULL,
    `category`     VARCHAR(100)    NOT NULL DEFAULT '',
    `comment`      TEXT,
    `scheduled_at` DATETIME        NULL DEFAULT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transactions_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Accès partagés ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `account_accesses` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `account_id` INT UNSIGNED    NOT NULL,
    `user_id`    INT UNSIGNED    NOT NULL,
    `type`       ENUM('permanent','temporary') NOT NULL DEFAULT 'permanent',
    `expires_at` DATETIME        NULL DEFAULT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_access` (`account_id`, `user_id`),
    CONSTRAINT `fk_accesses_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_accesses_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Virements ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transfers` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `from_account_id` INT UNSIGNED    NOT NULL,
    `to_account_id`   INT UNSIGNED    NOT NULL,
    `user_id`         INT UNSIGNED    NOT NULL,
    `amount`          DOUBLE          NOT NULL,
    `motif`           VARCHAR(255)    NOT NULL DEFAULT '',
    `status`          ENUM('scheduled','success','failed','cancelled') NOT NULL DEFAULT 'success',
    `scheduled_at`    DATETIME        NULL DEFAULT NULL,
    `executed_at`     DATETIME        NULL DEFAULT NULL,
    `debit_tx_id`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `credit_tx_id`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transfers_from` FOREIGN KEY (`from_account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transfers_to`   FOREIGN KEY (`to_account_id`)   REFERENCES `accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transfers_user` FOREIGN KEY (`user_id`)         REFERENCES `users`    (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
