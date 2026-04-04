# ToolBox — Framework MVC PHP

Boîte à outils PHP clé en main pour démarrer rapidement un projet web avec une architecture MVC solide.

## Composants inclus

| Composant | Description |
|-----------|-------------|
| **MVC** | Router, Controller abstrait, Model abstrait avec CRUD PDO |
| **Sécurité** | CSRF, Session sécurisée, JWT (API), Middleware d'accès |
| **Docker** | Dockerfile PHP-Apache, Docker Compose (dev + prod) |
| **Migrations** | Système de migrations SQL automatisé |
| **CSS** | Framework CSS responsive mobile-first avec variables CSS |
| **Tests** | Suite PHPUnit unitaire complète |

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.2, architecture MVC custom |
| Base de données | MySQL 8.0 avec PDO (requêtes préparées) |
| Frontend | HTML5, CSS3 (mobile-first, variables CSS, Flexbox/Grid), JavaScript vanilla |
| Serveur | Apache avec mod_rewrite |
| Conteneurisation | Docker / Docker Compose |
| Tests | PHPUnit 9.6 |
| Autoloading | Composer PSR-4 |

## Installation avec Docker

```bash
# Cloner le projet
git clone <url-du-repo> mon-projet
cd mon-projet

# Configurer l'environnement
cp .env.example .env

# Lancer les conteneurs
docker-compose -f docker-compose.dev.yml up -d
```

L'application sera accessible sur :
- **Application** : http://localhost:8080
- **phpMyAdmin** : http://localhost:8081

## Installation manuelle

```bash
# Installer les dépendances
composer install

# Configurer les variables d'environnement
cp .env.example .env

# Lancer le serveur de développement PHP
php -S localhost:8080 -t public/
```

## Architecture

```
app/
├── Config/          # Configuration (Database singleton PDO)
├── Controllers/     # Contrôleurs MVC
│   └── Api/         # Contrôleurs API REST (JWT)
├── Core/            # Framework (Router, Controller, Model, Session, CSRF, JWT, Middleware)
├── Helpers/         # Fonctions utilitaires globales
├── Models/          # Modèles de données
└── Views/           # Vues PHP
    ├── auth/        # Pages de connexion / inscription
    ├── errors/      # Pages d'erreur
    ├── home/        # Page d'accueil
    ├── layouts/     # Layout principal
    └── partials/    # Composants réutilisables
database/
├── migrate.php      # Script de migration automatique
└── migrations/      # Fichiers SQL numérotés
public/
├── css/             # Feuilles de style
├── js/              # JavaScript
├── index.php        # Front controller + routes
└── .htaccess        # Réécriture Apache
tests/
└── Unit/            # Tests unitaires PHPUnit
```

## Sécurité

- **Injection SQL** : toutes les requêtes utilisent des requêtes préparées PDO
- **XSS** : échappement systématique via la fonction `e()` (htmlspecialchars)
- **CSRF** : token unique par session, validé sur chaque formulaire POST
- **Mots de passe** : hashés avec `password_hash()` (bcrypt)
- **Sessions** : régénération d'ID après connexion
- **JWT** : authentification API avec HMAC-SHA256

## Tests

```bash
# Lancer tous les tests
./vendor/bin/phpunit

# Lancer uniquement les tests unitaires
./vendor/bin/phpunit --testsuite Unit

# Avec couverture de code
./vendor/bin/phpunit --coverage-html coverage/
```

## Personnalisation

Ce projet est conçu comme point de départ. Adaptez les éléments suivants :

1. **composer.json** : Changez le `name` et la `description`
2. **.env.example** : Ajustez les variables selon votre projet
3. **docker-compose.yml** : Modifiez les labels Traefik pour votre domaine
4. **public/index.php** : Définissez vos propres routes
5. **app/Models/** : Créez vos modèles métier
6. **app/Views/** : Développez vos vues
7. **database/migrations/** : Écrivez vos migrations SQL

## Licence

Projet personnel — libre d'utilisation.
