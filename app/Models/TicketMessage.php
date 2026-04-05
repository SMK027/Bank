<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class TicketMessage extends Model
{
    protected string $table = 'ticket_messages';

    /**
     * Retourne tous les messages d'un ticket, triés du plus ancien au plus récent,
     * avec le nom d'utilisateur de l'auteur.
     */
    public function getByTicket(int $ticketId): array
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT m.*, u.username
               FROM ticket_messages m
               JOIN users u ON u.id = m.user_id
              WHERE m.ticket_id = :tid
              ORDER BY m.created_at ASC'
        );
        $stmt->execute([':tid' => $ticketId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Poste un nouveau message.
     */
    public function post(int $ticketId, int $userId, string $body, bool $isStaff = false): int
    {
        return $this->create([
            'ticket_id' => $ticketId,
            'user_id'   => $userId,
            'body'      => $body,
            'is_staff'  => (int) $isStaff,
        ]);
    }
}
