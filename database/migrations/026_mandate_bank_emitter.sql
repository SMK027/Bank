-- Migration 026 : Permettre les mandats bancaires sans compte émetteur
-- Rend emitter_account_id nullable dans la table mandates.
-- Un mandat avec emitter_account_id NULL est considéré comme émis par la banque :
-- lors de l'exécution, seul le compte destinataire est débité, aucun compte n'est crédité.

ALTER TABLE `mandates`
    DROP FOREIGN KEY `fk_mandate_emitter`;

ALTER TABLE `mandates`
    MODIFY COLUMN `emitter_account_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Compte pro à créditer (NULL = mandat émis par la banque)';

ALTER TABLE `mandates`
    ADD CONSTRAINT `fk_mandate_emitter`
        FOREIGN KEY (`emitter_account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;
