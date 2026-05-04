-- Migration 038 : motif de rejet de prélèvement (renseigné par le modérateur)
ALTER TABLE `direct_debits`
    ADD COLUMN `reject_reason` VARCHAR(500) NULL DEFAULT NULL AFTER `motif`;
