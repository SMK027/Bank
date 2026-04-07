-- ─────────────────────────────────────────────────────────────────
-- 018 : Taux d'intérêt par type de compte épargne
-- ─────────────────────────────────────────────────────────────────

-- 1. Ajouter la colonne account_type avec valeur par défaut 'savings'
--    (les anciens enregistrements sans type correspondent au type 'savings')
ALTER TABLE `savings_rates`
    ADD COLUMN `account_type` VARCHAR(32) NOT NULL DEFAULT 'savings'
        COMMENT 'Type de compte auquel ce taux s\'applique'
        AFTER `id`;

-- 2. Ajouter un index pour accélérer les recherches par type
ALTER TABLE `savings_rates`
    ADD INDEX `idx_rate_type` (`account_type`);

-- 3. Ajouter account_type dans savings_interests pour traçabilité
--    (on mémorise le type au moment du calcul)
ALTER TABLE `savings_interests`
    ADD COLUMN `account_type` VARCHAR(32) NOT NULL DEFAULT 'savings'
        COMMENT 'Type de compte au moment du calcul'
        AFTER `account_id`;
