# 🏦 BankApp — Simulation bancaire en PHP

Application de simulation bancaire complète développée en PHP 8.2 avec une architecture MVC, MariaDB et Docker.

## Fonctionnalités

- **Authentification sécurisée** : inscription, connexion email/mot de passe, connexion rapide par PIN, réinitialisation de mot de passe par email, rate-limiting des tentatives échouées.
- **Multi-comptes** : comptes courant, professionnel, joint, épargne, en ligne et mineur, avec gestion du découvert autorisé, du plafond d'épargne et des intérêts.
- **Transactions** : dépenses, entrées, virements internes, virements programmés et récurrents, débits directs (mandats SEPA), débits différés.
- **Crédits** : simulation, demande, validation, échéancier avec intérêts et pénalités de retard, annulation/remboursement.
- **Cartes de paiement** virtuelles : génération de PAN, CVV et expiration, plafonds mensuels, débit immédiat ou différé, terminaux de paiement (TPE) côté pro.
- **Partage d'accès** : accès permanent ou temporaire à d'autres utilisateurs sur un compte.
- **Modération** : gestion des utilisateurs (suspension, bannissement, tutelle des mineurs), des comptes (gel, fermeture) et des crédits.
- **Messagerie interne** et **système de tickets** de support.
- **Notifications** en temps réel et **audit log** complet des actions sensibles.
- **Multi-devise** avec conversion via des taux de change configurables.
- **API REST** pour les paiements externes (clients API, signature HMAC).
- **Responsive** : utilisable sur PC, tablette et mobile (Android/iOS).

## Stack technique

| Composant            | Technologie                                                   |
|----------------------|---------------------------------------------------------------|
| Backend              | PHP 8.2, architecture MVC orientée objet                      |
| Base de données      | MariaDB 11 / MySQL 8 — schéma versionné (52 migrations SQL)   |
| Frontend             | HTML5, CSS3 responsive (mobile-first), JavaScript vanilla     |
| Serveur HTTP         | Apache + mod_rewrite (dev) / Nginx derrière Traefik (prod)    |
| Conteneurisation     | Docker / Docker Compose                                       |
| Tâches planifiées    | Cron (intérêts, échéances, transactions programmées)          |
| Tests                | PHPUnit 9.6 (SQLite in-memory pour les tests)                 |
| Librairies tierces   | mPDF, PHPMailer, chillerlan/php-qrcode                        |

## Installation avec Docker (recommandé)

```bash
cp .env.example .env
# Générer une clé APP_KEY robuste (≥ 32 caractères) :
sed -i "s/^APP_KEY=.*/APP_KEY=$(openssl rand -hex 32)/" .env

# Démarrer en mode développement (avec MariaDB et Mailpit) :
docker compose -f docker-compose.dev.yml up --build
```

L'application est accessible sur **http://localhost:8083**, Mailpit sur **http://localhost:8025**.

Le conteneur applicatif applique automatiquement les migrations SQL au démarrage (`database/migrate.php`) et configure les tâches cron.

## Installation sans Docker

```bash
composer install
cp .env.example .env
# Renseigner DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_KEY...
php database/migrate.php
php -S localhost:8080 -t public/
```

## Architecture

```
app/
├── Config/          # Configuration (DB, mailer)
├── Controllers/     # Contrôleurs MVC (Auth, Dashboard, Account, Transaction,
│   │                # Transfer, Loan, Card, Pos, Moderation, Profile, Ticket,
│   │                # Notification, Message, AuditLog, ApiClient...)
│   └── Api/         # Contrôleurs API REST
├── Core/            # Framework (Router, Controller, Model, Database, Session,
│                    # CSRF, JWT, Middleware)
├── Helpers/         # Fonctions utilitaires (validations, formatage, SIRET...)
├── Models/          # Modèles métier (User, Account, Transaction, Loan, Card...)
├── Services/        # Services applicatifs (Mailer, CurrencyConverter)
└── Views/           # Vues PHP par feature (layouts, dashboard, accounts...)
database/
├── migrate.php      # Lanceur de migrations
├── migrations/      # 52 fichiers SQL versionnés (001_initial.sql à 052_...)
└── process_*.php    # Tâches cron (intérêts, échéances, transactions...)
docker/              # Configuration Nginx + crontab
public/              # Front controller (index.php) + assets CSS/JS
tests/               # Tests PHPUnit (Unit/)
data/                # Volumes persistants (MySQL, uploads)
```

## Stockage des données

Toutes les données sont persistées en base **MariaDB/MySQL**. Le schéma est défini par 52 migrations SQL ordonnées dans `database/migrations/` et appliquées automatiquement au démarrage du conteneur (ou via `php database/migrate.php`).

Aucune donnée métier n'est stockée en JSON ; seuls les volumes Docker `data/mysql` (base) et `data/uploads` (futurs uploads) sont persistés sur disque.

## Tests

```bash
./vendor/bin/phpunit
# ou avec sortie lisible :
./vendor/bin/phpunit --testdox
```

Les tests utilisent une base SQLite en mémoire (`tests/TestDatabase.php`).

## Sécurité

- **APP_KEY** : longueur minimale 32 caractères vérifiée au runtime (fail-fast).
- **Mots de passe** : hashés avec bcrypt (`password_hash` / `password_verify`).
- **Sessions** : régénération d'ID après connexion, cookie `httponly` + `samesite=Lax`, flag `secure` activé automatiquement derrière HTTPS.
- **CSRF** : token par session, validation `hash_equals()` sur chaque requête POST/PUT/DELETE.
- **XSS** : échappement systématique via le helper `e()` (`htmlspecialchars`).
- **En-têtes HTTP** : CSP, HSTS (en HTTPS), X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy.
- **SQL Injection** : PDO en *prepared statements* partout, `ATTR_EMULATE_PREPARES=false`.
- **Rate limiting** : blocage 30 minutes après plusieurs tentatives de connexion échouées.
- **Audit log** : journalisation structurée des actions sensibles (login, virement, modération…).

## Améliorations identifiées

Voir [IMPROVEMENTS.md](IMPROVEMENTS.md) pour la liste des axes d'amélioration ouverts.
