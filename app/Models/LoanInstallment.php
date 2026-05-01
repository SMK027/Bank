<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Models\Loan;

/**
 * Échéance d'un crédit.
 *
 * Chaque ligne représente une mensualité planifiée par la modération.
 * À la date d'échéance, le compte est débité du montant de la mensualité.
 */
class LoanInstallment extends Model
{
    protected string $table      = 'loan_installments';
    protected bool   $hasUpdatedAt = false;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'En attente',
        self::STATUS_PAID      => 'Payée',
        self::STATUS_FAILED    => 'Échouée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    public const STATUS_BADGE = [
        self::STATUS_PENDING   => 'badge-warning',
        self::STATUS_PAID      => 'badge-success',
        self::STATUS_FAILED    => 'badge-danger',
        self::STATUS_CANCELLED => 'badge-secondary',
    ];

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Retourne toutes les échéances d'un crédit, ordonnées par date.
     */
    public function getByLoan(int $loanId): array
    {
        return $this->findBy(['loan_id' => $loanId], 'due_date', 'ASC');
    }

    /**
     * Retourne les échéances en attente dont la date est échue.
     */
    public function getDue(): array
    {
        $today = date('Y-m-d');
        $stmt  = $this->getPdo()->prepare(
            'SELECT li.*, l.account_id, l.user_id, l.amount AS loan_amount,
                    l.amount_repaid, a.currency, a.name AS account_name
             FROM `loan_installments` li
             JOIN `loans`    l ON l.id = li.loan_id
             JOIN `accounts` a ON a.id = l.account_id
             WHERE li.status = ?
               AND li.due_date <= ?
               AND l.status = ?'
        );
        $stmt->execute([self::STATUS_PENDING, $today, Loan::STATUS_ACTIVE]);
        return $stmt->fetchAll();
    }

    /**
     * Retourne les échéances à venir (pending) pour un compte donné, triées par date.
     */
    public function getUpcomingByAccount(int $accountId): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT li.*, l.loan_type, l.annual_rate
             FROM `loan_installments` li
             JOIN `loans` l ON l.id = li.loan_id
             WHERE li.status = ?
               AND l.account_id = ?
               AND l.status IN (?, ?)
             ORDER BY li.due_date ASC'
        );
        $stmt->execute([
            self::STATUS_PENDING,
            $accountId,
            Loan::STATUS_PENDING,
            Loan::STATUS_ACTIVE,
        ]);
        return $stmt->fetchAll();
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    /**
     * Ajoute une échéance à un crédit.
     */
    public function addInstallment(int $loanId, string $dueDate, float $amount): int
    {
        return $this->create([
            'loan_id'  => $loanId,
            'due_date' => $dueDate,
            'amount'   => $amount,
            'status'   => self::STATUS_PENDING,
        ]);
    }

    /**
     * Marque une échéance comme payée.
     */
    public function markPaid(int $id, int $transactionId): bool
    {
        return $this->update($id, [
            'status'         => self::STATUS_PAID,
            'paid_at'        => date('Y-m-d H:i:s'),
            'transaction_id' => $transactionId,
        ]);
    }

    /**
     * Marque une échéance comme échouée.
     */
    public function markFailed(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_FAILED]);
    }

    /**
     * Annule une échéance.
     */
    public function cancel(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_CANCELLED]);
    }
}
