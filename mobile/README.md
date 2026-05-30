# BankApp TPE — application mobile

Application mobile **Expo / React Native / TypeScript** permettant à un compte
**professionnel** ou **modérateur** du site [BankApp](../) d'effectuer des
opérations TPE (débit ou crédit par carte) depuis un téléphone.

## Pré-requis

- Node.js 20+
- Application backend BankApp en cours d'exécution (par défaut `http://localhost:8083`)
- Pour scanner les QR codes : un appareil physique (iOS / Android) ou un émulateur

## Installation

```bash
cd mobile
npm install
```

## Configuration de l'URL de l'API

L'URL de l'API est lue depuis `app.json` (`expo.extra.apiBaseUrl`).
Valeur par défaut : `http://10.0.2.2:8083` (alias de `localhost` depuis l'émulateur Android).

| Cible                      | Valeur à mettre                          |
| -------------------------- | ---------------------------------------- |
| Émulateur Android          | `http://10.0.2.2:8083`                   |
| Simulateur iOS             | `http://localhost:8083`                  |
| Téléphone physique (Wi-Fi) | `http://<IP-PC>:8083` (ex `http://192.168.1.42:8083`) |
| Production                 | `https://bank.leofranz.fr`               |

> Sur téléphone physique, le PC et le téléphone doivent être sur le même réseau,
> et le port 8083 du PC doit être accessible.

## Démarrage

```bash
npx expo start
```

Ensuite, scanner le QR code affiché avec **Expo Go** (Android / iOS).

## Endpoints API consommés

| Méthode | Chemin                       | Description                                          |
| ------- | ---------------------------- | ---------------------------------------------------- |
| POST    | `/api/v1/mobile/login`       | Connexion par email + mot de passe → JWT             |
| GET     | `/api/v1/mobile/status`      | Statut du TPE + comptes pro éligibles                |
| POST    | `/api/v1/mobile/transaction` | Débit ou crédit par carte                            |

L'authentification se fait via `Authorization: Bearer <jwt>` (JWT valable 7 jours).

## Règles d'accès

- Un compte **classique** (ni professionnel, ni modérateur) reçoit
  `403 Accès réservé aux comptes professionnels et à la modération.`
- Si le TPE est **désactivé** globalement par la modération → écran
  bloquant "TPE désactivé".
- Si le compte est **banni du TPE** (tous les comptes pro suspendus) →
  écran bloquant "Accès TPE suspendu".

## Parcours utilisateur

1. **Connexion** : email + mot de passe
2. **Vérifications automatiques** : TPE actif, utilisateur non banni
3. **Saisie** : type (débit/crédit) → carte (manuelle ou scan QR) →
   intitulé → montant → compte d'encaissement (si plusieurs)
4. **Validation** → écran de confirmation ou d'erreur
5. **Nouvelle transaction** : recharge automatiquement le statut TPE
