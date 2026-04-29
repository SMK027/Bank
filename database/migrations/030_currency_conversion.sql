-- ============================================================
-- Conversion de devise : cache des taux + traçabilité virements
-- ============================================================

-- Cache des taux de change récupérés depuis une API externe.
-- Un seul enregistrement par couple (base_currency, target_currency).
CREATE TABLE IF NOT EXISTS `exchange_rates` (
    `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `base_currency`   VARCHAR(10)    NOT NULL,
    `target_currency` VARCHAR(10)    NOT NULL,
    `rate`            DECIMAL(20,10) NOT NULL,
    `fetched_at`      DATETIME       NOT NULL,
    `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_pair` (`base_currency`, `target_currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Traçabilité de la conversion sur chaque virement.
ALTER TABLE `transfers`
    ADD COLUMN `from_currency`     VARCHAR(10)    NULL DEFAULT NULL AFTER `amount`,
    ADD COLUMN `to_currency`       VARCHAR(10)    NULL DEFAULT NULL AFTER `from_currency`,
    ADD COLUMN `exchange_rate`     DECIMAL(20,10) NULL DEFAULT NULL AFTER `to_currency`,
    ADD COLUMN `converted_amount`  DOUBLE         NULL DEFAULT NULL AFTER `exchange_rate`;
