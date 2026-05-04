<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Models\DeferredDebit;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\LoanInstallment;
use App\Models\User;

class Account extends Model
{
    protected string $table = 'accounts';

    /**
     * Types de comptes : label + droit au découvert.
     */
    public const TYPES = [
        'standard' => ['label' => 'Compte courant',       'overdraft' => true,  'cap' => false, 'interest' => false],
        'pro'      => ['label' => 'Compte professionnel', 'overdraft' => true,  'cap' => false, 'interest' => false],
        'joint'    => ['label' => 'Compte joint',         'overdraft' => true,  'cap' => false, 'interest' => false],
        'savings'  => ['label' => 'Compte épargne',       'overdraft' => false, 'cap' => true,  'interest' => true],
        'online'   => ['label' => 'Banque en ligne',      'overdraft' => false, 'cap' => false, 'interest' => true],
        'minor'    => ['label' => 'Compte mineur',        'overdraft' => false, 'cap' => false, 'interest' => false],
    ];

    /** Retourne les types de comptes éligibles aux intérêts. */
    public static function getInterestEligibleTypes(): array
    {
        return array_keys(array_filter(
            self::TYPES,
            fn(array $def) => $def['interest'] ?? false
        ));
    }

    /** Indique si un type de compte peut recevoir des intérêts. */
    public static function typeHasInterest(string $type): bool
    {
        return (bool) (self::TYPES[$type]['interest'] ?? false);
    }

    /**
     * Types créables par un mineur via le formulaire standard.
     * Le type 'minor' est réservé à la modération uniquement.
     */
    public const MINOR_ALLOWED_TYPES = ['savings'];

    /**
     * Types créables par un professionnel vérifié.
     * Les professionnels ne peuvent créer que des comptes pro ou épargne.
     */
    public const PRO_ALLOWED_TYPES = ['pro', 'savings'];

