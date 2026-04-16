<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Message extends Model
{
    protected string $table = 'messages';
    protected bool $hasUpdatedAt = false;

    /**
     * Poste un message dans une conversation.
     */
    public function post(int $conversationId, int $userId, string $body): int
    {
        return $this->create([
            'conversation_id' => $conversationId,
            'user_id'         => $userId,
            'body'            => $body,
        ]);
    }

    /**
     * Messages d'une conversation (ordre chronologique) avec infos auteur.
     */
    public function getByConversation(int $conversationId): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT m.*, u.username, u.global_role
             FROM messages m
             JOIN users u ON u.id = m.user_id
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC'
        );
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
