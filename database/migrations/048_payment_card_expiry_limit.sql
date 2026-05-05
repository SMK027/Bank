-- ============================================================
-- Migration 048 : Date d'expiration et plafond mensuel sur
--                 les cartes bancaires fictives
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `payment_cards`
    ADD COLUMN `expires_at`    DATE           NULL DEFAULT NULL
        COMMENT 'Date d''expiration de la carte (dernier jour du mois). NULL = sans expiration.'
        AFTER `label`,
    ADD COLUMN `monthly_limit` DOUBLE UNSIGNED NULL DEFAULT NULL
        COMMENT 'Plafond mensuel de dépense en devise du compte. NULL = illimité.'
        AFTER `expires_at`;
