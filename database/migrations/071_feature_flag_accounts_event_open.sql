-- ============================================================
-- Migration 071 — Feature flag d'ouverture de compte événementiel
-- ============================================================

INSERT IGNORE INTO `feature_flags` (`flag_key`, `enabled`, `label`, `description`, `category`)
VALUES (
    'accounts.event_open',
    1,
    'Ouverture de compte événementiel',
    'Permet la création de comptes événementiels depuis la modération.',
    'accounts'
);