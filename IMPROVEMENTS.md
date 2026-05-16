# Améliorations proposées — BankApp

Audit réalisé le 16 mai 2026. Liste des axes d'amélioration classés par priorité.

## 🔴 Critique (à traiter en priorité)

1. **`APP_KEY` par défaut non sécurisée** — `app/Core/JWT.php` tombe sur `'default_insecure_key'` si la variable d'environnement n'est pas définie. Ajouter un *fail-fast* au démarrage si `APP_KEY` est absent ou de longueur insuffisante (< 32 caractères).
2. **En-têtes de sécurité HTTP absents** — Aucun CSP, HSTS, X-Frame-Options, X-Content-Type-Options. À ajouter dans `public/index.php` ou `docker/nginx.conf`.
3. **Cookie de session `secure=false`** — `app/Core/Session.php` : forcer `secure=true` en production via `APP_ENV`/`HTTPS`.
4. **README désynchronisé** — `README.md` parle de stockage JSON alors que tout passe par MariaDB avec 52 migrations. À réécrire.
5. **Requêtes N+1 sur le tableau de bord** — `app/Controllers/DashboardController.php` appelle `getBalance()` + `getFutureBalance()` dans une boucle. Idem dans `CardController` et `AccountController`. Créer des méthodes batch.

## 🟠 Important (avant prod / v1)

6. **Indexes DB manquants** sur les colonnes utilisées dans les filtres WHERE des tâches cron et du calcul de solde à venir (`transactions.scheduled_at`, `transfers(status, scheduled_at)`, `recurring_transfers(status, next_execution_at)`). ✅ *Traité dans la migration `053_add_missing_indexes.sql`.*
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

---

# 🎯 Améliorations fonctionnelles (simulateur)

Ces propositions tirent parti du statut de simulateur de l'application (pas de contrainte réglementaire réelle) pour enrichir la valeur pédagogique et démonstrative.

## Expérience utilisateur

1. **Onboarding & données de démo** — Au premier login, seeder créant un compte courant + une épargne avec quelques transactions/virements/débits factices.
2. **Mode "voyage dans le temps"** — Bouton modérateur pour avancer la date virtuelle (+1 mois), déclenchant manuellement intérêts, échéances de crédit, mandats.
3. **Tour guidé** — Overlay pas-à-pas (intro.js / Shepherd.js) au premier lancement.
4. **Statistiques personnelles** — Graphiques d'évolution du solde, répartition des dépenses par catégorie (Chart.js), top marchands, comparaison mois N vs N-1.
5. **Budgets & objectifs** — Budget mensuel par catégorie avec alertes à 80 % / 100 %.
6. **Cagnottes / pots communs** — Mini-compte partagé alimenté par plusieurs utilisateurs.
7. **Export / import** — Export CSV/OFX/PDF des relevés (mPDF déjà installé), import OFX.
8. **Recherche globale** — Barre `Ctrl+K` qui cherche dans transactions, contacts, comptes, tickets.

## Produits bancaires

9. **IBAN + RIB téléchargeable** — IBAN factice (FR + checksum) par compte, RIB PDF.
10. **Virements SEPA externes simulés** — Saisie d'IBAN externe, statut "en cours" puis finalisation après X minutes via cron.
11. **Cartes virtuelles éphémères** — Cartes à usage unique qui s'autodétruisent.
12. **Catégorisation automatique** — Reconnaissance des libellés/marchands (regex) pour auto-tagger.
13. **Plafonds & limites configurables** — Plafond retrait quotidien, mode "vacances", blocage temporaire d'une carte.
14. **Découvert progressif** — Notification sous seuil + calcul d'agios fictifs.
15. **Codes promo / cashback fictif** — Récompenses sur certaines catégories.

## Multi-utilisateurs / social

16. **Liste de bénéficiaires** — Carnet d'adresses IBAN+nom avec pré-validation.
17. **Demande d'argent** — Request-to-pay entre utilisateurs.
18. **Partage de dépense** — Transaction splittable entre N utilisateurs.
19. **Coffres d'épargne (savings goals)** — Sous-compte virtuel pour un objectif, virement automatique mensuel.

## Modération / observabilité

20. **Tableau de bord modérateur** — Vue d'ensemble : utilisateurs actifs, volume transactions, comptes gelés, tickets en attente.
21. **Détection d'anomalies pédagogique** — Marquage automatique des transactions suspectes (montant, fréquence, IBAN nouveau).
22. **Audit log utilisateur** — Permettre à l'utilisateur de voir son propre journal d'actions.
23. **Mode "incident"** — Bouton admin simulant une panne d'un service, pour démontrer la résilience UX. *(Voir aussi : système de feature flags ci-dessous.)*

## API & intégrations

24. **Webhooks sortants** — URL appelée sur événement (transaction reçue, solde sous seuil).
25. **Sandbox API publique** — Documenter la fake banking API + tableau de bord de clés API et logs.
26. **Mode SCA / 3-D Secure simulé** — Validation par PIN ou code reçu en messagerie interne.

## Plateforme

27. **PWA installable** — Manifest + service worker (mode hors-ligne lecture).
28. **Notifications push** (Web Push) — En complément de la table notifications existante.
29. **Mode sombre** — Toggle persisté par utilisateur.
30. **Locale switcher EN/FR** — Lié à l'item i18n.

## Couche pédagogique

31. **Bandeau "simulateur"** — Mention permanente discrète "Application fictive — aucune valeur réelle".
32. **Explications contextuelles** — Tooltips expliquant les concepts (mandat SEPA, débit différé, intérêts composés, ratio d'endettement).
33. **Scénarios prêts à l'emploi** — Boutons "Charger scénario : étudiant", "famille", "TPE artisan" avec données cohérentes.
34. **Mode comparaison** — Visualiser côte-à-côte deux simulations de crédit.

---

# 🚦 Feature flags / interrupteurs de fonctionnalité

Système permettant à un modérateur de **désactiver dynamiquement** certaines fonctionnalités du site sans déploiement : connexion, inscription, ouverture de compte, virements, exécution d'opérations, partage de comptes, etc. Utile pour les démos, la maintenance, ou la simulation d'incidents (cf. item 23).

- Stockage : table `feature_flags` (`key`, `enabled`, `label`, `description`).
- Helper global `feature_enabled('key')` + `feature_require('key')`.
- Page de modération `/moderation/features` pour basculer les flags.
- Vue dédiée "Fonctionnalité indisponible" affichée quand un flag est désactivé.
- Toute modification est tracée dans l'audit log.

