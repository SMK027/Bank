<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Transaction extends Model
{
    protected string $table = 'transactions';

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
        'Autre',
    ];

    public function addTransaction(int $accountId, string $type, float $amount, string $category, string $comment = '', int $userId = 0, ?string $scheduledAt = null): int
    {
        return $this->create([
            'account_id'   => $accountId,
            'user_id'      => $userId,
            'type'         => $type,
            'amount'       => $amount,
            'category'     => $category,
            'comment'      => $comment,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public static function isPending(array $transaction): bool
    {
        if (empty($transaction['scheduled_at'])) {
            return false;
        }
        return strtotime($transaction['scheduled_at']) > time();
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
                    SELECT debit_tx_id  AS linked_id FROM transfers     WHERE debit_tx_id  > 0 AND debit_tx_id  IN ($ph)
                    UNION ALL
                    SELECT credit_tx_id AS linked_id FROM transfers     WHERE credit_tx_id > 0 AND credit_tx_id IN ($ph)
                    UNION ALL
                    SELECT debit_tx_id  AS linked_id FROM direct_debits WHERE debit_tx_id  IS NOT NULL AND debit_tx_id  IN ($ph)
                    UNION ALL
                    SELECT credit_tx_id AS linked_id FROM direct_debits WHERE credit_tx_id IS NOT NULL AND credit_tx_id IN ($ph)
                ) AS linked_sub
                WHERE linked_id IS NOT NULL";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute(array_merge($ids, $ids, $ids, $ids));
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
}
