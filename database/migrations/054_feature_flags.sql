-- ============================================================
-- Migration 054 — Feature flags / interrupteurs de fonctionnalité
-- ------------------------------------------------------------
-- Permet à un modérateur de désactiver dynamiquement certaines
-- fonctionnalités (connexion, inscription, virements, partage…)
-- sans déploiement. Utile pour maintenance, démos, simulation
-- d'incidents (cf. IMPROVEMENTS.md, item 23).
-- ============================================================

CREATE TABLE IF NOT EXISTS `feature_flags` (
    `flag_key`    VARCHAR(60)   NOT NULL PRIMARY KEY,
    `enabled`     TINYINT(1)    NOT NULL DEFAULT 1,
    `label`       VARCHAR(120)  NOT NULL,
    `description` VARCHAR(255)  NOT NULL DEFAULT '',
    `category`    VARCHAR(40)   NOT NULL DEFAULT 'general',
    `updated_by`  INT UNSIGNED  NULL DEFAULT NULL,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `feature_flags` (`flag_key`, `enabled`, `label`, `description`, `category`) VALUES
    ('auth.login',           1, 'Connexion (email + mot de passe)',     'Permet aux utilisateurs de se connecter avec leur email et leur mot de passe.', 'auth'),
    ('auth.login_pin',       1, 'Connexion rapide par PIN',             'Permet la connexion via code PIN à 6 chiffres.', 'auth'),
    ('auth.register',        1, 'Inscription de nouveaux utilisateurs', 'Permet la création de nouveaux comptes utilisateur.', 'auth'),
    ('auth.password_reset',  1, 'Réinitialisation du mot de passe',     'Active le formulaire « mot de passe oublié » et l''envoi d''emails de réinitialisation.', 'auth'),
    ('accounts.create',      1, 'Ouverture de compte bancaire',         'Permet aux utilisateurs d''ouvrir un nouveau compte (courant, épargne, etc.).', 'accounts'),
    ('accounts.edit',        1, 'Modification d''un compte',            'Édition du nom, du découvert, du type d''un compte.', 'accounts'),
    ('accounts.share',       1, 'Partage d''accès à un compte',         'Octroi d''un accès permanent ou temporaire à un autre utilisateur.', 'accounts'),
    ('transactions.create',  1, 'Création d''opérations manuelles',     'Saisie manuelle de dépenses et de revenus sur un compte.', 'operations'),
    ('transactions.edit',    1, 'Modification / suppression d''opérations', 'Édition et suppression de transactions existantes.', 'operations'),
    ('transfers.create',     1, 'Virements internes',                   'Réalisation de virements entre comptes BankApp.', 'operations'),
    ('transfers.recurring',  1, 'Virements récurrents',                 'Création de virements programmés récurrents.', 'operations'),
    ('deferred_debits',      1, 'Débits différés',                      'Création et gestion des débits différés.', 'operations'),
    ('cards.create',         1, 'Création de cartes bancaires',         'Émission de nouvelles cartes virtuelles.', 'cards'),
    ('loans.simulate',       1, 'Simulation de crédit',                 'Calculatrice de simulation de crédit.', 'loans'),
    ('loans.request',        1, 'Demande de crédit',                    'Soumission d''une demande de crédit.', 'loans');
