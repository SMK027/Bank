-- ============================================================
-- Migration 013 : limite de réexécution unique sur les prélèvements
--
-- Ajoute retry_count pour tracer le nombre de tentatives
-- de réexécution. Un prélèvement (original ou réexécuté)
-- ne peut être réexécuté que si retry_count = 0.
-- ============================================================

ALTER TABLE `direct_debits`
    ADD COLUMN `retry_count` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '0 = jamais réexécuté, 1 = déjà réexécuté (limite atteinte)'
        AFTER `status`;
