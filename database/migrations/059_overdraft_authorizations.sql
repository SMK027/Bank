-- Migration 059 : Autorisations de dépassement de découvert émises par la modération
-- Chaque autorisation ajoute un plafond supplémentaire (extra_limit) sur le découvert
-- d'un compte donné, valable entre start_date et end_date (null = sans fin).
-- Elle reste active jusqu'à révocation explicite (revoked_at NOT NULL).

CREATE TABLE IF NOT EXISTS overdraft_authorizations (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    account_id    INT UNSIGNED     NOT NULL,
    moderator_id  INT UNSIGNED     NOT NULL,
    extra_limit   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    reason        VARCHAR(500)     NOT NULL DEFAULT '',
    start_date    DATE             NOT NULL,
    end_date      DATE                     DEFAULT NULL,
    revoked_at    DATETIME                 DEFAULT NULL,
    revoked_by    INT UNSIGNED             DEFAULT NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_oa_account    FOREIGN KEY (account_id)   REFERENCES accounts (id) ON DELETE CASCADE,
    CONSTRAINT fk_oa_moderator  FOREIGN KEY (moderator_id) REFERENCES users    (id) ON DELETE CASCADE,
    CONSTRAINT fk_oa_revoker    FOREIGN KEY (revoked_by)   REFERENCES users    (id) ON DELETE SET NULL,

    INDEX idx_oa_account   (account_id),
    INDEX idx_oa_moderator (moderator_id),
    INDEX idx_oa_active    (account_id, revoked_at, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
