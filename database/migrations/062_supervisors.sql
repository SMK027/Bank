-- ─── Comptes superviseurs pour le contournement des feature flags ────────────
-- Un superviseur peut débloquer provisoirement une fonctionnalité désactivée
-- via un formulaire dédié (identifiant + code PIN). Chaque utilisation est
-- tracée dans audit_logs avec l'action supervisor.bypass.
CREATE TABLE IF NOT EXISTS `supervisors` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `first_name`     VARCHAR(100)    NOT NULL,
    `last_name`      VARCHAR(100)    NOT NULL,
    `supervisor_id`  VARCHAR(64)     NOT NULL COMMENT 'Identifiant de connexion unique',
    `pin_hash`       VARCHAR(255)    NOT NULL COMMENT 'Bcrypt du code PIN',
    `status`         ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `created_by`     INT UNSIGNED    NULL DEFAULT NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_supervisors_supervisor_id` (`supervisor_id`),
    CONSTRAINT `fk_supervisors_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
