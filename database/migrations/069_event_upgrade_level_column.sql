-- ============================================================
-- Migration 069 — Ajout de la colonne level sur event_account_upgrades
-- ============================================================

ALTER TABLE `event_account_upgrades`
    ADD COLUMN IF NOT EXISTS `level` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `quantity`;
