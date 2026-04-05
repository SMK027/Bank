-- ─── Plafond d'épargne ─────────────────────────────────────────────────────
-- Ajoute la colonne `cap` (plafond de solde) sur la table accounts.
-- Applicable uniquement aux comptes de type 'savings'.
-- NULL = pas de plafond ; valeur > 0 = plafond en unité de la devise du compte.

ALTER TABLE `accounts`
    ADD COLUMN IF NOT EXISTS `cap` DOUBLE NULL DEFAULT NULL;
