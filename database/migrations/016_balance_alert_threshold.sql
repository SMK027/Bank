-- Migration 016 : seuil d'alerte de solde par compte
--
-- Ajoute une colonne nullable sur la table `accounts`.
-- Quand le solde PASSE SOUS ce seuil à la suite d'une opération,
-- le propriétaire du compte (et ses tuteurs légaux) reçoit une
-- notification in-app de type 'balance_alert'.
--
-- NULL = fonctionnalité désactivée pour ce compte.

ALTER TABLE `accounts`
    ADD COLUMN `balance_alert_threshold` DECIMAL(10,2) NULL DEFAULT NULL
        COMMENT 'Seuil d''alerte de solde : notification envoyée lorsque le solde passe sous ce montant.'
        AFTER `cap`;
