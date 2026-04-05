<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\User;

class Account extends Model
{
    protected string $table = 'accounts';

    /**
     * Types de comptes : label + droit au découvert.
     */
    public const TYPES = [
        'standard' => ['label' => 'Compte courant',       'overdraft' => true,  'cap' => false],
        'pro'      => ['label' => 'Compte professionnel', 'overdraft' => true,  'cap' => false],
        'joint'    => ['label' => 'Compte joint',         'overdraft' => true,  'cap' => false],
        'savings'  => ['label' => 'Compte épargne',       'overdraft' => false, 'cap' => true],
        'online'   => ['label' => 'Banque en ligne',      'overdraft' => false, 'cap' => false],
        'minor'    => ['label' => 'Compte mineur',        'overdraft' => false, 'cap' => false],
    ];

    /**
     * Types créables par un mineur via le formulaire standard.
     * Le type 'minor' est réservé à la modération uniquement.
     */
    public const MINOR_ALLOWED_TYPES = ['savings'];

    /**
     * Retourne les types de comptes créables via le formulaire standard.
     * Le type 'minor' est réservé exclusivement à la modération.
     */
    public static function getAllowedTypes(bool $isMinor): array
    {
        if ($isMinor) {
            return array_filter(
                self::TYPES,
                fn(string $key) => in_array($key, self::MINOR_ALLOWED_TYPES, true),
                ARRAY_FILTER_USE_KEY
            );
        }
        // Les adultes peuvent créer tous les types sauf 'minor' (modération uniquement)
        return array_filter(
            self::TYPES,
            fn(string $key) => $key !== 'minor',
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function typeAllowsOverdraft(string $type): bool
    {
        return self::TYPES[$type]['overdraft'] ?? true;
    }

    public static function typeHasCap(string $type): bool
    {
        return (bool) (self::TYPES[$type]['cap'] ?? false);
    }

    public function createAccount(int $userId, string $name, string $currency, float $overdraft = 0.0, string $type = 'standard', ?float $cap = null): int
    {
        if (!self::typeAllowsOverdraft($type)) {
            $overdraft = 0.0;
        }
        if (!self::typeHasCap($type)) {
            $cap = null;
        }
        return $this->create([
            'user_id'   => $userId,
            'name'      => $name,
            'currency'  => $currency,
            'overdraft' => $overdraft,
            'type'      => $type,
            'cap'       => $cap,
        ]);
    }

    public function getByUser(int $userId): array
    {
        return $this->findBy(['user_id' => (string) $userId]);
    }

    public function getBalance(int $accountId): float
    {
        $transactionModel = new Transaction();
        $transactions = $transactionModel->findBy(['account_id' => $accountId]);
        $balance = 0.0;
        foreach ($transactions as $t) {
            if (Transaction::isPending($t)) {
                continue;
            }
            if ($t['type'] === 'income') {
                $balance += (float) $t['amount'];
            } else {
                $balance -= (float) $t['amount'];
            }
        }
        return $balance;
    }

    public function getFutureBalance(int $accountId): float
    {
        $transactionModel = new Transaction();
        $transactions = $transactionModel->findBy(['account_id' => $accountId]);
        $balance = 0.0;
        foreach ($transactions as $t) {
            if ($t['type'] === 'income') {
                $balance += (float) $t['amount'];
            } else {
                $balance -= (float) $t['amount'];
            }
        }

        // Déduire les prélèvements planifiés non encore exécutés
        $directDebitModel = new DirectDebit();
        $upcomingDebits = $directDebitModel->findBy(
            ['to_account_id' => $accountId, 'status' => DirectDebit::STATUS_SCHEDULED]
        );
        foreach ($upcomingDebits as $d) {
            $balance -= (float) $d['amount'];
        }

        return $balance;
    }

    public function isOwner(int $accountId, int $userId): bool
    {
        $account = $this->find($accountId);
        return $account && (int) $account['user_id'] === $userId;
    }

    public function hasAccess(int $accountId, int $userId): bool
    {
        if ($this->isOwner($accountId, $userId)) {
            return true;
        }
        $accessModel = new AccountAccess();
        if ($accessModel->hasValidAccess($accountId, $userId)) {
            return true;
        }
        // Vérifier si l'utilisateur est responsable légal actif du propriétaire du compte
        $account = $this->find($accountId);
        if ($account) {
            $guardianshipModel = new Guardianship();
            if ($guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id'])) {
                return true;
            }
        }
        return false;
    }

    public function isFrozen(int $accountId): bool
    {
        $account = $this->find($accountId);
        return $account !== null && !empty($account['frozen']);
    }

    public function freezeAccount(int $accountId): bool
    {
        return $this->update($accountId, ['frozen' => true]);
    }

    public function unfreezeAccount(int $accountId): bool
    {
        return $this->update($accountId, ['frozen' => false]);
    }

    public function getAccessibleAccounts(int $userId): array
    {
        $ownAccounts = $this->getByUser($userId);

        $accessModel = new AccountAccess();
        $sharedAccesses = $accessModel->getValidAccessesForUser($userId);

        $sharedAccounts = [];
        foreach ($sharedAccesses as $access) {
            $account = $this->find((int) $access['account_id']);
            if ($account) {
                $account['_shared'] = true;
                $account['_access_type'] = $access['type'];
                $account['_access_expires'] = $access['expires_at'] ?? null;
                $sharedAccounts[] = $account;
            }
        }

        // Ajouter les comptes des mineurs dont l'utilisateur est responsable légal actif
        $guardianshipModel = new Guardianship();
        $minorLinks        = $guardianshipModel->getMinorsOf($userId);
        $userModel         = new User();
        foreach ($minorLinks as $link) {
            $minorUser = $userModel->find((int) $link['minor_user_id']);
            if (!$minorUser || !User::isMinorFromDate($minorUser['birth_date'] ?? null)) {
                continue; // Le mineur est devenu majeur : procuration expirée
            }
            $minorAccounts = $this->getByUser((int) $link['minor_user_id']);
            foreach ($minorAccounts as $account) {
                // Éviter les doublons (ex. partagé ET tuteur)
                $alreadyIncluded = false;
                foreach ($sharedAccounts as $sa) {
                    if ((int) $sa['id'] === (int) $account['id']) {
                        $alreadyIncluded = true;
                        break;
                    }
                }
                if (!$alreadyIncluded) {
                    $account['_shared']         = true;
                    $account['_access_type']    = 'guardian';
                    $account['_access_expires'] = null;
                    $account['_minor_username'] = $minorUser['username'];
                    $sharedAccounts[]           = $account;
                }
            }
        }

        return ['own' => $ownAccounts, 'shared' => $sharedAccounts];
    }

    /**
     * Recherche de comptes par nom de compte ou nom d'utilisateur (pour l'autocomplete).
     */
    public function searchByQuery(string $q, int $limit = 15): array
    {
        $term = '%' . $q . '%';
        $stmt = $this->getPdo()->prepare(
            'SELECT a.id, a.name, a.currency, a.type, u.username
             FROM accounts a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.name LIKE ? OR u.username LIKE ?
             ORDER BY a.name ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$term, $term]);
        return $stmt->fetchAll();
    }
}
