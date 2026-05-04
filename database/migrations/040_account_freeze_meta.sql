-- Migration 040 : métadonnées du gel temporaire de compte (motif, durée, auteur)
ALTER TABLE `accounts`
    ADD COLUMN `frozen_reason` VARCHAR(500) NULL DEFAULT NULL AFTER `frozen`,
    ADD COLUMN `frozen_until`  DATETIME    NULL DEFAULT NULL AFTER `frozen_reason`,
    ADD COLUMN `frozen_by`     INT(11)     NULL DEFAULT NULL AFTER `frozen_until`,
    ADD INDEX  `idx_accounts_frozen_until` (`frozen_until`);
