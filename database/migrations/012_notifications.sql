-- ============================================================
-- Migration 012 — Notifications in-app
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       VARCHAR(60)  NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `body`       TEXT         NULL,
    `link`       VARCHAR(255) NULL,
    `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_notif_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
