-- ============================================================
-- Migration 067 — Pause/réactivation du revenu passif d'un compte événementiel
-- ============================================================

ALTER TABLE `accounts`
    ADD COLUMN IF NOT EXISTS `passive_income_paused_at` DATETIME NULL AFTER `event_end_at`,
    ADD COLUMN IF NOT EXISTS `passive_income_last_reactivated_at` DATETIME NULL AFTER `passive_income_paused_at`;
