-- ============================================================
-- 064 : Étendre execution_day à 1-31 (anciennement limité à 28)
-- ============================================================
-- Supprime l'ancienne contrainte CHECK si elle existe, puis
-- recrée la contrainte avec une plage étendue à 1-31.
-- En application, les jours > dernier jour d'un mois sont
-- ramenés par le code PHP au dernier jour valide (28 en février).
-- Syntaxe MariaDB 10.4+ : DROP CONSTRAINT IF EXISTS.

ALTER TABLE `mandates`
    DROP CONSTRAINT IF EXISTS `chk_mandate_execution_day`,
    ADD CONSTRAINT `chk_mandate_execution_day`
        CHECK (`execution_day` IS NULL OR (`execution_day` >= 1 AND `execution_day` <= 31));
