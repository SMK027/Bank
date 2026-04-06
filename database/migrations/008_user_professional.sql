-- ============================================================
-- 008 : Séparation comptes personnels / professionnels
-- Ajout des champs professionnels sur la table users
-- ============================================================

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `is_professional` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `company_name`    VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `siret`           CHAR(14)     NULL DEFAULT NULL;
