-- Migration 021 : ajout de la colonne disabled_at sur les comptes bancaires
-- Un compte désactivé (disabled_at IS NOT NULL) ne peut plus enregistrer d'opérations.
-- Il sera définitivement supprimé à la fin du mois calendaire de sa désactivation.

ALTER TABLE `accounts`
    ADD COLUMN `disabled_at` DATETIME NULL DEFAULT NULL AFTER `frozen`;
