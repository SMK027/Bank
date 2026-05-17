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
             LEFT JOIN `accounts` a ON a.id = l.account_id
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
             LEFT JOIN `accounts` a ON a.id = l.account_id
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
             LEFT JOIN `accounts` a ON a.id = l.account_id
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
             LEFT JOIN `accounts` a ON a.id = l.account_id
             JOIN `users`    m ON m.id = l.granted_by
             WHERE l.user_id = ? AND l.status = ?
             ORDER BY l.granted_at DESC'
        );
        $stmt->execute([$userId, self::STATUS_PENDING]);
        return $stmt->fetchAll();
    }

    /**
     * Retourne vrai si le compte possède au moins un crédit en cours
     * (statut active ou pending_acceptance).
     */
    public function hasActiveLoanForAccount(int $accountId): bool
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT COUNT(*) FROM `loans`
             WHERE account_id = ? AND status IN (?, ?)'
        );
        $stmt->execute([$accountId, self::STATUS_ACTIVE, self::STATUS_PENDING]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Retourne les crédits actifs ou en attente d'un utilisateur dont le
     * compte de prélèvement a été supprimé (account_id IS NULL).
     * Utilisé par la modération pour réaffecter le crédit à un autre compte.
     */
    public function getOrphanedForUser(int $userId): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT * FROM `loans`
             WHERE user_id = ? AND account_id IS NULL
               AND status IN (?, ?)
             ORDER BY granted_at DESC'
        );
        $stmt->execute([$userId, self::STATUS_ACTIVE, self::STATUS_PENDING]);
        return $stmt->fetchAll();
    }

    /**
     * Réaffecte un crédit orphelin (account_id NULL) à un autre compte du
     * contractant. Aucune vérification de propriété n'est faite ici : le
     * contrôleur doit s'assurer que le compte appartient bien à l'utilisateur
     * du crédit (loan.user_id == account.user_id).
     */
    public function reassignAccount(int $loanId, int $newAccountId): bool
    {
        return $this->update($loanId, ['account_id' => $newAccountId]);
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
     */
    public function recordRepayment(int $id, float $amount): bool
    {
        $loan = $this->find($id);
        if (!$loan) {
            return false;
        }

        $newRepaid      = round((float) $loan['amount_repaid'] + $amount, 2);
        $effectiveTotal = $this->getTotalScheduledInstallments($id);

        $this->update($id, ['amount_repaid' => $newRepaid]);

        // Solder uniquement si tout le capital est planifié ET que tout est remboursé.
        // Si effectiveTotal < loan.amount, il reste du capital non planifié → pas soldé.
        $allPrincipalScheduled = $this->getSchedulablePrincipal($id) <= 0;
        if ($allPrincipalScheduled && $effectiveTotal > 0 && $newRepaid >= $effectiveTotal) {
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
     * Rouvre un crédit soldé si des mensualités restent effectivement dues.
     * N'agit que sur les crédits en statut 'closed'.
     *
     * La dette effective = toutes les mensualités sauf les annulées.
     * Les mensualités "remboursées" (refunded) sont incluses : le paiement a été
     * reversé, donc la dette n'est pas éteinte (≠ annulation qui retire la dette).
     */
    public function reopenIfNeeded(int $id): bool
    {
        $loan = $this->find($id);
        if (!$loan || $loan['status'] !== self::STATUS_CLOSED) {
            return false;
        }
        // Total incluant paid + pending + refunded ; exclut uniquement cancelled
        $stmt = $this->getPdo()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM `loan_installments`
             WHERE loan_id = ? AND status != ?'
        );
        $stmt->execute([$id, LoanInstallment::STATUS_CANCELLED]);
        $effectiveDebt = (float) $stmt->fetchColumn();
        $repaid        = (float) $loan['amount_repaid'];
        if ($repaid < $effectiveDebt) {
            $this->update($id, ['status' => self::STATUS_ACTIVE, 'closed_at' => null]);
            return true;
        }
        return false;
    }

    /**
     * Recalcule amount_repaid depuis les mensualités (source de vérité).
     * amount_repaid = SUM des mensualités dont le statut est 'paid' uniquement
     * (les mensualités remboursées ont déjà été déduites ; les annulées n'ont jamais été encaissées).
     */
    public function recalculateAmountRepaid(int $id): void
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM `loan_installments` WHERE loan_id = ? AND status = ?'
        );
        $stmt->execute([$id, LoanInstallment::STATUS_PAID]);
        $repaid = (float) $stmt->fetchColumn();
        $this->update($id, ['amount_repaid' => $repaid]);
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
     * Retourne le montant total effectif des échéances d'un crédit.
     * Exclut les mensualités annulées et remboursées (non actives sur le plan de remboursement).
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

    /**
     * Retourne le capital restant à planifier (non encore alloué à des échéances actives).
     * "Actives" = pending, paid, failed (pas annulées ni remboursées).
     */
    public function getSchedulablePrincipal(int $id): float
    {
        $loan = $this->find($id);
        if (!$loan) {
            return 0.0;
        }
        $stmt = $this->getPdo()->prepare(
            'SELECT COALESCE(SUM(principal), 0)
             FROM `loan_installments`
             WHERE loan_id = ? AND status NOT IN (?, ?)'
        );
        $stmt->execute([$id, LoanInstallment::STATUS_CANCELLED, LoanInstallment::STATUS_REFUNDED]);
        return max(0.0, round((float) $loan['amount'] - (float) $stmt->fetchColumn(), 2));
    }

    /**
     * Retourne le total des parts capital des mensualités payées (non remboursées).
     * Utilisé pour calculer le capital restant dû lors d'un recalcul d'intérêts.
     */
    public function getPaidPrincipal(int $id): float
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT COALESCE(SUM(principal), 0)
             FROM `loan_installments`
             WHERE loan_id = ? AND status = ?'
        );
        $stmt->execute([$id, LoanInstallment::STATUS_PAID]);
        return (float) $stmt->fetchColumn();
    }
}
