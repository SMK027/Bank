-- 028 : Jour préféré de débit différé par compte
-- Permet à l'utilisateur de définir le jour du mois où tous ses encours carte sont débités.

ALTER TABLE `accounts`
    ADD COLUMN `deferred_debit_day` TINYINT UNSIGNED DEFAULT NULL
        COMMENT 'Jour du mois (1-31) pour le débit différé automatique';
