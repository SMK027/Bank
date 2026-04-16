<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class DeferredDebit extends Model
{
    protected string $table = 'deferred_debits';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_EXECUTED  = 'executed';
    public const STATUS_CANCELLED = 'cancelled';

    public function createDeferredDebit(
        int $accountId,
        int $userId,
        float $amount,
        string $category,
        string $comment,
        string $operationDate,
        string $periodEndDate
    ): int {
        return $this->create([
            'account_id'      => $accountId,
            'user_id'         => $userId,
            'amount'          => $amount,
            'category'        => $category,
            'comment'         => $comment,
            'operation_date'  => $operationDate,
            'period_end_date' => $periodEndDate,
            'status'          => self::STATUS_PENDING,
        ]);
    }

    /**
     * Opérations en attente pour un compte, triées par fin de période puis date d'opération.
     */
    public function getPendingByAccount(int $accountId): array
    {
        return $this->findBy(
            ['account_id' => $accountId, 'status' => self::STATUS_PENDING],
            'operation_date',
            'ASC'
        );
    }

    /**
     * Total des montants en attente pour un compte (utile pour le solde à venir).
     */
    public function getPendingTotalByAccount(int $accountId): float
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM `{$this->table}`
             WHERE account_id = :account_id AND status = :status"
        );
        $stmt->execute(['account_id' => $accountId, 'status' => self::STATUS_PENDING]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Toutes les opérations en attente dont la période est échue (à exécuter par le cron).
     */
    public function getDue(): array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}`
             WHERE status = :status AND period_end_date <= CURDATE()
             ORDER BY period_end_date ASC, operation_date ASC"
        );
        $stmt->execute(['status' => self::STATUS_PENDING]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markExecuted(int $id, int $transactionId): bool
    {
        return $this->update($id, [
            'status'         => self::STATUS_EXECUTED,
            'transaction_id' => $transactionId,
            'executed_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function cancel(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_CANCELLED]);
    }
}
