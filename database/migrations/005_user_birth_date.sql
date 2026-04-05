-- ============================================================
-- Migration 005 : ajout de la date de naissance sur les utilisateurs
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `birth_date` DATE NULL DEFAULT NULL
    AFTER `global_role`;
