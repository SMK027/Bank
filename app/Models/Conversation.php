<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Conversation extends Model
{
    protected string $table = 'conversations';

    public const TYPE_MOD_ONLY = 'mod_only';
    public const TYPE_MOD_USER = 'mod_user';

    /**
     * Crée une conversation avec ses participants.
     *
     * @param  int[]  $participantIds  IDs des utilisateurs participant
     * @return int    ID de la conversation
     */
    public function createConversation(string $subject, string $type, int $createdBy, array $participantIds): int
    {
        $convId = $this->create([
            'subject'    => $subject,
            'type'       => $type,
            'is_closed'  => 0,
            'created_by' => $createdBy,
        ]);

        // Ajouter les participants (inclure le créateur)
        $ids = array_unique(array_merge([$createdBy], $participantIds));
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `conversation_participants` (`conversation_id`, `user_id`, `created_at`)
             VALUES (?, ?, NOW())'
        );
        foreach ($ids as $uid) {
            $stmt->execute([$convId, (int) $uid]);
        }

        return $convId;
    }

    /**
     * Conversations auxquelles un utilisateur participe (triées par dernière activité).
     */
    public function getForUser(int $userId): array
    {
        $sql = "SELECT c.*, cp.last_read_at,
                       (SELECT COUNT(*) FROM messages m
                        WHERE m.conversation_id = c.id
                          AND m.created_at > COALESCE(cp.last_read_at, '1970-01-01')) AS unread_count,
                       (SELECT m2.body FROM messages m2
                        WHERE m2.conversation_id = c.id
                        ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
                       (SELECT m3.created_at FROM messages m3
                        WHERE m3.conversation_id = c.id
                        ORDER BY m3.created_at DESC LIMIT 1) AS last_message_at
                FROM conversations c
                JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
                ORDER BY COALESCE(last_message_at, c.created_at) DESC";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie qu'un utilisateur est participant.
     */
    public function isParticipant(int $conversationId, int $userId): bool
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT COUNT(*) FROM `conversation_participants`
             WHERE `conversation_id` = ? AND `user_id` = ?'
        );
        $stmt->execute([$conversationId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Participants d'une conversation avec infos utilisateur.
     */
    public function getParticipants(int $conversationId): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT u.id, u.username, u.global_role, cp.last_read_at
             FROM conversation_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.conversation_id = ?
             ORDER BY u.username'
        );
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un participant à une conversation existante.
     */
    public function addParticipant(int $conversationId, int $userId): void
    {
        $stmt = $this->getPdo()->prepare(
            'INSERT IGNORE INTO `conversation_participants` (`conversation_id`, `user_id`, `created_at`)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$conversationId, $userId]);
    }

    /**
     * Met à jour le curseur de lecture d'un participant.
     */
    public function markRead(int $conversationId, int $userId): void
    {
        $stmt = $this->getPdo()->prepare(
            'UPDATE `conversation_participants` SET `last_read_at` = NOW()
             WHERE `conversation_id` = ? AND `user_id` = ?'
        );
        $stmt->execute([$conversationId, $userId]);
    }

    /**
     * Ferme une conversation.
     */
    public function close(int $conversationId): bool
    {
        return $this->update($conversationId, ['is_closed' => 1]);
    }

    /**
     * Rouvre une conversation.
     */
    public function reopen(int $conversationId): bool
    {
        return $this->update($conversationId, ['is_closed' => 0]);
    }

    /**
     * Nombre total de messages non lus pour un utilisateur (toutes conversations).
     */
    public function countTotalUnread(int $userId): int
    {
        $sql = "SELECT COALESCE(SUM(sub.cnt), 0) FROM (
                    SELECT COUNT(*) AS cnt
                    FROM messages m
                    JOIN conversation_participants cp
                      ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
                    WHERE m.created_at > COALESCE(cp.last_read_at, '1970-01-01')
                ) AS sub";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
