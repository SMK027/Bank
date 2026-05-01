-- ================================================================
-- Ajout du statut 'cancelled' sur loans
-- Ajout du statut 'refunded' + colonne refund_tx_id sur loan_installments
-- ================================================================

ALTER TABLE `loans`
    MODIFY COLUMN `status` ENUM(
        'pending_acceptance',
        'active',
        'rejected',
        'closed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending_acceptance';

ALTER TABLE `loan_installments`
    MODIFY COLUMN `status` ENUM('pending','paid','failed','cancelled','refunded')
        NOT NULL DEFAULT 'pending',
    ADD COLUMN `refund_tx_id` INT UNSIGNED NULL
        COMMENT 'Transaction de remboursement de la mensualité'
        AFTER `transaction_id`;
