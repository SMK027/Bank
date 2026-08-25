-- ============================================================
-- Migration 066 — Améliorations de compte événementiel
-- ============================================================

CREATE TABLE IF NOT EXISTS `event_account_upgrades` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT UNSIGNED NOT NULL,
    `upgrade_key` VARCHAR(64) NOT NULL,
    `quantity`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_event_upgrade_account_key` (`account_id`, `upgrade_key`),
    KEY `idx_event_upgrade_account` (`account_id`),
    KEY `idx_event_upgrade_key` (`upgrade_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