    /**
     * Retourne les types de comptes créables via le formulaire standard.
     * - Mineur : épargne uniquement.
     * - Professionnel : pro + épargne uniquement.
     * - Adulte standard : tout sauf 'minor' et 'pro'.
     */
    public static function getAllowedTypes(bool $isMinor, bool $isProfessional = false): array
    {
        if ($isMinor) {
            return array_filter(
                self::TYPES,
                fn(string $key) => in_array($key, self::MINOR_ALLOWED_TYPES, true),
                ARRAY_FILTER_USE_KEY
            );
        }
        if ($isProfessional) {
            return array_filter(
                self::TYPES,
                fn(string $key) => in_array($key, self::PRO_ALLOWED_TYPES, true),
                ARRAY_FILTER_USE_KEY
            );
        }
        // Adultes non-pros : tous les types sauf 'minor' et 'pro'
        return array_filter(
            self::TYPES,
            fn(string $key) => $key !== 'minor' && $key !== 'pro',
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Indique si le compte est réservé exclusivement aux utilisateurs majeurs :
     * - compte courant (standard)
     * - compte professionnel (pro)
     * - compte joint avec découvert autorisé (overdraft > 0)
     * Les mineurs ne peuvent y être ajoutés qu'à la main par un modérateur.
     */
    public static function isAdultOnlyAccount(array $account): bool
    {
        $type = $account['type'] ?? '';
        if (in_array($type, ['pro', 'standard'], true)) {
            return true;
        }
        if ($type === 'joint' && (float) ($account['overdraft'] ?? 0) > 0.0) {
            return true;
        }
        return false;
    }

    public static function typeAllowsOverdraft(string $type): bool
    {
        return self::TYPES[$type]['overdraft'] ?? true;
    }

    public static function typeHasCap(string $type): bool
    {
        return (bool) (self::TYPES[$type]['cap'] ?? false);
    }

    /** Types de comptes éligibles au débit différé (comptes majeurs, hors épargne). */
    public static function typeAllowsDeferredDebit(string $type): bool
    {
        return in_array($type, ['standard', 'pro', 'joint', 'online'], true);
    }

    /**
     * Indique si une opération vient de faire franchir le seuil d'alerte à la baisse.
     * Retourne true uniquement si le solde était >= seuil avant et < seuil après l'opération.
     */
    public static function crossedAlertThreshold(array $account, float $balanceBefore, float $balanceAfter): bool
    {
        if (($account['balance_alert_threshold'] ?? null) === null) {
            return false;
        }
        $threshold = (float) $account['balance_alert_threshold'];
        return $balanceBefore >= $threshold && $balanceAfter < $threshold;
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

        // Ajouter les prélèvements planifiés où ce compte est émetteur (il sera crédité)
        $upcomingCredits = $directDebitModel->findBy(
            ['from_account_id' => $accountId, 'status' => DirectDebit::STATUS_SCHEDULED]
        );
        foreach ($upcomingCredits as $d) {
            $balance += (float) $d['amount'];
        }

        // Mandats actifs (compte débité ou émetteur) avec prochaine exécution dans le mois en cours
        $mandateModel = new Mandate();
        $monthStart   = date('Y-m-01 00:00:00');
        $monthEnd     = date('Y-m-t 23:59:59');
        foreach ($mandateModel->getUpcomingByAccount($accountId) as $m) {
            $next = $m['next_execution_at'] ?? null;
            if (!$next || $next < $monthStart || $next > $monthEnd) {
                continue;
            }
            if ((int) $m['recipient_account_id'] === $accountId) {
                $balance -= (float) $m['amount'];
            } elseif ((int) ($m['emitter_account_id'] ?? 0) === $accountId) {
                $balance += (float) $m['amount'];
            }
        }

        // Déduire les débits différés en attente
        $deferredDebitModel = new DeferredDebit();
        $balance -= $deferredDebitModel->getPendingTotalByAccount($accountId);

        // Déduire les échéances de crédit en attente
        $installmentModel      = new LoanInstallment();
        $upcomingInstallments  = $installmentModel->getUpcomingByAccount($accountId);
        foreach ($upcomingInstallments as $inst) {
            $balance -= (float) $inst['amount'];
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
        $account = $this->find($accountId);
        if (!$account) {
            return false;
        }
        if ((int) $account['user_id'] === $userId) {
            // Le mineur ne peut pas consulter un compte que son responsable légal a masqué
            return empty($account['hidden_from_owner']);
        }
        $accessModel = new AccountAccess();
        if ($accessModel->hasValidAccess($accountId, $userId)) {
            return true;
        }
        // Responsable légal actif : accès complet, même si le compte est masqué au mineur
        $guardianshipModel = new Guardianship();
        return $guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);
    }

    public function isFrozen(int $accountId): bool
    {
        $account = $this->find($accountId);
        return $account !== null && !empty($account['frozen']);
    }

    public function freezeAccount(int $accountId): bool
    {
        return $this->update($accountId, ['frozen' => 1]);
    }

    public function unfreezeAccount(int $accountId): bool
    {
        return $this->update($accountId, ['frozen' => 0]);
    }

    public function isDisabled(int $accountId): bool
    {
        $account = $this->find($accountId);
        return $account !== null && !empty($account['disabled_at']);
    }

    public function disableAccount(int $accountId): bool
    {
        return $this->update($accountId, ['disabled_at' => date('Y-m-d H:i:s')]);
    }

    public function enableAccount(int $accountId): bool
    {
        return $this->update($accountId, ['disabled_at' => null]);
    }

    /**
     * Retourne les comptes éligibles à la clôture définitive :
     * disabled_at IS NOT NULL ET disabled_at < premier jour du mois courant.
     */
    public function getEligibleForClosure(): array
    {
        $firstOfMonth = date('Y-m-01 00:00:00');
        $stmt = $this->getPdo()->prepare(
            'SELECT * FROM `accounts` WHERE `disabled_at` IS NOT NULL AND `disabled_at` < ?'
        );
        $stmt->execute([$firstOfMonth]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAccessibleAccounts(int $userId): array
    {
        // Comptes masqués par un responsable légal : invisibles pour le mineur propriétaire
        $ownAccounts = array_values(array_filter(
            $this->getByUser($userId),
            fn($a) => empty($a['hidden_from_owner'])
        ));

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
    public function searchByQuery(string $q, int $limit = 15, ?string $type = null): array
    {
        $term = '%' . $q . '%';
        $sql = 'SELECT a.id, a.name, a.currency, a.type, u.username
                FROM accounts a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE (a.name LIKE ? OR u.username LIKE ?)';
        $params = [$term, $term];

        if ($type !== null) {
            $sql .= ' AND a.type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY a.name ASC LIMIT ' . (int) $limit;
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
