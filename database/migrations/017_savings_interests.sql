-- ─────────────────────────────────────────────────────────────────
-- 017 : Système d'intérêts sur comptes épargne
-- ─────────────────────────────────────────────────────────────────

-- Historique des taux d'intérêt configurés par les modérateurs
CREATE TABLE IF NOT EXISTS `savings_rates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `rate`       DECIMAL(5,4) NOT NULL COMMENT 'Taux annuel brut — ex : 0.0300 = 3 %',
    `set_by`     INT UNSIGNED NOT NULL COMMENT 'Identifiant du modérateur',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rate_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intérêts calculés pour chaque compte épargne, en attente de confirmation
CREATE TABLE IF NOT EXISTS `savings_interests` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `account_id`        INT UNSIGNED  NOT NULL,
    `year`              SMALLINT UNSIGNED NOT NULL COMMENT 'Année concernée',
    `rate`              DECIMAL(5,4)  NOT NULL COMMENT 'Taux appliqué',
    `calculated_amount` DECIMAL(10,2) NOT NULL COMMENT 'Montant calculé au prorata temporis',
    `max_amount`        DECIMAL(10,2) NOT NULL COMMENT 'Maximum théorique autorisé (balance × taux, borné par plafond)',
    `confirmed_amount`  DECIMAL(10,2) NULL     COMMENT 'Montant effectivement versé après confirmation',
    `status`            ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
    `transaction_id`    INT UNSIGNED  NULL     COMMENT 'Transaction de versement créée lors de la confirmation',
    `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `confirmed_at`      DATETIME      NULL,
    UNIQUE KEY `uniq_account_year` (`account_id`, `year`),
    INDEX `idx_interest_account` (`account_id`),
    INDEX `idx_interest_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
