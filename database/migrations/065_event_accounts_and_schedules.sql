-- ============================================================
-- Migration 065 — Comptes événementiels et planification d'événements
-- ============================================================

CREATE TABLE IF NOT EXISTS `event_schedules` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(180) NOT NULL,
    `start_at`   DATETIME     NOT NULL,
    `end_at`     DATETIME     NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_schedules_window` (`start_at`, `end_at`),
    KEY `idx_event_schedules_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `accounts`
    ADD COLUMN `event_title` VARCHAR(180) NULL AFTER `type`,
    ADD COLUMN `event_start_at` DATETIME NULL AFTER `event_title`,
    ADD COLUMN `event_end_at` DATETIME NULL AFTER `event_start_at`;

CREATE INDEX `idx_accounts_event_end` ON `accounts` (`event_end_at`);
