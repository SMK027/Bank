-- ============================================================
-- Migration 011 — Statut des comptes utilisateurs
-- Ajoute la suspension et le bannissement des utilisateurs.
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN `status`          ENUM('active','suspended','banned') NOT NULL DEFAULT 'active' AFTER `global_role`,
    ADD COLUMN `suspended_until` DATETIME NULL DEFAULT NULL               AFTER `status`;
