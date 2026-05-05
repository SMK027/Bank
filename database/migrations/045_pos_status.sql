-- ─── État global du TPE ─────────────────────────────────────
-- Table à une seule ligne (id=1) qui pilote l'activation du
-- terminal de paiement. La modération peut désactiver le TPE
-- en temps réel, optionnellement jusqu'à une date donnée.
CREATE TABLE IF NOT EXISTS `pos_status` (
    `id`             TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `disabled_at`    DATETIME         NULL DEFAULT NULL,
    `disabled_until` DATETIME         NULL DEFAULT NULL,
    `disabled_by`    INT UNSIGNED     NULL DEFAULT NULL,
    `reason`         VARCHAR(500)     NOT NULL DEFAULT '',
    `updated_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pos_status_user` FOREIGN KEY (`disabled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `pos_status` (`id`, `disabled_at`) VALUES (1, NULL);
