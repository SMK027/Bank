-- Migration 020 : Numéro de compte unique et code PIN haché par utilisateur
ALTER TABLE `users`
    ADD COLUMN `account_number` VARCHAR(20) NULL UNIQUE AFTER `email`,
    ADD COLUMN `pin_hash`       VARCHAR(255) NULL AFTER `account_number`;
