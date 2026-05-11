-- ============================================================
-- Migration 050 — Override modérateur du plafond mensuel dépensé
-- ============================================================
-- Permet à un modérateur de forcer manuellement la valeur
-- du montant dépensé ce mois-ci sur une carte, pour corriger
-- un écart ou intégrer des dépenses hors système.
-- L'override est automatiquement ignoré dès que le mois change.

SET NAMES utf8mb4;

ALTER TABLE `payment_cards`
    ADD COLUMN `monthly_spent_override` DECIMAL(15,2) NULL DEFAULT NULL
        COMMENT 'Valeur manuelle du montant dépensé ce mois (modération)'
        AFTER `monthly_limit`,
    ADD COLUMN `monthly_spent_override_month` CHAR(7) NULL DEFAULT NULL
        COMMENT 'Mois de validité de l''override, format YYYY-MM'
        AFTER `monthly_spent_override`;
