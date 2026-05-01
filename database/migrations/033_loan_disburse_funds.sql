-- ================================================================
-- Ajout du champ disburse_funds sur la table loans
-- Indique si les fonds doivent être crédités sur le compte
-- lors de l'acceptation du crédit par l'utilisateur.
-- ================================================================

ALTER TABLE `loans`
    ADD COLUMN `disburse_funds` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Si 1, les fonds sont versés sur le compte à l\'acceptation'
    AFTER `notes`;
