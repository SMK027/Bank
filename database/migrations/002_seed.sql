-- ============================================================
-- Données initiales BankApp (seed)
-- Converti depuis les fichiers JSON data/
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ─── Utilisateurs ────────────────────────────────────────────
INSERT INTO `users`
    (`id`, `username`, `email`, `password`, `global_role`, `created_at`, `updated_at`)
VALUES
    (1, 'SMK',  'contact@leofranz.fr',  '$2y$10$sqHCDqcCbs.gbWjvMavauub4BmabT7GoC3jRwwPRUsqDAMrjP8NH.', 'moderator', '2026-04-04 18:43:41', '2026-04-04 17:43:04'),
    (2, 'test', 'leofranz46@gmail.com', '$2y$10$A3U.ak0T5QTr8vL7JSdlSOqtds77/Y8QZnAkP5iwmk8ZBm3eV.3Cm', 'user',      '2026-04-04 18:45:52', '2026-04-04 18:45:52')
ON DUPLICATE KEY UPDATE
    `username`    = VALUES(`username`),
    `email`       = VALUES(`email`),
    `password`    = VALUES(`password`),
    `global_role` = VALUES(`global_role`),
    `created_at`  = VALUES(`created_at`),
    `updated_at`  = VALUES(`updated_at`);

ALTER TABLE `users` AUTO_INCREMENT = 3;

-- ─── Comptes bancaires ────────────────────────────────────────
INSERT INTO `accounts`
    (`id`, `user_id`, `name`, `currency`, `overdraft`, `type`, `frozen`, `created_at`, `updated_at`)
VALUES
    (1, 1, 'Compte courant', 'EUR',    0, 'standard', 0, '2026-04-04 18:43:49', '2026-04-04 19:13:53'),
    (2, 1, 'Compte joint',   'EUR',  100, 'joint',    0, '2026-04-04 18:44:37', '2026-04-04 19:14:02'),
    (3, 2, 'Revolut',        'EUR',    0, 'online',   0, '2026-04-04 18:53:45', '2026-04-04 20:13:04'),
    (4, 2, 'Compte Lina',    'EUR',    0, 'minor',    0, '2026-04-04 19:14:38', '2026-04-04 19:14:38'),
    (5, 1, 'SARL Test',      'EUR', 1500, 'pro',      0, '2026-04-04 21:02:38', '2026-04-04 21:02:38')
ON DUPLICATE KEY UPDATE
    `user_id`    = VALUES(`user_id`),
    `name`       = VALUES(`name`),
    `currency`   = VALUES(`currency`),
    `overdraft`  = VALUES(`overdraft`),
    `type`       = VALUES(`type`),
    `frozen`     = VALUES(`frozen`),
    `created_at` = VALUES(`created_at`),
    `updated_at` = VALUES(`updated_at`);

ALTER TABLE `accounts` AUTO_INCREMENT = 6;

-- ─── Transactions ─────────────────────────────────────────────
INSERT INTO `transactions`
    (`id`, `account_id`, `user_id`, `type`, `amount`, `category`, `comment`, `scheduled_at`, `created_at`, `updated_at`)
