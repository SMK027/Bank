-- Migration 014 : virements récurrents
-- Stocke les modèles de virement répétitif définis par l'utilisateur.
-- Chaque occurrence concrète génère un enregistrement dans la table `transfers`.

CREATE TABLE IF NOT EXISTS `recurring_transfers` (
    `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `from_account_id`   INT UNSIGNED    NOT NULL,
    `to_account_id`     INT UNSIGNED    NOT NULL,
    `user_id`           INT UNSIGNED    NOT NULL,
    `amount`            DOUBLE          NOT NULL,
    `motif`             VARCHAR(255)    NOT NULL DEFAULT '',
    `status`            ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    `interval_days`     INT UNSIGNED    NOT NULL,
    `next_execution_at` DATETIME        NOT NULL,
    `last_executed_at`  DATETIME        NULL DEFAULT NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rec_tr_from` FOREIGN KEY (`from_account_id`) REFERENCES `accounts` (`id`),
    CONSTRAINT `fk_rec_tr_to`   FOREIGN KEY (`to_account_id`)   REFERENCES `accounts` (`id`),
    CONSTRAINT `fk_rec_tr_user` FOREIGN KEY (`user_id`)         REFERENCES `users`    (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
