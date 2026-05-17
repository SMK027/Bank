-- ================================================================
-- Migration 055 : Conserver les crédits lors de la suppression du compte
-- ================================================================
-- Auparavant, la contrainte fk_loans_account était en ON DELETE CASCADE :
-- la suppression d'un compte (clôture définitive en fin de mois) effaçait
-- également les crédits associés, y compris ceux encore actifs.
--
-- Nouvelle règle : on rend la colonne `loans.account_id` nullable et la
-- contrainte est passée en ON DELETE SET NULL. Le crédit survit donc à la
-- suppression du compte initial. La modération peut ensuite réaffecter le
-- crédit à un autre compte du contractant pour les prélèvements de
-- mensualités (voir route POST /moderation/loans/{id}/reassign).
-- ================================================================

ALTER TABLE `loans`
    DROP FOREIGN KEY `fk_loans_account`;

ALTER TABLE `loans`
    MODIFY COLUMN `account_id` INT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Compte de prélèvement (NULL = compte initial supprimé, à réaffecter par la modération)';

ALTER TABLE `loans`
    ADD CONSTRAINT `fk_loans_account`
        FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL;
