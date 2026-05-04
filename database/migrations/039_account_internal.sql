-- Migration 039 : ajout du marqueur `internal` sur les comptes bancaires.
-- Un compte interne est créé par la modération à des fins de test.
-- Il appartient à un modérateur, ne peut pas être partagé aux utilisateurs normaux,
-- et donne accès à l'ensemble des opérations bancaires sans restriction de profil.

ALTER TABLE `accounts`
    ADD COLUMN `internal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `disabled_at`,
    ADD INDEX `idx_accounts_internal` (`internal`);
