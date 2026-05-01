-- Migration 037 : pénalités de retard sur les mensualités
ALTER TABLE `loan_installments`
    ADD COLUMN `penalty` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `interest`;
UPDATE `loan_installments` SET `penalty` = 0.00;
