-- ============================================================
-- 007 : Système de ticketing interne
-- ============================================================

CREATE TABLE IF NOT EXISTS `tickets` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT UNSIGNED    NOT NULL,
    `type`         VARCHAR(60)     NOT NULL,
    `subject`      VARCHAR(255)    NOT NULL,
    `status`       VARCHAR(30)     NOT NULL DEFAULT 'open',
    `account_id`   INT UNSIGNED    NULL DEFAULT NULL,
    `priority`     VARCHAR(20)     NOT NULL DEFAULT 'normal',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ticket_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ticket_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `ticket_id`    INT UNSIGNED    NOT NULL,
    `user_id`      INT UNSIGNED    NOT NULL,
    `is_staff`     TINYINT(1)      NOT NULL DEFAULT 0,
    `body`         TEXT            NOT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tmsg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tmsg_user`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
