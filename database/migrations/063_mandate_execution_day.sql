-- ============================================================
-- 063 : Jour fixe d'exécution pour les mandats récurrents
-- ============================================================
-- Ajoute execution_day (1-28) sur les mandats récurrents.
-- Quand ce champ est renseigné, le mandat est prélevé chaque mois
-- à la date fixe indiquée (ex. : 5 = le 5 de chaque mois).
-- interval_days est alors ignoré et doit rester NULL.
-- Limité à 28 pour garantir la validité en février.

ALTER TABLE `mandates`
    ADD COLUMN `execution_day` TINYINT UNSIGNED NULL DEFAULT NULL
        COMMENT 'Jour fixe du mois (1-31) pour les mandats récurrents à date fixe'
        AFTER `interval_days`,
    ADD CONSTRAINT `chk_mandate_execution_day` CHECK (`execution_day` IS NULL OR (`execution_day` >= 1 AND `execution_day` <= 31));
