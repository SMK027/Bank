-- Tokens de réinitialisation de mot de passe (usage unique, 30 minutes)
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255)  NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used_at`    DATETIME     NULL DEFAULT NULL,
    `created_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_token_hash` (`token_hash`(64)),
    KEY `idx_user_id`    (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
