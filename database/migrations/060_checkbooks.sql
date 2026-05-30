-- ============================================================
-- Migration 060 — Chéquiers et chèques
-- ============================================================
-- Permet à un utilisateur d'émettre des chèques associés à un
-- compte courant ou professionnel (non-épargne). Chaque chéquier
-- peut être mis en opposition globale ; chaque chèque peut aussi
-- être mis en opposition individuellement.
-- Un paiement par chèque crée une transaction en attente que
-- l'utilisateur doit confirmer manuellement lorsque le chèque est
-- encaissé.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── Chéquiers ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `checkbooks` (
    `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED   NOT NULL,
    `account_id`  INT UNSIGNED   NOT NULL,
    `label`       VARCHAR(100)   NOT NULL DEFAULT '',
    `status`      ENUM('active','opposed') NOT NULL DEFAULT 'active',
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_checkbook_user`    (`user_id`),
    KEY `idx_checkbook_account` (`account_id`),
    CONSTRAINT `fk_checkbook_user`
        FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_checkbook_account`
        FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Chèques émis ────────────────────────────────────────────
-- Un chèque est créé à l'émission (lors de l'enregistrement de
-- la dépense). Son statut passe à 'cashed' quand l'utilisateur
-- confirme l'encaissement, et à 'opposed' en cas d'opposition.
CREATE TABLE IF NOT EXISTS `checks` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `checkbook_id`   INT UNSIGNED     NOT NULL,
    `check_number`   INT UNSIGNED     NOT NULL,
    `transaction_id` INT UNSIGNED     NULL DEFAULT NULL,
    `amount`         DECIMAL(12,2)    NOT NULL,
    `payee`          VARCHAR(150)     NOT NULL DEFAULT '',
    `status`         ENUM('emitted','cashed','opposed') NOT NULL DEFAULT 'emitted',
    `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_check_checkbook`   (`checkbook_id`),
    KEY `idx_check_transaction` (`transaction_id`),
    CONSTRAINT `fk_check_checkbook`
        FOREIGN KEY (`checkbook_id`)   REFERENCES `checkbooks`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_check_transaction`
        FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Lien chèque sur les transactions ────────────────────────
ALTER TABLE `transactions`
    ADD COLUMN `check_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Chèque associé (paiement par chèque — en attente de confirmation)'
        AFTER `card_id`,
    ADD CONSTRAINT `fk_transactions_check`
        FOREIGN KEY (`check_id`) REFERENCES `checks` (`id`) ON DELETE SET NULL;

SET foreign_key_checks = 1;

-- ─── Feature flags chéquiers ─────────────────────────────────
INSERT IGNORE INTO `feature_flags` (`flag_key`, `label`, `description`, `category`, `enabled`)
VALUES
    ('checkbooks.create',  'Création de chéquier',          'Permet aux utilisateurs de créer des chéquiers.',                'Chéquiers', 1),
    ('checkbooks.oppose',  'Opposition chéquier / chèque',  'Permet de mettre un chéquier ou un chèque en opposition.',       'Chéquiers', 1),
    ('checkbooks.confirm', 'Confirmation d\'encaissement',  'Permet de confirmer l\'encaissement d\'un chèque émis.',         'Chéquiers', 1);
