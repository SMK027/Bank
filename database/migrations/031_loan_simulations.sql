-- Simulateur de crédits : historique des simulations par utilisateur
CREATE TABLE IF NOT EXISTS `loan_simulations` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED    NOT NULL,
    `loan_type`        VARCHAR(30)     NOT NULL,
    `amount`           DECIMAL(15,2)   NOT NULL,
    `months`           SMALLINT UNSIGNED NOT NULL,
    `annual_rate`      DECIMAL(7,4)    NOT NULL COMMENT 'Taux annuel en % (ex: 5.5000)',
    `monthly_payment`  DECIMAL(15,2)   NOT NULL,
    `total_cost`       DECIMAL(15,2)   NOT NULL,
    `total_interest`   DECIMAL(15,2)   NOT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_loan_sim_user` (`user_id`),
    CONSTRAINT `fk_loan_sim_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
