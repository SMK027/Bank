-- ============================================================
-- 010 : Exécution automatique des mandats
-- ============================================================

ALTER TABLE `mandates`
    ADD COLUMN `last_executed_at`  DATETIME     NULL DEFAULT NULL COMMENT 'Dernière exécution' AFTER `created_by`,
    ADD COLUMN `next_execution_at` DATETIME     NULL DEFAULT NULL COMMENT 'Prochaine exécution planifiée' AFTER `last_executed_at`,
    MODIFY COLUMN `status` ENUM('active','executed','revoked') NOT NULL DEFAULT 'active';
