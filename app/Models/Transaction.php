<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Transaction extends Model
{
    protected string $table = 'transactions';

    /** Catégories réservées aux opérations de modération (non accessibles aux utilisateurs) */
    public const MODERATION_CATEGORIES = [
        'Régularisation'              => '🔧',
        'Ajustement comptable'        => '⚖️',
        "Correction d'erreur"         => '✏️',
        'Pénalité bancaire'           => '⚠️',
        'Compensation exceptionnelle' => '💚',
        'Provision'                   => '📋',
        'Frais de gestion'            => '💼',
    ];

    /** Catégories spécifiques aux dépenses */
    public const EXPENSE_CATEGORIES = [
        'Alimentation'       => '🛒',
        'Transport'          => '🚗',
        'Logement'           => '🏠',
        'Santé'              => '💊',
        'Loisirs'            => '🎮',
        'Vêtements'          => '👕',
        'Éducation'          => '📚',
        'Factures'           => '📄',
        'Abonnements'        => '🔄',
        'Restaurants'        => '🍽️',
        'Voyages'            => '✈️',
        'Animaux'            => '🐾',
        'Cadeaux'            => '🎁',
        'Impôts & taxes'     => '🏛️',
        'Agios'              => '🏧',
        'Épargne'            => '🏦',
        'Autre'              => '📌',
    ];

    /** Catégories spécifiques aux entrées */
    public const INCOME_CATEGORIES = [
        'Salaire'            => '💰',
        'Freelance'          => '💻',
        'Investissement'     => '📈',
        'Remboursement'      => '🔙',
        'Allocations'        => '🏛️',
        'Vente'              => '🏷️',
        'Cadeaux'            => '🎁',
        'Intérêts'           => '🏦',
        'Loyer perçu'        => '🔑',
        'Autre'              => '📌',
    ];

    /** Toutes les catégories (rétro-compatibilité) */
    public const CATEGORIES = [
        'Alimentation',
        'Transport',
        'Logement',
        'Santé',
        'Loisirs',
        'Vêtements',
        'Éducation',
        'Épargne',
        'Salaire',
        'Freelance',
        'Investissement',
        'Cadeaux',
        'Factures',
        'Abonnements',
        'Restaurants',
        'Voyages',
        'Animaux',
        'Impôts & taxes',
        'Agios',
        'Remboursement',
        'Allocations',
        'Vente',
        'Intérêts',
        'Loyer perçu',
        'Autre',
    ];

    /**
     * Retourne les catégories (clé => emoji) pour un type donné.
     */
    public static function getCategoriesForType(string $type): array
    {
        return $type === 'income' ? self::INCOME_CATEGORIES : self::EXPENSE_CATEGORIES;
    }

    /**
     * Vérifie si une catégorie est valide pour un type donné.
     */
    public static function isValidCategory(string $category, string $type): bool
    {
        return array_key_exists($category, self::getCategoriesForType($type));
    }

    public function addTransaction(int $accountId, string $type, float $amount, string $category, string $comment = '', int $userId = 0, ?string $scheduledAt = null, ?int $cardId = null): int
    {
        $row = [
            'account_id'   => $accountId,
            'user_id'      => $userId,
            'type'         => $type,
            'amount'       => $amount,
            'category'     => $category,
            'comment'      => $comment,
            'scheduled_at' => $scheduledAt,
        ];
        if ($cardId !== null) {
            $row['card_id'] = $cardId;
        }
        return $this->create($row);
    }

    public static function isPending(array $transaction): bool
    {
        if (empty($transaction['scheduled_at'])) {
            return false;
        }
        return strtotime($transaction['scheduled_at']) > time();
    }

    /**
     * Indique si une transaction est issue d'une opération réservée à la
     * modération (TPE, annulation de virement, annulation de prélèvement,
     * annulation / remboursement de crédit, rejet de prélèvement…).
     * Ces transactions ne peuvent être ni éditées, ni supprimées manuellement.
     */
    public static function isModerationOnly(array $transaction): bool
    {
        // Les agios et les catégories de modération sont réservés à la modération
        $cat = $transaction['category'] ?? '';
        if ($cat === 'Agios' || array_key_exists($cat, self::MODERATION_CATEGORIES)) {
            return true;
        }

        $comment = (string) ($transaction['comment'] ?? '');
        return str_starts_with($comment, '[TPE')
            || str_starts_with($comment, 'Annulation virement')
            || str_starts_with($comment, 'Annulation paiement TPE')
            || str_starts_with($comment, 'Annulation crédit ')
            || str_starts_with($comment, 'Remboursement mensualité #')
            || str_starts_with($comment, 'Remboursement crédit #')
            || str_starts_with($comment, 'Rejet prélèvement mandat ');
    }

    /**
     * Bascule l'exclusion budgétaire d'une transaction.
     * Retourne le nouvel état (true = désormais exclue).
     */
    public function toggleBudgetExclusion(int $transactionId): bool
    {
        $stmt = $this->getPdo()->prepare(
            "UPDATE `{$this->table}`
             SET excluded_from_budget = 1 - excluded_from_budget
             WHERE id = ?"
        );
        $stmt->execute([$transactionId]);

        $row = $this->find($transactionId);
        return (bool) ($row['excluded_from_budget'] ?? false);
    }

    public function getByAccount(int $accountId, string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        return $this->findBy(['account_id' => (string) $accountId], $orderBy, $direction);
    }

    /**
     * Compte les transactions exécutées (non programmées futures) d'un compte.
     */
    public function countExecutedByAccount(int $accountId): int
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COUNT(*) FROM `{$this->table}`
             WHERE account_id = ?
               AND (scheduled_at IS NULL OR scheduled_at <= CURRENT_TIMESTAMP)"
        );
        $stmt->execute([$accountId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne une page de transactions exécutées triées par date décroissante.
     */
    public function getExecutedByAccountPaginated(int $accountId, int $limit, int $offset): array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}`
             WHERE account_id = ?
               AND (scheduled_at IS NULL OR scheduled_at <= CURRENT_TIMESTAMP)
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $accountId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,     \PDO::PARAM_INT);
        $stmt->bindValue(3, $offset,    \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotalIncome(int $accountId, bool $currentOnly = false): float
    {
        $transactions = $this->findBy(['account_id' => (string) $accountId]);
        $total = 0.0;
        foreach ($transactions as $t) {
            if ($t['type'] === 'income' && (!$currentOnly || !self::isPending($t))) {
                $total += (float) $t['amount'];
            }
        }
        return $total;
    }

    public function getTotalExpense(int $accountId, bool $currentOnly = false): float
    {
        $transactions = $this->findBy(['account_id' => (string) $accountId]);
        $total = 0.0;
        foreach ($transactions as $t) {
            if ($t['type'] === 'expense' && (!$currentOnly || !self::isPending($t))) {
                $total += (float) $t['amount'];
            }
        }
        return $total;
    }

    /**
     * Retourne le total des dépenses exécutées par catégorie pour une liste de comptes
     * et un mois donné (format 'YYYY-MM').
     * Résultat : [category => total_amount], trié par montant décroissant.
     *
     * @param  int[]  $accountIds
     */
    public function getMonthlyExpensesByCategory(array $accountIds, string $yearMonth): array
    {
        if (empty($accountIds)) {
            return [];
        }
        $ids = array_values(array_map('intval', $accountIds));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT category, SUM(amount) AS total
                FROM `{$this->table}`
                WHERE account_id IN ($ph)
                  AND type = 'expense'
                  AND (scheduled_at IS NULL OR scheduled_at <= CURRENT_TIMESTAMP)
                  AND DATE_FORMAT(created_at, '%Y-%m') = ?
                  AND excluded_from_budget = 0
                GROUP BY category
                ORDER BY total DESC";
        $params = array_merge($ids, [$yearMonth]);
        $stmt   = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        $result = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['category']] = (float) $row['total'];
        }
        return $result;
    }

    /**
     * Retourne le sous-ensemble des IDs donnés qui sont référencés comme debit_tx_id
     * ou credit_tx_id dans les tables transfers ou direct_debits.
     * Ces transactions ne doivent pas être supprimables individuellement.
     *
     * @param  int[] $txIds
     * @return int[]
     */
    public function getProtectedIds(array $txIds): array
    {
        if (empty($txIds)) {
            return [];
        }
        $ids = array_values(array_map('intval', $txIds));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT DISTINCT linked_id FROM (
                    SELECT debit_tx_id  AS linked_id FROM transfers          WHERE debit_tx_id  > 0            AND debit_tx_id  IN ($ph)
                    UNION ALL
                    SELECT credit_tx_id AS linked_id FROM transfers          WHERE credit_tx_id > 0            AND credit_tx_id IN ($ph)
                    UNION ALL
                    SELECT debit_tx_id  AS linked_id FROM direct_debits      WHERE debit_tx_id  IS NOT NULL    AND debit_tx_id  IN ($ph)
                    UNION ALL
                    SELECT credit_tx_id AS linked_id FROM direct_debits      WHERE credit_tx_id IS NOT NULL    AND credit_tx_id IN ($ph)
                    UNION ALL
                    SELECT credit_tx_id AS linked_id FROM loans              WHERE credit_tx_id IS NOT NULL    AND credit_tx_id IN ($ph)
                    UNION ALL
                    SELECT cancel_tx_id AS linked_id FROM loans              WHERE cancel_tx_id IS NOT NULL    AND cancel_tx_id IN ($ph)
                    UNION ALL
                    SELECT transaction_id AS linked_id FROM loan_installments WHERE transaction_id IS NOT NULL AND transaction_id IN ($ph)
                    UNION ALL
                    SELECT refund_tx_id AS linked_id FROM loan_installments  WHERE refund_tx_id IS NOT NULL    AND refund_tx_id  IN ($ph)
                ) AS linked_sub
                WHERE linked_id IS NOT NULL";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute(array_merge($ids, $ids, $ids, $ids, $ids, $ids, $ids, $ids));
        return array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'linked_id'));
    }

    /**
     * Retourne les transactions exécutées d'un compte dans un intervalle de dates (bornes incluses).
     * Les transactions programmées futures sont exclues.
     */
    public function getByAccountBetween(int $accountId, string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT * FROM transactions
                WHERE account_id = :account_id
                  AND (scheduled_at IS NULL OR scheduled_at <= CURRENT_TIMESTAMP)
                  AND created_at >= :date_from
                  AND created_at <= :date_to
                ORDER BY created_at ASC";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([
            ':account_id' => $accountId,
            ':date_from'  => $dateFrom . ' 00:00:00',
            ':date_to'    => $dateTo   . ' 23:59:59',
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Calcule le solde du compte juste avant minuit d'une date donnée (solde d'ouverture).
     */
    public function getBalanceBeforeDate(int $accountId, string $date): float
    {
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END), 0)
                  - COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0)
                FROM transactions
                WHERE account_id = :account_id
                  AND (scheduled_at IS NULL OR scheduled_at <= CURRENT_TIMESTAMP)
                  AND created_at < :date";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([
            ':account_id' => $accountId,
            ':date'       => $date . ' 00:00:00',
        ]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Reconstruit l'historique de solde d'un compte (toutes les transactions exécutées,
     * ordre chronologique ASC) et identifie les épisodes de dépassement de découvert.
     *
     * Un épisode commence dès que le solde tombe sous $overdraftLimit (= -$overdraft, ou 0 si
     * le type de compte n'autorise pas le découvert) et se termine quand il repasse au-dessus.
     *
     * Retourne un tableau de la forme :
     * [
     *   'transactions' => [...],   // toutes les tx exécutées (avec champ 'running_balance')
     *   'episodes'     => [        // périodes en dépassement
     *     [
     *       'start_tx'       => [...],  // tx qui a déclenché le dépassement
     *       'end_tx'         => [...],  // tx qui a soldé le dépassement (null si toujours en cours)
     *       'started_at'     => '...',
     *       'ended_at'       => '...' | null,
     *       'max_depth'      => float,  // dépassement max (valeur absolue au-delà de la limite)
     *       'snapshot'       => [...],  // toutes les tx pendant l'épisode (avec running_balance)
     *     ],
     *     ...
     *   ],
     * ]
     *
     * @param float $overdraftLimit Seuil en dessous duquel on considère un dépassement (négatif).
     *                              Ex : -500 pour un découvert autorisé de 500, ou 0 sinon.
     */
    public function buildOverdraftHistory(int $accountId, float $overdraftLimit = 0.0): array
    {
        // Toutes les transactions exécutées, ordre chronologique
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}`
             WHERE account_id = ?
               AND (scheduled_at IS NULL OR scheduled_at <= CURRENT_TIMESTAMP)
             ORDER BY created_at ASC, id ASC"
        );
        $stmt->execute([$accountId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $runningBalance = 0.0;
        $transactions   = [];
        foreach ($rows as $row) {
            if ($row['type'] === 'income') {
                $runningBalance += (float) $row['amount'];
            } else {
                $runningBalance -= (float) $row['amount'];
            }
            $row['running_balance'] = $runningBalance;
            $transactions[] = $row;
        }

        // Identification des épisodes
        $episodes        = [];
        $inEpisode       = false;
        $currentEpisode  = null;

        foreach ($transactions as $tx) {
            $bal = (float) $tx['running_balance'];

            if (!$inEpisode) {
                if ($bal < $overdraftLimit) {
                    // Début d'un épisode
                    $inEpisode      = true;
                    $currentEpisode = [
                        'start_tx'   => $tx,
                        'end_tx'     => null,
                        'started_at' => $tx['created_at'],
                        'ended_at'   => null,
                        'max_depth'  => $overdraftLimit - $bal,
                        'snapshot'   => [$tx],
                    ];
                }
            } else {
                $depth = $overdraftLimit - $bal;
                if ($depth > $currentEpisode['max_depth']) {
                    $currentEpisode['max_depth'] = $depth;
                }
                $currentEpisode['snapshot'][] = $tx;

                if ($bal >= $overdraftLimit) {
                    // Fin de l'épisode
                    $currentEpisode['end_tx']   = $tx;
                    $currentEpisode['ended_at'] = $tx['created_at'];
                    $episodes[]     = $currentEpisode;
                    $inEpisode      = false;
                    $currentEpisode = null;
                }
            }
        }

        // Épisode toujours en cours
        if ($inEpisode && $currentEpisode !== null) {
            $episodes[] = $currentEpisode;
        }

        return [
            'transactions' => $transactions,
            'episodes'     => $episodes,
        ];
    }
}
