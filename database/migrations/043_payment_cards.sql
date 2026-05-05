-- ============================================================
-- Migration 043 : Cartes bancaires fictives & clients API tiers
-- ============================================================
-- Permet à un utilisateur d'enregistrer une ou plusieurs cartes
-- bancaires (numéro fictif à 16 chiffres) associées à un compte
-- non-épargne. Les cartes sont consommées par une API de paiement
-- accessible aux plateformes tierces authentifiées.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── Cartes bancaires ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payment_cards` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT UNSIGNED    NOT NULL,
    `account_id`   INT UNSIGNED    NOT NULL,
    `card_number`  VARCHAR(19)     NOT NULL,
    `last4`        CHAR(4)         NOT NULL,
    `label`        VARCHAR(100)    NOT NULL DEFAULT '',
    `status`       ENUM('active','blocked') NOT NULL DEFAULT 'active',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_card_number` (`card_number`),
    KEY `idx_card_user`    (`user_id`),
    KEY `idx_card_account` (`account_id`),
    CONSTRAINT `fk_cards_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cards_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Clients API (plateformes tierces) ──────────────────────
CREATE TABLE IF NOT EXISTS `api_clients` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name`            VARCHAR(150)    NOT NULL,
    `api_key`         VARCHAR(64)     NOT NULL,
    `api_secret_hash` VARCHAR(255)    NOT NULL,
    `status`          ENUM('active','revoked') NOT NULL DEFAULT 'active',
    `created_by`      INT UNSIGNED    NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_api_key` (`api_key`),
    CONSTRAINT `fk_api_clients_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Journal des paiements API ──────────────────────────────
CREATE TABLE IF NOT EXISTS `api_payments` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `api_client_id`   INT UNSIGNED    NOT NULL,
    `card_id`         INT UNSIGNED    NULL,
    `account_id`      INT UNSIGNED    NULL,
    `transaction_id`  INT UNSIGNED    NULL,
    `operation`       ENUM('debit','credit') NOT NULL,
    `amount`          DOUBLE          NOT NULL,
    `currency`        VARCHAR(10)     NOT NULL DEFAULT 'EUR',
    `status`          ENUM('success','failed') NOT NULL,
    `reason`          VARCHAR(255)    NOT NULL DEFAULT '',
    `comment`         VARCHAR(255)    NOT NULL DEFAULT '',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_api_payments_client` (`api_client_id`),
    KEY `idx_api_payments_card`   (`card_id`),
    CONSTRAINT `fk_api_payments_client` FOREIGN KEY (`api_client_id`) REFERENCES `api_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
