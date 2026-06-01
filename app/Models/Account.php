<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Models\DeferredDebit;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Mandate;
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
     * Indique si un type de compte peut être associé à une carte bancaire.
     * Les comptes d'épargne sont exclus.
     */
    public static function typeAllowsCard(string $type): bool
    {
        return $type !== 'savings';
    }

    // ── Suspension TPE d'un compte professionnel ─────────────────────────────

    /**
     * Vérifie si le compte est suspendu du TPE.
     * Accepte un tableau `account` (résultat de `find()`). Tient compte
     * de la réactivation automatique par `pos_suspended_until`.
     */
    public static function isPosSuspended(array $account): bool
    {
        if (empty($account['pos_suspended_at'])) {
            return false;
        }
        // Réactivation automatique transparente
        if (!empty($account['pos_suspended_until'])
            && strtotime((string) $account['pos_suspended_until']) <= time()
        ) {
            return false;
        }
        return true;
    }

    /**
     * Suspend l'accès au TPE pour ce compte.
     *
     * @param int         $accountId
     * @param int         $moderatorId
     * @param string      $reason       Motif obligatoire (≤ 500 car.).
     * @param string|null $until        Datetime SQL ou null (durée indéterminée).
     */
    public function suspendPos(int $accountId, int $moderatorId, string $reason, ?string $until = null): bool
    {
        $pdo  = \App\Core\Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE accounts
                SET pos_suspended_at    = ?,
                    pos_suspended_until = ?,
                    pos_suspended_by    = ?,
                    pos_suspend_reason  = ?
              WHERE id = ?'
        );
        return (bool) $stmt->execute([
            date('Y-m-d H:i:s'),
            $until,
            $moderatorId,
            mb_substr($reason, 0, 500),
            $accountId,
        ]);
    }

    /** Réactive l'accès au TPE pour ce compte. */
    public function resumePos(int $accountId, int $moderatorId): bool
    {
        $pdo  = \App\Core\Database::getInstance();
        $stmt = $pdo->prepare(
            "UPDATE accounts
                SET pos_suspended_at    = NULL,
                    pos_suspended_until = NULL,
                    pos_suspended_by    = ?,
                    pos_suspend_reason  = ''
              WHERE id = ?"
        );
        return (bool) $stmt->execute([$moderatorId, $accountId]);
    }

    /**
     * Retourne les comptes professionnels dont le TPE est actuellement suspendu
     * (hors expirations dépassées), enrichis des infos utilisateur.
     */
    public function getPosSuspendedAccounts(): array
    {
        $pdo  = \App\Core\Database::getInstance();
        $stmt = $pdo->query(
            "SELECT a.*, u.username, u.email
               FROM accounts a
               JOIN users u ON u.id = a.user_id
              WHERE a.type = 'pro'
                AND a.pos_suspended_at IS NOT NULL
                AND (a.pos_suspended_until IS NULL
                     OR a.pos_suspended_until > NOW())
              ORDER BY a.pos_suspended_at DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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

    public function createAccount(int $userId, string $name, string $currency, float $overdraft = 0.0, string $type = 'standard', ?float $cap = null, bool $internal = false): int
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
            'internal'  => $internal ? 1 : 0,
        ]);
    }

    /**
     * Indique si un compte est un compte interne de modération (test).
     * Les comptes internes ne peuvent pas être partagés aux utilisateurs normaux.
     */
    public static function isInternal(array $account): bool
    {
        return !empty($account['internal']);
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

    /**
     * Calcule en batch les soldes courant et "à venir" de plusieurs comptes.
     *
     * Effectue un nombre constant de requêtes (5) quelle que soit la cardinalité
     * de $accountIds, contrairement à des appels individuels à getBalance() /
     * getFutureBalance() qui produisent un comportement N+1 sur le dashboard.
     *
     * @param int[] $accountIds
     * @return array<int, array{balance: float, future_balance: float}>
     */
    public function getBalancesBatch(array $accountIds): array
    {
        $result = [];
        foreach ($accountIds as $id) {
            $result[(int) $id] = ['balance' => 0.0, 'future_balance' => 0.0];
        }
        if (empty($result)) {
            return $result;
        }

        $ids          = array_keys($result);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo          = $this->getPdo();
        $now          = date('Y-m-d H:i:s');
        $monthStart   = date('Y-m-01 00:00:00');
        $monthEnd     = date('Y-m-t 23:59:59');

        // 1. Transactions : solde courant (non planifiées) + base du solde à venir
        $stmt = $pdo->prepare(
            "SELECT account_id,
                    SUM(CASE WHEN (scheduled_at IS NULL OR scheduled_at <= ?)
                             THEN CASE WHEN type = 'income' THEN amount ELSE -amount END
                             ELSE 0 END) AS current_balance,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) AS total_balance
               FROM transactions
              WHERE account_id IN ($placeholders)
              GROUP BY account_id"
        );
        $stmt->execute(array_merge([$now], $ids));
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $aid = (int) $row['account_id'];
            $result[$aid]['balance']        = (float) $row['current_balance'];
            $result[$aid]['future_balance'] = (float) $row['total_balance'];
        }

        // 2. Prélèvements (direct debits) planifiés : compte cible débité
        $stmt = $pdo->prepare(
            "SELECT to_account_id AS aid, SUM(amount) AS total
               FROM direct_debits
              WHERE status = ? AND to_account_id IN ($placeholders)
              GROUP BY to_account_id"
        );
        $stmt->execute(array_merge([DirectDebit::STATUS_SCHEDULED], $ids));
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['aid']]['future_balance'] -= (float) $row['total'];
        }

        // 3. Prélèvements planifiés : compte émetteur crédité
        $stmt = $pdo->prepare(
            "SELECT from_account_id AS aid, SUM(amount) AS total
               FROM direct_debits
              WHERE status = ? AND from_account_id IN ($placeholders)
              GROUP BY from_account_id"
        );
        $stmt->execute(array_merge([DirectDebit::STATUS_SCHEDULED], $ids));
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['aid']]['future_balance'] += (float) $row['total'];
        }

        // 4. Mandats actifs avec prochaine exécution dans le mois courant
        $stmt = $pdo->prepare(
            "SELECT emitter_account_id, recipient_account_id, amount
               FROM mandates
              WHERE status = ?
                AND next_execution_at IS NOT NULL
                AND next_execution_at BETWEEN ? AND ?
                AND (emitter_account_id IN ($placeholders) OR recipient_account_id IN ($placeholders))"
        );
        $stmt->execute(array_merge([Mandate::STATUS_ACTIVE, $monthStart, $monthEnd], $ids, $ids));
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $amount    = (float) $row['amount'];
            $recipient = (int) $row['recipient_account_id'];
            $emitter   = (int) ($row['emitter_account_id'] ?? 0);
            if (isset($result[$recipient])) {
                $result[$recipient]['future_balance'] -= $amount;
            }
            if ($emitter !== 0 && isset($result[$emitter])) {
                $result[$emitter]['future_balance'] += $amount;
            }
        }

        // 5. Débits différés en attente
        $stmt = $pdo->prepare(
            "SELECT account_id, SUM(amount) AS total
               FROM deferred_debits
              WHERE status = ? AND account_id IN ($placeholders)
              GROUP BY account_id"
        );
        $stmt->execute(array_merge([DeferredDebit::STATUS_PENDING], $ids));
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['account_id']]['future_balance'] -= (float) $row['total'];
        }

        // 6. Échéances de crédit à venir (crédits actifs / en cours d'approbation)
        $stmt = $pdo->prepare(
            "SELECT l.account_id AS aid, SUM(li.amount) AS total
               FROM loan_installments li
               JOIN loans l ON l.id = li.loan_id
              WHERE li.status = ?
                AND l.status IN (?, ?)
                AND l.account_id IN ($placeholders)
              GROUP BY l.account_id"
        );
        $stmt->execute(array_merge(
            [LoanInstallment::STATUS_PENDING, Loan::STATUS_PENDING, Loan::STATUS_ACTIVE],
            $ids
        ));
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['aid']]['future_balance'] -= (float) $row['total'];
        }

        return $result;
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

    public function freezeAccount(int $accountId, ?string $reason = null, ?string $frozenUntil = null, ?int $frozenBy = null): bool
    {
        return $this->update($accountId, [
            'frozen'        => 1,
            'frozen_reason' => $reason,
            'frozen_until'  => $frozenUntil,
            'frozen_by'     => $frozenBy,
        ]);
    }

    public function unfreezeAccount(int $accountId): bool
    {
        return $this->update($accountId, [
            'frozen'        => 0,
            'frozen_reason' => null,
            'frozen_until'  => null,
            'frozen_by'     => null,
        ]);
    }

    /**
     * Retourne les comptes dont le gel temporaire a expiré (frozen_until passé).
     */
    public function getAccountsToAutoUnfreeze(): array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `accounts` WHERE frozen = 1 AND frozen_until IS NOT NULL AND frozen_until <= NOW()"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
     *
     * Si $force = true, retourne tous les comptes avec disabled_at IS NOT NULL,
     * sans contrainte de date (suppression forcée avant la fin du mois).
     */
    public function getEligibleForClosure(bool $force = false): array
    {
        if ($force) {
            $stmt = $this->getPdo()->query(
                'SELECT * FROM `accounts` WHERE `disabled_at` IS NOT NULL'
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $firstOfMonth = date('Y-m-01 00:00:00');
        $stmt = $this->getPdo()->prepare(
            'SELECT * FROM `accounts` WHERE `disabled_at` IS NOT NULL AND `disabled_at` < ?'
        );
        $stmt->execute([$firstOfMonth]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Compte les comptes actuellement désactivés (en attente de clôture définitive).
     */
    public function countDisabled(): int
    {
        $stmt = $this->getPdo()->query(
            'SELECT COUNT(*) FROM `accounts` WHERE `disabled_at` IS NOT NULL'
        );
        return (int) $stmt->fetchColumn();
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
        $term   = '%' . $q . '%';
        $sql    = 'SELECT a.id, a.name, a.currency, a.type,
                          u.username, u.email, u.company_name, u.siret
                   FROM accounts a
                   LEFT JOIN users u ON u.id = a.user_id
                   WHERE (
                       a.name         LIKE ?
                    OR u.username     LIKE ?
                    OR u.email        LIKE ?
                    OR u.company_name LIKE ?
                    OR u.siret        LIKE ?
                   )';
        $params = [$term, $term, $term, $term, $term];

        if ($type !== null) {
            if ($type === 'pro') {
                // Inclut les comptes de type pro ET tous les comptes
                // appartenant à un utilisateur professionnel vérifié.
                $sql .= ' AND (a.type = ? OR u.is_professional = 1)';
            } else {
                $sql .= ' AND a.type = ?';
            }
            $params[] = $type;
        }

        $sql .= ' ORDER BY a.name ASC LIMIT ' . (int) $limit;
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
