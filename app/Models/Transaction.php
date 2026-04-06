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
}
