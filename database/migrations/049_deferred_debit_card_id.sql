-- ============================================================
-- Migration 049 — Lien carte bancaire sur les débits différés
-- ============================================================
-- Permet d'associer un débit différé à une carte spécifique
-- afin de vérifier et d'imputer son plafond mensuel.

SET NAMES utf8mb4;

ALTER TABLE `deferred_debits`
    ADD COLUMN `card_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Carte bancaire associée (optionnel — déclenche la vérification du plafond mensuel)'
        AFTER `user_id`,
    ADD CONSTRAINT `fk_deferred_debits_card`
        FOREIGN KEY (`card_id`) REFERENCES `payment_cards` (`id`) ON DELETE SET NULL;
