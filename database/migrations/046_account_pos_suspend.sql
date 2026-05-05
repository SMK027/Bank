-- ─── Suspension TPE d'un compte professionnel ───────────────
-- Permet à la modération de bloquer l'accès au TPE pour un
-- compte professionnel précis, avec motif obligatoire et durée
-- optionnelle (réactivation automatique).
ALTER TABLE `accounts`
    ADD COLUMN `pos_suspended_at`    DATETIME     NULL DEFAULT NULL AFTER `frozen_by`,
    ADD COLUMN `pos_suspended_until` DATETIME     NULL DEFAULT NULL AFTER `pos_suspended_at`,
    ADD COLUMN `pos_suspended_by`    INT UNSIGNED NULL DEFAULT NULL AFTER `pos_suspended_until`,
    ADD COLUMN `pos_suspend_reason`  VARCHAR(500) NOT NULL DEFAULT '' AFTER `pos_suspended_by`,
    ADD INDEX  `idx_accounts_pos_suspended_until` (`pos_suspended_until`),
    ADD CONSTRAINT `fk_accounts_pos_suspended_by`
        FOREIGN KEY (`pos_suspended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
