ALTER TABLE `loans`
    ADD COLUMN `cancel_tx_id` INT UNSIGNED NULL AFTER `credit_tx_id`;
