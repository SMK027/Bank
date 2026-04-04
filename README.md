# 🏦 BankApp — Simulation bancaire en PHP

Application de simulation bancaire développée en PHP avec une architecture MVC, Docker et stockage en fichiers JSON.

## Fonctionnalités

- **Inscription / Connexion** sécurisée avec sessions et protection CSRF
- **Multi-comptes** : créez plusieurs comptes bancaires avec nom, devise et découvert autorisé
- **Transactions** : enregistrez vos dépenses et entrées d'argent avec catégorie et commentaire
- **Partage d'accès** : donnez un accès permanent ou temporaire à d'autres utilisateurs
- **Tableau de bord** : vue globale avec soldes colorés (vert = positif, rouge = négatif) et total
- **Responsive** : fonctionne sur PC, tablette et mobile (Android/iOS)

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.2, architecture MVC orientée objet |
| Stockage | Fichiers JSON dans le dossier `data/` |
| Frontend | HTML5, CSS3 responsive (mobile-first), JavaScript vanilla |
| Serveur | Apache avec mod_rewrite |
| Conteneurisation | Docker / Docker Compose |
| Tests | PHPUnit 9.6 |

## Installation avec Docker

```bash
cp .env.example .env
docker compose -f docker-compose.dev.yml up --build
```

L'application sera accessible sur **http://localhost:8080**.

## Installation sans Docker

```bash
composer install
cp .env.example .env
php -S localhost:8080 -t public/
```

## Architecture

```
app/
├── Config/          # Configuration
├── Controllers/     # Contrôleurs MVC (Auth, Dashboard, Account, Transaction, Access)
├── Core/            # Framework (Model JSON, Controller, Router, Session, CSRF, JWT)
├── Helpers/         # Fonctions utilitaires
├── Models/          # Modèles (User, Account, Transaction, AccountAccess)
└── Views/           # Vues PHP (layouts, dashboard, accounts, auth)
data/                # Stockage JSON (users, accounts, transactions, accesses)
public/              # Point d'entrée + assets CSS/JS
tests/               # Tests unitaires PHPUnit
```

## Données

Les données sont stockées en clair au format JSON dans le dossier `data/` :
- `users.json` — Utilisateurs inscrits
- `accounts.json` — Comptes bancaires
- `transactions.json` — Dépenses et entrées d'argent
- `accesses.json` — Partages d'accès entre utilisateurs

## Tests

```bash
./vendor/bin/phpunit
```

## Sécurité

- **XSS** : échappement via `e()` (htmlspecialchars)
- **CSRF** : token par session, validé sur chaque POST
- **Mots de passe** : hashés avec bcrypt
- **Sessions** : régénération d'ID après connexion
