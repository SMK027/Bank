-- ============================================================
-- Migration 071 — Feature flag de transfert de compte bancaire
-- ============================================================

INSERT IGNORE INTO `feature_flags` (`flag_key`, `enabled`, `label`, `description`, `category`)
VALUES (
    'accounts.transfer',
    1,
    'Transfert de compte bancaire',
    'Permet de transférer la propriété d\'un compte bancaire à un autre utilisateur.',
    'accounts'
);
