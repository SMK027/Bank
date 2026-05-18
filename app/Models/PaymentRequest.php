<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PaymentRequest extends Model
{
    protected string $table = 'payment_requests';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_REFUSED   = 'refused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    /** Durée de validité par défaut d'une demande (en jours). */
    public const DEFAULT_EXPIRY_DAYS = 7;

    // ── Création ─────────────────────────────────────────────────────────────

    public function createRequest(
        int    $requesterId,
        int    $recipientId,
        float  $amount,
        string $currency,
        string $motif,
        ?int   $fromAccountId = null,
        int    $expiryDays    = self::DEFAULT_EXPIRY_DAYS
    ): int {
        $expiresAt = (new \DateTime("+{$expiryDays} days"))->format('Y-m-d H:i:s');
        return $this->create([
            'requester_id'    => $requesterId,
            'recipient_id'    => $recipientId,
            'from_account_id' => $fromAccountId,
            'amount'          => $amount,
            'currency'        => $currency,
            'motif'           => $motif,
            'status'          => self::STATUS_PENDING,
            'expires_at'      => $expiresAt,
        ]);
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Retourne les demandes enrichies (infos requester + recipient) pour un utilisateur,
     * qu'il soit demandeur ou destinataire.
     * Ordre : pending d'abord, puis par date décroissante.
     */
    public function getForUser(int $userId): array
    {
        $sql = "SELECT pr.*,
                       ru.username AS requester_username,
                       re.username AS recipient_username
                FROM `payment_requests` pr
                JOIN `users` ru ON ru.id = pr.requester_id
                JOIN `users` re ON re.id = pr.recipient_id
                WHERE pr.requester_id = :uid OR pr.recipient_id = :uid2
                ORDER BY
                    FIELD(pr.status, 'pending', 'paid', 'refused', 'cancelled', 'expired'),
                    pr.created_at DESC";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les demandes en attente dont l'utilisateur est le destinataire.
     */
    public function getPendingForRecipient(int $userId): array
    {
        $sql = "SELECT pr.*, ru.username AS requester_username
                FROM `payment_requests` pr
                JOIN `users` ru ON ru.id = pr.requester_id
                WHERE pr.recipient_id = ?
                  AND pr.status = 'pending'
                  AND (pr.expires_at IS NULL OR pr.expires_at > NOW())
                ORDER BY pr.created_at ASC";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Compte les demandes en attente reçues par un utilisateur (badge navbar).
     */
    public function countPendingForRecipient(int $userId): int
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COUNT(*) FROM `payment_requests`
             WHERE recipient_id = ?
               AND status = 'pending'
               AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Transitions d'état ──────────────────────────────────────────────────

    public function markPaid(int $id, int $toAccountId, int $transferId): void
    {
        $this->update($id, [
            'status'        => self::STATUS_PAID,
            'to_account_id' => $toAccountId,
            'transfer_id'   => $transferId,
            'paid_at'       => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function markRefused(int $id): void
    {
        $this->update($id, [
            'status'     => self::STATUS_REFUSED,
            'refused_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function markCancelled(int $id): void
    {
        $this->update($id, [
            'status'       => self::STATUS_CANCELLED,
            'cancelled_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function markExpired(int $id): void
    {
        $this->update($id, ['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Expire automatiquement toutes les demandes dont expires_at est dépassé.
     * Appelable depuis un cron job.
     */
    public function expireOverdue(): int
    {
        $stmt = $this->getPdo()->prepare(
            "UPDATE `payment_requests`
             SET status = 'expired'
             WHERE status = 'pending'
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function isPending(array $request): bool
    {
        if ($request['status'] !== self::STATUS_PENDING) {
            return false;
        }
        if (!empty($request['expires_at']) && strtotime($request['expires_at']) <= time()) {
            return false;
        }
        return true;
    }
}
