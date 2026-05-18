-- ============================================================
-- Migration 056 — Demandes d'argent (Request-to-Pay)
-- ============================================================
-- Un utilisateur peut envoyer une demande d'argent à un autre
-- utilisateur. Le destinataire peut payer, refuser ou laisser
-- la demande expirer.
-- ============================================================

CREATE TABLE IF NOT EXISTS `payment_requests` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `requester_id`    INT UNSIGNED  NOT NULL COMMENT 'Utilisateur qui demande l''argent',
    `recipient_id`    INT UNSIGNED  NOT NULL COMMENT 'Utilisateur à qui on demande',
    `from_account_id` INT UNSIGNED  NULL     COMMENT 'Compte créditeur (du demandeur)',
    `to_account_id`   INT UNSIGNED  NULL     COMMENT 'Compte débiteur (du payeur, défini au moment du paiement)',
    `transfer_id`     INT UNSIGNED  NULL     COMMENT 'Virement généré lors du paiement',
    `amount`          DECIMAL(12,2) NOT NULL,
    `currency`        VARCHAR(10)   NOT NULL DEFAULT 'EUR',
    `motif`           VARCHAR(255)  NOT NULL DEFAULT '',
    `status`          ENUM('pending','paid','refused','cancelled','expired') NOT NULL DEFAULT 'pending',
    `expires_at`      DATETIME      NULL     COMMENT 'Date d''expiration automatique',
    `paid_at`         DATETIME      NULL,
    `refused_at`      DATETIME      NULL,
    `cancelled_at`    DATETIME      NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pr_requester`    (`requester_id`),
    INDEX `idx_pr_recipient`    (`recipient_id`),
    INDEX `idx_pr_status`       (`status`),
    INDEX `idx_pr_expires_at`   (`expires_at`),
    CONSTRAINT `fk_pr_requester`    FOREIGN KEY (`requester_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_pr_recipient`    FOREIGN KEY (`recipient_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_pr_from_account` FOREIGN KEY (`from_account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pr_transfer`     FOREIGN KEY (`transfer_id`)     REFERENCES `transfers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