VALUES
    ( 3, 2, 2, 'income',  550,    'Salaire',      '',               NULL,                    '2026-04-04 18:52:33', '2026-04-04 18:52:33'),
    ( 4, 2, 1, 'expense', 400,    'Logement',     'Loyer',          NULL,                    '2026-04-04 18:58:10', '2026-04-04 18:58:10'),
    ( 5, 2, 2, 'expense',  50,    'Transport',    '',               NULL,                    '2026-04-04 18:58:52', '2026-04-04 18:58:52'),
    (10, 4, 2, 'income',    0.27, 'Alimentation', '',               NULL,                    '2026-04-04 19:15:00', '2026-04-04 19:15:00'),
    (11, 3, 2, 'income',  550,    'Salaire',      '',               NULL,                    '2026-04-04 19:44:40', '2026-04-04 19:44:40'),
    (12, 3, 2, 'expense', 150,    'Transport',    '',               NULL,                    '2026-04-04 20:13:22', '2026-04-04 20:13:22'),
    (13, 3, 2, 'expense', 345,    'Factures',     'Gaz',            NULL,                    '2026-04-04 20:13:53', '2026-04-04 20:13:53'),
    (14, 3, 1, 'expense', 250,    'Autre',        'Frais CB',       NULL,                    '2026-04-04 20:24:52', '2026-04-04 20:24:52'),
    (15, 3, 1, 'income',  1150,   'Salaire',      '',               NULL,                    '2026-04-04 21:09:17', '2026-04-04 21:09:17'),
    (16, 2, 1, 'expense', 150,    'Logement',     'Loyer',          '2026-04-04 21:13:00',   '2026-04-04 21:12:53', '2026-04-04 21:12:53'),
    (17, 2, 1, 'expense',  45,    'Virement',     'Virement',       NULL,                    '2026-04-04 21:37:46', '2026-04-04 21:37:46'),
    (18, 3, 1, 'income',   45,    'Virement',     'Virement',       NULL,                    '2026-04-04 21:37:46', '2026-04-04 21:37:46'),
    (20, 2, 1, 'income',   50,    'Virement',     'Virement — test','2026-04-04 22:00:00',   '2026-04-04 21:59:06', '2026-04-04 21:59:06'),
    (23, 2, 1, 'income',  3000,   'Virement',     'Virement',       '2026-04-04 22:02:00',   '2026-04-04 22:01:57', '2026-04-04 22:01:57'),
    (24, 1, 1, 'income',  75000,  'Autre',        '',               NULL,                    '2026-04-04 22:22:27', '2026-04-04 22:22:27'),
    (25, 1, 1, 'income',  15000,  'Salaire',      '',               NULL,                    '2026-04-04 22:22:58', '2026-04-04 22:22:58'),
    (26, 1, 1, 'income',  15000,  'Salaire',      '',               NULL,                    '2026-04-04 22:23:08', '2026-04-04 22:23:08'),
    (27, 1, 1, 'income',  895000, 'Autre',        '',               NULL,                    '2026-04-04 22:23:52', '2026-04-04 22:23:52'),
    (28, 1, 1, 'expense', 5000,   'Virement',     'Virement',       NULL,                    '2026-04-04 22:24:18', '2026-04-04 22:24:18'),
    (29, 5, 1, 'income',  5000,   'Virement',     'Virement',       NULL,                    '2026-04-04 22:24:18', '2026-04-04 22:24:18')
ON DUPLICATE KEY UPDATE
    `account_id`   = VALUES(`account_id`),
    `user_id`      = VALUES(`user_id`),
    `type`         = VALUES(`type`),
    `amount`       = VALUES(`amount`),
    `category`     = VALUES(`category`),
    `comment`      = VALUES(`comment`),
    `scheduled_at` = VALUES(`scheduled_at`),
    `created_at`   = VALUES(`created_at`),
    `updated_at`   = VALUES(`updated_at`);

ALTER TABLE `transactions` AUTO_INCREMENT = 30;

-- ─── Accès partagés ────────────────────────────────────────────
INSERT INTO `account_accesses`
    (`id`, `account_id`, `user_id`, `type`, `expires_at`, `created_at`, `updated_at`)
VALUES
    (1, 2, 2, 'permanent', NULL, '2026-04-04 18:53:22', '2026-04-04 18:53:22')
ON DUPLICATE KEY UPDATE
    `account_id` = VALUES(`account_id`),
    `user_id`    = VALUES(`user_id`),
    `type`       = VALUES(`type`),
    `expires_at` = VALUES(`expires_at`),
    `created_at` = VALUES(`created_at`),
    `updated_at` = VALUES(`updated_at`);

ALTER TABLE `account_accesses` AUTO_INCREMENT = 2;

-- ─── Virements ─────────────────────────────────────────────────
INSERT INTO `transfers`
    (`id`, `from_account_id`, `to_account_id`, `user_id`, `amount`, `motif`, `status`, `scheduled_at`, `executed_at`, `debit_tx_id`, `credit_tx_id`, `created_at`, `updated_at`)
VALUES
    (1, 1, 5, 1, 5000, '', 'success', NULL, '2026-04-04 22:24:18', 28, 29, '2026-04-04 22:24:18', '2026-04-04 22:24:18')
ON DUPLICATE KEY UPDATE
    `from_account_id` = VALUES(`from_account_id`),
    `to_account_id`   = VALUES(`to_account_id`),
    `user_id`         = VALUES(`user_id`),
    `amount`          = VALUES(`amount`),
    `motif`           = VALUES(`motif`),
    `status`          = VALUES(`status`),
    `scheduled_at`    = VALUES(`scheduled_at`),
    `executed_at`     = VALUES(`executed_at`),
    `debit_tx_id`     = VALUES(`debit_tx_id`),
    `credit_tx_id`    = VALUES(`credit_tx_id`),
    `created_at`      = VALUES(`created_at`),
    `updated_at`      = VALUES(`updated_at`);

ALTER TABLE `transfers` AUTO_INCREMENT = 2;

SET foreign_key_checks = 1;
