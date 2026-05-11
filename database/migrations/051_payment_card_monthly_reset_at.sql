-- ============================================================
-- Migration 051 — Suivi de la remise à zéro du plafond mensuel
-- ============================================================
-- Pour les cartes à débit différé, le plafond est remis à zéro
-- manuellement par l'utilisateur (une fois par mois) à la fin
-- de sa période. Pour les cartes à débit immédiat, la remise
-- à zéro est automatique via le script cron process_card_resets.php
-- (tous les 1er du mois).
--
-- Le calcul du montant dépensé (getMonthlySpent, getPendingDeferredTotal)
-- utilise cette date comme borne inférieure. Une valeur NULL signifie
-- que la borne est le 1er du mois calendaire courant.

SET NAMES utf8mb4;

ALTER TABLE `payment_cards`
    ADD COLUMN `monthly_reset_at` DATETIME NULL DEFAULT NULL
        COMMENT 'Dernière remise à zéro du plafond mensuel (NULL = jamais remis à zéro → borne = 1er du mois)'
        AFTER `monthly_spent_override_month`;
