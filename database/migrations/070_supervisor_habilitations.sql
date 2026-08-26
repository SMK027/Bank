-- ============================================================
-- Migration 070 — Habilitations superviseur
-- ------------------------------------------------------------
-- Liste des fonctionnalités qu'un superviseur est autorisé à
-- débloquer via le formulaire de bypass.
-- ============================================================

CREATE TABLE IF NOT EXISTS `supervisor_habilitations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supervisor_id` INT UNSIGNED NOT NULL,
    `feature_key`   VARCHAR(60)  NOT NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_supervisor_habilitations_supervisor_feature` (`supervisor_id`, `feature_key`),
    KEY `idx_supervisor_habilitations_supervisor` (`supervisor_id`),
    KEY `idx_supervisor_habilitations_feature` (`feature_key`),
    CONSTRAINT `fk_supervisor_habilitations_supervisor`
        FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;