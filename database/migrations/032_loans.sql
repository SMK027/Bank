-- ================================================================
-- Crédits bancaires octroyés par la modération
-- ================================================================

CREATE TABLE IF NOT EXISTS `loans` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `account_id`      INT UNSIGNED      NOT NULL,
    `user_id`         INT UNSIGNED      NOT NULL COMMENT 'Propriétaire du compte au moment de l\'octroi',
    `loan_type`       VARCHAR(30)       NOT NULL,
    `amount`          DECIMAL(15,2)     NOT NULL                   COMMENT 'Capital total accordé',
    `annual_rate`     DECIMAL(7,4)      NOT NULL                   COMMENT 'Taux annuel en % (ex : 5.5000)',
    `amount_repaid`   DECIMAL(15,2)     NOT NULL DEFAULT 0.00      COMMENT 'Montant déjà remboursé',
    `status`          ENUM(
                          'pending_acceptance',
                          'active',
                          'rejected',
                          'closed'
                      )                 NOT NULL DEFAULT 'pending_acceptance',
    `granted_by`      INT UNSIGNED      NOT NULL                   COMMENT 'ID du modérateur',
    `notes`           TEXT              NULL                       COMMENT 'Notes internes de la modération',
    `granted_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `accepted_at`     DATETIME          NULL,
    `rejected_at`     DATETIME          NULL,
    `closed_at`       DATETIME          NULL,
    `credit_tx_id`    INT UNSIGNED      NULL                       COMMENT 'Transaction de crédit initial',
    PRIMARY KEY (`id`),
    INDEX `idx_loans_account`  (`account_id`),
    INDEX `idx_loans_user`     (`user_id`),
    INDEX `idx_loans_status`   (`status`),
    CONSTRAINT `fk_loans_account`  FOREIGN KEY (`account_id`)  REFERENCES `accounts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_loans_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`    (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_loans_granted`  FOREIGN KEY (`granted_by`)  REFERENCES `users`    (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Échéancier d'un crédit
-- ================================================================

CREATE TABLE IF NOT EXISTS `loan_installments` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `loan_id`         INT UNSIGNED      NOT NULL,
    `due_date`        DATE              NOT NULL               COMMENT 'Date d\'échéance',
    `amount`          DECIMAL(15,2)     NOT NULL               COMMENT 'Montant de la mensualité',
    `status`          ENUM('pending','paid','failed','cancelled')
                                        NOT NULL DEFAULT 'pending',
    `paid_at`         DATETIME          NULL,
    `transaction_id`  INT UNSIGNED      NULL                   COMMENT 'Transaction de débit générée',
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_installments_loan`    (`loan_id`),
    INDEX `idx_installments_status`  (`status`),
    INDEX `idx_installments_due`     (`due_date`),
    CONSTRAINT `fk_installments_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
