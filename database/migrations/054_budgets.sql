-- ============================================================
-- Migration 054 — Table des budgets mensuels par catégorie
-- ============================================================
-- Chaque utilisateur peut définir un plafond de dépense mensuel
-- par catégorie. Une seule ligne par (user_id, category) grâce
-- à la contrainte UNIQUE — les mises à jour se font via UPSERT.
-- ============================================================

CREATE TABLE IF NOT EXISTS `budgets` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `category`      VARCHAR(64)  NOT NULL,
    `monthly_limit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_budget_user_category` (`user_id`, `category`),
    CONSTRAINT `fk_budgets_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
