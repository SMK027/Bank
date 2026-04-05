-- ============================================================
-- Migration 006 : table des tutelles légales (compte mineur)
-- ============================================================
-- Un mineur peut avoir 1 ou 2 responsables légaux (adultes).
-- La tutelle est active tant que le mineur n'a pas atteint 18 ans
-- (calculé dynamiquement via birth_date ; aucun champ d'expiration stocké).
-- ============================================================

CREATE TABLE IF NOT EXISTS `guardianships` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `minor_user_id`    INT UNSIGNED NOT NULL COMMENT 'Utilisateur mineur',
    `guardian_user_id` INT UNSIGNED NOT NULL COMMENT 'Responsable légal (adulte)',
    `created_by`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'ID du modérateur créateur',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_guardianship` (`minor_user_id`, `guardian_user_id`),
    CONSTRAINT `fk_g_minor`    FOREIGN KEY (`minor_user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_g_guardian` FOREIGN KEY (`guardian_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
