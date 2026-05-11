-- ============================================================
-- Migration 052 — Lien carte bancaire sur les transactions
-- ============================================================
-- Permet d'associer une transaction manuelle (dépense immédiate
-- ou programmée) à une carte bancaire à titre informatif.

SET NAMES utf8mb4;

ALTER TABLE `transactions`
    ADD COLUMN `card_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Carte bancaire associée (facultatif — liaison informative)'
        AFTER `user_id`,
    ADD CONSTRAINT `fk_transactions_card`
        FOREIGN KEY (`card_id`) REFERENCES `payment_cards` (`id`) ON DELETE SET NULL;
