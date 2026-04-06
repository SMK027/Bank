-- ─────────────────────────────────────────────────────────────────
-- 015 : Journal d'audit des actions effectuées sur la plateforme
-- ─────────────────────────────────────────────────────────────────
-- Pas de FK sur user_id / target_user_id / target_account_id :
-- on conserve les entrées même si l'utilisateur ou le compte est supprimé.
-- ─────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`           INT UNSIGNED    NULL     COMMENT 'Auteur (NULL = cron / système)',
    `action`            VARCHAR(60)     NOT NULL COMMENT 'Catégorie.événement, ex: account.freeze',
    `target_user_id`    INT UNSIGNED    NULL     COMMENT 'Utilisateur cible (si applicable)',
    `target_account_id` INT UNSIGNED    NULL     COMMENT 'Compte cible (si applicable)',
    `details`           TEXT            NULL     COMMENT 'Contexte sérialisé en JSON',
    `ip_address`        VARCHAR(45)     NULL     COMMENT 'IPv4 ou IPv6 (NULL si CLI)',
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_audit_user`    (`user_id`),
    INDEX `idx_audit_action`  (`action`),
    INDEX `idx_audit_target_user`    (`target_user_id`),
    INDEX `idx_audit_target_account` (`target_account_id`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
