-- ============================================================
-- Migration 074 — Campagnes marketing (boost de revenus temporaire)
-- ============================================================
-- Permet à tout propriétaire de compte événementiel d'acheter un boost
-- de revenus temporaire (indépendant du mode développeur), à la manière
-- des autres mécaniques du tycoon événementiel.

ALTER TABLE `accounts`
    ADD COLUMN IF NOT EXISTS `income_boost_multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER `passive_income_last_reactivated_at`,
    ADD COLUMN IF NOT EXISTS `income_boost_expires_at` DATETIME NULL AFTER `income_boost_multiplier`,
    ADD COLUMN IF NOT EXISTS `income_boost_label` VARCHAR(60) NULL AFTER `income_boost_expires_at`;
