-- ─────────────────────────────────────────────────────────────────
-- 041 : Précision du taux dans savings_interests + suppression
--       de la contrainte d'unicité (account_id, year) pour permettre
--       aux comptes internes de recalculer leurs intérêts plusieurs
--       fois par an à des fins de test.
-- ─────────────────────────────────────────────────────────────────

-- Aligne decimal(5,4) → decimal(6,5) pour correspondre à accounts.interest_rate
ALTER TABLE `savings_interests`
    MODIFY COLUMN `rate` DECIMAL(6,5) NOT NULL COMMENT 'Taux appliqué';

-- Supprime la contrainte d'unicité stricte (account_id, year)
-- L'unicité pour les comptes normaux est désormais gérée en amont (cron + contrôleur)
ALTER TABLE `savings_interests`
    DROP INDEX `uniq_account_year`;
