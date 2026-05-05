-- ─────────────────────────────────────────────────────────────────
-- 041 : Précision du taux dans savings_interests + accounts
--       et suppression de la contrainte d'unicité (account_id, year)
--       pour permettre aux comptes internes de recalculer leurs
--       intérêts plusieurs fois par an à des fins de test.
-- ─────────────────────────────────────────────────────────────────

-- Aligne accounts.interest_rate : decimal(6,5) → decimal(10,5)
-- Supporte jusqu'à 9 999 999,999 % pour les tests sur comptes internes
ALTER TABLE `accounts`
    MODIFY COLUMN `interest_rate` DECIMAL(10,5) NULL COMMENT 'Taux annuel brut — ex : 0.03000 = 3 %';

-- Aligne savings_interests.rate sur le même type
-- Élargit les montants pour supporter les taux extrêmes de test
ALTER TABLE `savings_interests`
    MODIFY COLUMN `rate`              DECIMAL(10,5) NOT NULL COMMENT 'Taux appliqué',
    MODIFY COLUMN `calculated_amount` DECIMAL(65,2) NOT NULL COMMENT 'Montant calculé au prorata temporis',
    MODIFY COLUMN `max_amount`        DECIMAL(65,2) NOT NULL COMMENT 'Maximum théorique autorisé',
    MODIFY COLUMN `confirmed_amount`  DECIMAL(65,2) NULL     COMMENT 'Montant effectivement versé après confirmation';

-- Supprime la contrainte d'unicité stricte (account_id, year)
-- L'unicité pour les comptes normaux est désormais gérée en amont (cron + contrôleur)
ALTER TABLE `savings_interests`
    DROP INDEX IF EXISTS `uniq_account_year`;
