<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Friendship extends Model
{
    protected string $table = 'friendships';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REFUSED   = 'refused';
    public const STATUS_CANCELLED = 'cancelled';

    // ── Création ─────────────────────────────────────────────────────────────

    /**
     * Envoie une demande d'amitié.
     * Si une demande annulée/refusée existe déjà, elle est réactivée.
     */
    public function sendRequest(int $requesterId, int $recipientId): int
    {
        // Vérifier si une ligne existe déjà (quelle que soit la direction)
        $existing = $this->findBetween($requesterId, $recipientId);
        if ($existing) {
            // Réactiver une demande annulée ou refusée si on est l'expéditeur
            if (
                in_array($existing['status'], [self::STATUS_CANCELLED, self::STATUS_REFUSED], true)
                && (int) $existing['requester_id'] === $requesterId
            ) {
                $this->update((int) $existing['id'], ['status' => self::STATUS_PENDING]);
                return (int) $existing['id'];
            }
            return (int) $existing['id'];
        }

        return $this->create([
            'requester_id' => $requesterId,
            'recipient_id' => $recipientId,
            'status'       => self::STATUS_PENDING,
        ]);
    }

    // ── Transitions d'état ──────────────────────────────────────────────────

    public function acceptRequest(int $id): void
    {
        $this->update($id, ['status' => self::STATUS_ACCEPTED]);
    }

    public function refuseRequest(int $id): void
    {
        $this->update($id, ['status' => self::STATUS_REFUSED]);
    }

    public function cancelRequest(int $id): void
    {
        $this->update($id, ['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Supprime définitivement une amitié acceptée.
     */
    public function removeFriend(int $userId, int $friendId): void
    {
        $stmt = $this->getPdo()->prepare(
            "DELETE FROM `friendships`
             WHERE (requester_id = ? AND recipient_id = ?)
                OR (requester_id = ? AND recipient_id = ?)"
        );
        $stmt->execute([$userId, $friendId, $friendId, $userId]);
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Retourne la ligne de relation entre deux utilisateurs, quelle que soit la direction.
     */
    public function findBetween(int $userA, int $userB): ?array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `friendships`
             WHERE (requester_id = ? AND recipient_id = ?)
                OR (requester_id = ? AND recipient_id = ?)
             LIMIT 1"
        );
        $stmt->execute([$userA, $userB, $userB, $userA]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Statut de la relation entre deux utilisateurs.
     * Retourne null si aucune relation n'existe.
     */
    public function getStatus(int $userA, int $userB): ?string
    {
        $row = $this->findBetween($userA, $userB);
        return $row ? $row['status'] : null;
    }

    /**
     * Retourne true si les deux utilisateurs sont amis (statut accepted).
     */
    public function areFriends(int $userA, int $userB): bool
    {
        return $this->getStatus($userA, $userB) === self::STATUS_ACCEPTED;
    }

    /**
     * Retourne la liste des amis acceptés d'un utilisateur (infos username, id).
     */
    public function getFriends(int $userId): array
    {
        $sql = "SELECT
                    f.id          AS friendship_id,
                    f.created_at  AS friends_since,
                    CASE WHEN f.requester_id = :uid THEN f.recipient_id ELSE f.requester_id END AS friend_id,
                    u.username    AS friend_username
                FROM `friendships` f
                JOIN `users` u
                  ON u.id = CASE WHEN f.requester_id = :uid2 THEN f.recipient_id ELSE f.requester_id END
                WHERE (f.requester_id = :uid3 OR f.recipient_id = :uid4)
                  AND f.status = 'accepted'
                ORDER BY u.username ASC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([':uid' => $userId, ':uid2' => $userId, ':uid3' => $userId, ':uid4' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Demandes en attente reçues par un utilisateur.
     */
    public function getPendingReceived(int $userId): array
    {
        $sql = "SELECT f.*, u.username AS requester_username
                FROM `friendships` f
                JOIN `users` u ON u.id = f.requester_id
                WHERE f.recipient_id = ?
                  AND f.status = 'pending'
                ORDER BY f.created_at DESC";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Demandes en attente envoyées par un utilisateur.
     */
    public function getPendingSent(int $userId): array
    {
        $sql = "SELECT f.*, u.username AS recipient_username
                FROM `friendships` f
                JOIN `users` u ON u.id = f.recipient_id
                WHERE f.requester_id = ?
                  AND f.status = 'pending'
                ORDER BY f.created_at DESC";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Compte les demandes d'amitié reçues et en attente (badge navbar).
     */
    public function countPendingReceived(int $userId): int
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COUNT(*) FROM `friendships`
             WHERE recipient_id = ? AND status = 'pending'"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
