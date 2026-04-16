-- Migration 029 : Messagerie interne
-- Conversations entre modérateurs, ou entre modération et utilisateurs

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── Conversations ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `conversations` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `subject`    VARCHAR(255)    NOT NULL,
    `type`       ENUM('mod_only','mod_user') NOT NULL DEFAULT 'mod_user',
    `is_closed`  TINYINT(1)      NOT NULL DEFAULT 0,
    `created_by` INT UNSIGNED    NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_conversations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Participants d'une conversation ─────────────────────────
CREATE TABLE IF NOT EXISTS `conversation_participants` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `last_read_at`    DATETIME     NULL DEFAULT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_conv_user` (`conversation_id`, `user_id`),
    CONSTRAINT `fk_cp_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cp_user`         FOREIGN KEY (`user_id`)         REFERENCES `users`         (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Messages ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `messages` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT UNSIGNED NOT NULL,
    `user_id`         INT UNSIGNED NOT NULL,
    `body`            TEXT         NOT NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_user`         FOREIGN KEY (`user_id`)         REFERENCES `users`         (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
