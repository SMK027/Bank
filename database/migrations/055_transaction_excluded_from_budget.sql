-- ============================================================
-- Migration 055 — Exclusion d'une transaction du budget
-- ============================================================
-- Ajoute la colonne excluded_from_budget sur la table transactions.
-- Quand elle vaut 1, la transaction n'est pas comptabilisée dans
-- le calcul des dépenses par catégorie de la page Budgets.
-- ============================================================

ALTER TABLE `transactions`
    ADD COLUMN IF NOT EXISTS `excluded_from_budget`
        TINYINT(1) NOT NULL DEFAULT 0
        AFTER `scheduled_at`;

ALTER TABLE `transactions`
    ADD INDEX IF NOT EXISTS `idx_tx_excluded_from_budget` (`excluded_from_budget`);
