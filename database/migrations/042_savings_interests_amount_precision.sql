-- ─────────────────────────────────────────────────────────────────
-- 042 : Précision maximale des montants dans savings_interests
--       DECIMAL(20,2) → DECIMAL(65,2) pour supporter les taux
--       extrêmes (jusqu'à ~10 000 000 %) sur comptes internes.
--       65 est la précision maximale autorisée par MariaDB/MySQL.
-- ─────────────────────────────────────────────────────────────────

ALTER TABLE `savings_interests`
    MODIFY COLUMN `calculated_amount` DECIMAL(65,2) NOT NULL COMMENT 'Montant calculé au prorata temporis',
    MODIFY COLUMN `max_amount`        DECIMAL(65,2) NOT NULL COMMENT 'Maximum théorique autorisé',
    MODIFY COLUMN `confirmed_amount`  DECIMAL(65,2) NULL     COMMENT 'Montant effectivement versé après confirmation';
