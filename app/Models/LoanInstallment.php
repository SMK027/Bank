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
    public const STATUS_REFUNDED  = 'refunded';

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'En attente',
        self::STATUS_PAID      => 'Payée',
        self::STATUS_FAILED    => 'Échouée',
        self::STATUS_CANCELLED => 'Annulée',
        self::STATUS_REFUNDED  => 'Remboursée',
    ];

    public const STATUS_BADGE = [
        self::STATUS_PENDING   => 'badge-warning',
        self::STATUS_PAID      => 'badge-success',
        self::STATUS_FAILED    => 'badge-danger',
        self::STATUS_CANCELLED => 'badge-secondary',
        self::STATUS_REFUNDED  => 'badge-info',
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
     * Ajoute une échéance à un crédit avec décomposition capital/intérêts.
     *
     * @param float $principal  Part capital de la mensualité
     * @param float $interest   Part intérêts (calculée sur le capital restant)
     */
    public function addInstallment(int $loanId, string $dueDate, float $principal, float $interest): int
    {
        return $this->create([
            'loan_id'   => $loanId,
            'due_date'  => $dueDate,
            'amount'    => round($principal + $interest, 2),
            'principal' => $principal,
            'interest'  => $interest,
            'status'    => self::STATUS_PENDING,
        ]);
    }

    /**
     * Recalcule les intérêts de toutes les mensualités en attente suite à un changement de taux.
     *
     * Parcourt les mensualités dans l'ordre chronologique. Pour chacune, l'intérêt
     * est recalculé sur le capital restant (encours progressivement réduit par chaque principal).
     *
     * @param int   $loanId              ID du crédit
     * @param float $annualRate          Nouveau taux annuel (%)
     * @param float $outstandingPrincipal Capital restant dû (loan.amount − principal des mensualités payées)
     * @return int  Nombre de mensualités recalculées
     */
    public function recalculateInterestForPending(int $loanId, float $annualRate): int
    {
        $pending = $this->findBy(['loan_id' => $loanId, 'status' => self::STATUS_PENDING], 'due_date', 'ASC');
        $rate    = $annualRate / 100.0;
        $count   = 0;

        foreach ($pending as $inst) {
            $principal = (float) $inst['principal'];
            $interest  = round($principal * $rate, 2);
            $amount    = round($principal + $interest, 2);
            $this->update((int) $inst['id'], [
                'interest' => $interest,
                'amount'   => $amount,
            ]);
            $count++;
        }

        return $count;
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

    /**
     * Marque une échéance comme remboursée par la modération.
     */
    public function markRefunded(int $id, int $refundTxId): bool
    {
        return $this->update($id, [
            'status'       => self::STATUS_REFUNDED,
            'refund_tx_id' => $refundTxId,
        ]);
    }

    /**
     * Retourne les échéances payées d'un crédit.
     */
    public function getPaidByLoan(int $loanId): array
    {
        return $this->findBy(['loan_id' => $loanId, 'status' => self::STATUS_PAID], 'due_date', 'ASC');
    }

    /**
     * Retourne les échéances échouées d'un crédit.
     */
    public function getFailedByLoan(int $loanId): array
    {
        return $this->findBy(['loan_id' => $loanId, 'status' => self::STATUS_FAILED], 'due_date', 'ASC');
    }

    /**
     * Replanifie une mensualité échouée : reporte la date, remet le statut à pending,
     * et ajoute les pénalités de retard éventuelles (qui s'ajoutent au montant total).
     *
     * @param int    $id       ID de l'échéance (doit être en statut 'failed')
     * @param string $newDate  Nouvelle date d'échéance (Y-m-d)
     * @param float  $penalty  Montant de la pénalité (>= 0)
     * @return bool
     */
    public function reschedule(int $id, string $newDate, float $penalty = 0.0): bool
    {
        $inst = $this->find($id);
        if (!$inst || $inst['status'] !== self::STATUS_FAILED) {
            return false;
        }

        $penalty     = max(0.0, round($penalty, 2));
        $newAmount   = round((float) $inst['principal'] + (float) $inst['interest'] + $penalty, 2);

        return $this->update($id, [
            'due_date' => $newDate,
            'penalty'  => $penalty,
            'amount'   => $newAmount,
            'status'   => self::STATUS_PENDING,
        ]);
    }
}
