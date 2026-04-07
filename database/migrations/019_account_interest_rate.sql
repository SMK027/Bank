-- ─────────────────────────────────────────────────────────────────
-- 019 : Taux d'intérêt propre à chaque compte éligible
-- ─────────────────────────────────────────────────────────────────
-- Le taux de la modération devient un plafond maximum.
-- Chaque compte éligible aux intérêts possède désormais son propre
-- taux, fixé par l'utilisateur, borné par le plafond de la modération.

ALTER TABLE `accounts`
    ADD COLUMN `interest_rate` DECIMAL(6,5) NULL DEFAULT NULL
        COMMENT 'Taux d\'intérêt annuel propre au compte (décimal, ex : 0.03000 = 3 %). NULL = pas de taux défini.'
        AFTER `balance_alert_threshold`;
