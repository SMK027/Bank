-- ============================================================
-- Migration 047 : Remboursements partiels des paiements TPE
-- Chaque remboursement partiel est tracé dans une ligne dédiée,
-- permettant plusieurs remboursements successifs sur un même paiement.
-- ============================================================

CREATE TABLE IF NOT EXISTS `api_payment_refunds` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `payment_id`      INT UNSIGNED  NOT NULL,
    `amount`          DOUBLE        NOT NULL,
    `reason`          VARCHAR(255)  NOT NULL DEFAULT '',
    `refunded_by`     INT UNSIGNED  NULL,
    `refunded_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `customer_tx_id`  INT UNSIGNED  NULL     COMMENT 'Transaction de crédit côté client',
    `merchant_tx_id`  INT UNSIGNED  NULL     COMMENT 'Transaction de débit côté commerçant',
    KEY `idx_refunds_payment` (`payment_id`),
    CONSTRAINT `fk_refunds_payment` FOREIGN KEY (`payment_id`)  REFERENCES `api_payments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_refunds_user`    FOREIGN KEY (`refunded_by`) REFERENCES `users`        (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
