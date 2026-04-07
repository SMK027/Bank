-- ─────────────────────────────────────────────────────────────────
-- 024 : Limitation du taux de tentatives de connexion par adresse IP
-- ─────────────────────────────────────────────────────────────────
-- Règle : 5 échecs dans une fenêtre glissante de 15 minutes
--         → blocage de l'IP pendant 30 minutes.
-- Le compte utilisateur n'est pas suspendu, seul l'accès IP est bloqué.
-- ─────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `login_rate_limits` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `ip_address`    VARCHAR(45)      NOT NULL COMMENT 'IPv4 ou IPv6',
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 1  COMMENT 'Tentatives échouées dans la fenêtre active',
    `window_start`  DATETIME         NOT NULL             COMMENT 'Début de la fenêtre glissante (15 min)',
    `blocked_until` DATETIME         NULL     DEFAULT NULL COMMENT 'Fin du blocage (30 min après le 5e échec)',
    `created_at`    DATETIME         NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ip`            (`ip_address`),
    KEY           `idx_blocked`   (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
