<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Crédit bancaire octroyé par la modération.
 *
 * Cycle de vie :
 *   pending_acceptance → (user accept) → active → (fully repaid) → closed
 *   pending_acceptance → (user reject) → rejected
 */
class Loan extends Model
{
    protected string $table      = 'loans';
    protected bool   $hasCreatedAt = false;
    protected bool   $hasUpdatedAt = false;

    public const STATUS_PENDING   = 'pending_acceptance';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'En attente d\'acceptation',
        self::STATUS_ACTIVE    => 'Actif',
        self::STATUS_REJECTED  => 'Refusé',
        self::STATUS_CLOSED    => 'Soldé',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    public const STATUS_BADGE = [
        self::STATUS_PENDING   => 'badge-warning',
        self::STATUS_ACTIVE    => 'badge-success',
        self::STATUS_REJECTED  => 'badge-danger',
        self::STATUS_CLOSED    => 'badge-secondary',
        self::STATUS_CANCELLED => 'badge-danger',
    ];

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Retourne tous les crédits avec infos compte + propriétaire, pour la modération.
     */
    public function getAllEnriched(): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT l.*,
                    a.name        AS account_name,
                    a.type        AS account_type,
                    a.currency    AS account_currency,
                    u.username    AS owner_username,
                    u.email       AS owner_email,
                    m.username    AS granted_by_username
             FROM `loans` l
             JOIN `accounts` a ON a.id = l.account_id
             JOIN `users`    u ON u.id = l.user_id
             JOIN `users`    m ON m.id = l.granted_by
             ORDER BY l.granted_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retourne un crédit enrichi par son ID.
     */
    public function getEnriched(int $id): ?array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT l.*,
                    a.name        AS account_name,
                    a.type        AS account_type,
                    a.currency    AS account_currency,
                    u.username    AS owner_username,
                    u.email       AS owner_email,
                    m.username    AS granted_by_username
             FROM `loans` l
             JOIN `accounts` a ON a.id = l.account_id
             JOIN `users`    u ON u.id = l.user_id
             JOIN `users`    m ON m.id = l.granted_by
             WHERE l.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Retourne les crédits actifs/en attente d'un utilisateur, enrichis.
     */
    public function getForUser(int $userId): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT l.*,
                    a.name     AS account_name,
                    a.currency AS account_currency,
                    m.username AS granted_by_username
             FROM `loans` l
             JOIN `accounts` a ON a.id = l.account_id
             JOIN `users`    m ON m.id = l.granted_by
             WHERE l.user_id = ?
             ORDER BY l.granted_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Retourne les crédits en attente d'acceptation d'un utilisateur.
     */
    public function getPendingForUser(int $userId): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT l.*,
                    a.name     AS account_name,
                    a.currency AS account_currency,
                    m.username AS granted_by_username
             FROM `loans` l
             JOIN `accounts` a ON a.id = l.account_id
             JOIN `users`    m ON m.id = l.granted_by
             WHERE l.user_id = ? AND l.status = ?
             ORDER BY l.granted_at DESC'
        );
        $stmt->execute([$userId, self::STATUS_PENDING]);
        return $stmt->fetchAll();
    }

    // ── Actions de cycle de vie ──────────────────────────────────────────────

    /**
     * Octroie un nouveau crédit (status = pending_acceptance).
     */
    public function grant(
        int    $accountId,
        int    $userId,
        string $loanType,
        float  $amount,
        float  $annualRate,
        int    $grantedBy,
        ?string $notes = null,
        bool   $disburseFunds = true
    ): int {
        return $this->create([
            'account_id'    => $accountId,
            'user_id'       => $userId,
            'loan_type'     => $loanType,
            'amount'        => $amount,
            'annual_rate'   => $annualRate,
            'amount_repaid' => 0,
            'status'        => self::STATUS_PENDING,
            'granted_by'    => $grantedBy,
            'notes'         => $notes,
            'disburse_funds'=> $disburseFunds ? 1 : 0,
            'granted_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * L'utilisateur accepte le crédit.
     * $creditTxId est null si disburse_funds = false.
     */
    public function accept(int $id, ?int $creditTxId): bool
    {
        return $this->update($id, [
            'status'       => self::STATUS_ACTIVE,
            'accepted_at'  => date('Y-m-d H:i:s'),
            'credit_tx_id' => $creditTxId,
        ]);
    }

    /**
     * L'utilisateur refuse le crédit — la ligne est supprimée (CASCADE sur loan_installments).
     */
    public function reject(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Passe le crédit en "soldé".
     */
    public function close(int $id): bool
    {
        return $this->update($id, [
            'status'    => self::STATUS_CLOSED,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Enregistre un remboursement partiel (incrémente amount_repaid).
     * Retourne true si le crédit est désormais soldé.
     *
     * La comparaison se fait contre le total effectif (mensualités actives
     * hors annulées et remboursées) et non contre le montant nominal du crédit,
     * afin que les annulations de mensualités soient correctement prises en compte.
     */
    public function recordRepayment(int $id, float $amount): bool
    {
        $loan = $this->find($id);
        if (!$loan) {
            return false;
        }

        $newRepaid     = round((float) $loan['amount_repaid'] + $amount, 2);
        $effectiveTotal = $this->getTotalScheduledInstallments($id);

        $this->update($id, ['amount_repaid' => $newRepaid]);

        if ($effectiveTotal > 0 && $newRepaid >= $effectiveTotal) {
            $this->close($id);
            return true; // soldé
        }
        return false;
    }

    /**
     * Met à jour le taux annuel d'un crédit actif.
     */
    public function updateRate(int $id, float $annualRate): bool
    {
        return $this->update($id, ['annual_rate' => $annualRate]);
    }

    /**
     * Décrémente le montant remboursé (suite au remboursement d'une mensualité).
     */
    public function decrementRepaid(int $id, float $amount): void
    {
        $loan = $this->find($id);
        if (!$loan) {
            return;
        }
        $newRepaid = max(0.0, round((float) $loan['amount_repaid'] - $amount, 2));
        $this->update($id, ['amount_repaid' => $newRepaid]);
    }

    /**
     * Annule un crédit (modération).
     *
     * @param int      $id          Identifiant du crédit.
     * @param int|null $cancelTxId  ID de la transaction de débit de récupération des fonds (si applicable).
     */
    public function cancel(int $id, ?int $cancelTxId = null): bool
    {
        $data = [
            'status'    => self::STATUS_CANCELLED,
            'closed_at' => date('Y-m-d H:i:s'),
        ];
        if ($cancelTxId !== null) {
            $data['cancel_tx_id'] = $cancelTxId;
        }
        return $this->update($id, $data);
    }

    /**
     * Retourne le montant total des échéances actives d'un crédit
     * (hors annulées et remboursées, dont le paiement a été reversé).
     *
     * C'est cette valeur — et non loan.amount — qui représente le montant
     * effectivement à rembourser par le client.
     */
    public function getTotalScheduledInstallments(int $loanId): float
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT COALESCE(SUM(amount), 0)
             FROM `loan_installments`
             WHERE loan_id = ? AND status NOT IN (?, ?)'
        );
        $stmt->execute([$loanId, LoanInstallment::STATUS_CANCELLED, LoanInstallment::STATUS_REFUNDED]);
        return (float) $stmt->fetchColumn();
    }
}
