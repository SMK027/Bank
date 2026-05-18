-- 057_friendships.sql
-- Table des relations d'amitié entre utilisateurs

CREATE TABLE IF NOT EXISTS `friendships` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `requester_id` INT UNSIGNED NOT NULL,
    `recipient_id` INT UNSIGNED NOT NULL,
    `status`       ENUM('pending','accepted','refused','cancelled') NOT NULL DEFAULT 'pending',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_friendship` (`requester_id`, `recipient_id`),
    KEY `idx_friendships_recipient` (`recipient_id`),
    KEY `idx_friendships_status` (`status`),
    CONSTRAINT `fk_friendship_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_friendship_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
