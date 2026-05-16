# Améliorations proposées — BankApp

Audit réalisé le 16 mai 2026. Liste des axes d'amélioration classés par priorité.

## 🔴 Critique (à traiter en priorité)

1. **`APP_KEY` par défaut non sécurisée** — `app/Core/JWT.php` tombe sur `'default_insecure_key'` si la variable d'environnement n'est pas définie. Ajouter un *fail-fast* au démarrage si `APP_KEY` est absent ou de longueur insuffisante (< 32 caractères).
2. **En-têtes de sécurité HTTP absents** — Aucun CSP, HSTS, X-Frame-Options, X-Content-Type-Options. À ajouter dans `public/index.php` ou `docker/nginx.conf`.
3. **Cookie de session `secure=false`** — `app/Core/Session.php` : forcer `secure=true` en production via `APP_ENV`/`HTTPS`.
4. **README désynchronisé** — `README.md` parle de stockage JSON alors que tout passe par MariaDB avec 52 migrations. À réécrire.
5. **Requêtes N+1 sur le tableau de bord** — `app/Controllers/DashboardController.php` appelle `getBalance()` + `getFutureBalance()` dans une boucle. Idem dans `CardController` et `AccountController`. Créer des méthodes batch.

## 🟠 Important (avant prod / v1)

6. **Indexes DB manquants** sur les FK (`accounts.user_id`, `transactions.account_id`, `transfers.user_id`, `transactions.scheduled_at`). Ajouter une migration `053_add_missing_indexes.sql`.
7. **Pas de DI Container** — duplication marquée entre `ModerationController` et `ModerationLoanController` (~40 lignes identiques). Introduire un container minimal ou PHP-DI.
8. **Middleware route-level** — actuellement chaque controller appelle manuellement `requireAuth`. Ajouter un système de middleware déclaratif dans `app/Core/Router.php`.
9. **Tests insuffisants** (~15–20 % de couverture, 19 tests unitaires). Manquent les controllers critiques : `AuthController`, `AccountController`, `TransferController`, `LoanController`, `ModerationController`.
10. **Logging d'erreurs absent** — seul l'audit fonctionnel est loggé. Ajouter un handler global d'exceptions vers fichier/syslog.
11. **CI/CD absente** — pas de `.github/workflows/`. Ajouter un pipeline GitHub Actions (PHPUnit + PHPStan + composer audit).
12. **Permissions Docker trop larges** — `Dockerfile` fait `chmod -R 777 data`. À restreindre à 755/775 avec `chown www-data`.

## 🟡 Évolutions souhaitables

13. **Internationalisation** — tous les textes sont en français hardcodé. Mettre en place un helper `t()` + dictionnaires.
14. **Accessibilité WCAG** — ARIA labels partiels, peu de balises sémantiques (`<section>`, `<article>`), styles de focus à vérifier.
15. **Cache** (APCu / Redis) sur les soldes et taux de change.
16. **Documentation API** — OpenAPI/Swagger pour les contrôleurs `app/Controllers/Api/`.
17. **Outils qualité** — PHPStan niveau 6+, PHP_CodeSniffer (PSR-12), `composer audit` en CI.
18. **Monitoring** — Sentry ou équivalent pour les erreurs en prod.
19. **Builds Docker multiarch** (`linux/amd64,linux/arm64`).

## ✅ Points forts à conserver

- PDO en *prepared statements* partout, `ATTR_EMULATE_PREPARES=false`
- Rate limiting login + bcrypt + régénération de session
- CSRF avec `hash_equals()`
- Audit log structuré
- 52 migrations versionnées, cron jobs Docker propres
- Séparation dev/prod dans docker-compose
