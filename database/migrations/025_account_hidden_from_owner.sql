ALTER TABLE `accounts`
    ADD COLUMN IF NOT EXISTS `hidden_from_owner` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Compte masqué au mineur par un responsable légal (il reste propriétaire mais ne peut plus le consulter)';
