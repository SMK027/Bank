-- Décomposition capital / intérêts sur les échéances de crédit
ALTER TABLE `loan_installments`
    ADD COLUMN `principal` DECIMAL(15,2) NOT NULL DEFAULT 0.00
        COMMENT 'Part capital de la mensualité' AFTER `amount`,
    ADD COLUMN `interest`  DECIMAL(15,2) NOT NULL DEFAULT 0.00
        COMMENT 'Part intérêts de la mensualité' AFTER `principal`;

-- Rétrocompat : les échéances existantes n'avaient pas d'intérêts
UPDATE `loan_installments` SET `principal` = `amount`, `interest` = 0.00;
