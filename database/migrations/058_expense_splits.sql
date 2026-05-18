-- 058_expense_splits.sql
-- Répartition de dépenses entre plusieurs utilisateurs

CREATE TABLE IF NOT EXISTS `expense_splits` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id` INT UNSIGNED NOT NULL,
    `account_id`     INT UNSIGNED NOT NULL,
    `created_by`     INT UNSIGNED NOT NULL,
    `total_amount`   DECIMAL(12,2) NOT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expense_splits_tx`      (`transaction_id`),
    KEY `idx_expense_splits_account` (`account_id`),
    KEY `idx_expense_splits_creator` (`created_by`),
    CONSTRAINT `fk_expense_split_tx`      FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_expense_split_account` FOREIGN KEY (`account_id`)     REFERENCES `accounts`      (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_expense_split_creator` FOREIGN KEY (`created_by`)     REFERENCES `users`         (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expense_split_participants` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `split_id`           INT UNSIGNED NOT NULL,
    `user_id`            INT UNSIGNED NOT NULL,
    `amount`             DECIMAL(12,2) NOT NULL,
    `payment_request_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_split_part_split` (`split_id`),
    KEY `idx_split_part_user`  (`user_id`),
    KEY `idx_split_part_pr`    (`payment_request_id`),
    CONSTRAINT `fk_split_part_split` FOREIGN KEY (`split_id`)           REFERENCES `expense_splits`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_split_part_user`  FOREIGN KEY (`user_id`)            REFERENCES `users`             (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_split_part_pr`    FOREIGN KEY (`payment_request_id`) REFERENCES `payment_requests`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
