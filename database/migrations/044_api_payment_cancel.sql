-- ============================================================
-- Migration 044 : Liens de transactions et débit différé pour
-- les paiements API/TPE, afin de permettre une annulation
-- complète par la modération.
-- ============================================================

ALTER TABLE `api_payments`
    ADD COLUMN `credit_transaction_id` INT UNSIGNED NULL AFTER `transaction_id`,
    ADD COLUMN `deferred_debit_id`     INT UNSIGNED NULL AFTER `credit_transaction_id`,
    ADD COLUMN `cancelled_at`          DATETIME     NULL AFTER `comment`,
    ADD COLUMN `cancelled_by`          INT UNSIGNED NULL AFTER `cancelled_at`,
    ADD COLUMN `cancel_reason`         VARCHAR(255) NOT NULL DEFAULT '' AFTER `cancelled_by`;
