<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Ticket extends Model
{
    protected string $table = 'tickets';

    /** Types de tickets disponibles (valeur => libellé). */
    public const TYPES = [
        'direct_debit_request'   => 'Demande de prélèvement automatique',
        'minor_proxy'            => 'Demande de procuration sur compte mineur',
        'minor_account_create'   => 'Création de compte mineur',
        'minor_overdraft_access' => 'Ajout de compte mineur sur compte avec découvert',
        'account_freeze'         => 'Gel / Dégel de compte',
        'transfer_request'       => 'Demande de virement exceptionnel',
        'account_access'         => 'Demande d\'accès à un compte',
        'other'                  => 'Autre demande',
    ];

    /** Statuts possibles (valeur => libellé). */
    public const STATUSES = [
        'open'          => 'Ouvert',
        'in_progress'   => 'En cours',
        'pending_user'  => 'En attente de réponse',
        'resolved'      => 'Résolu',
        'rejected'      => 'Rejeté',
        'closed'        => 'Fermé',
    ];

    /** Statuts dits "terminaux" (aucune réponse attendue). */
    public const CLOSED_STATUSES = ['resolved', 'rejected', 'closed'];

    /** Priorités. */
    public const PRIORITIES = [
        'low'    => 'Faible',
        'normal' => 'Normale',
        'high'   => 'Élevée',
    ];

    /** Couleurs Bootstrap associées aux statuts. */
    public const STATUS_COLORS = [
        'open'         => 'primary',
        'in_progress'  => 'warning',
        'pending_user' => 'info',
        'resolved'     => 'success',
        'rejected'     => 'danger',
        'closed'       => 'secondary',
    ];

    // --------------------------------------------------------
    // Requêtes d'accès
    // --------------------------------------------------------

    /**
     * Retourne tous les tickets d'un utilisateur, triés du plus récent.
     */
    public function getByUser(int $userId): array
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT t.*, u.username
               FROM tickets t
               JOIN users u ON u.id = t.user_id
              WHERE t.user_id = :uid
              ORDER BY t.updated_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne tous les tickets (pour la modération), triés statut + date.
     */
    public function getAll(string $statusFilter = ''): array
    {
        $pdo  = $this->getPdo();
        $sql  = 'SELECT t.*, u.username
                   FROM tickets t
                   JOIN users u ON u.id = t.user_id';
        $params = [];
        if ($statusFilter !== '') {
            $sql   .= ' WHERE t.status = :status';
            $params[':status'] = $statusFilter;
        }
        $sql .= ' ORDER BY
                    CASE t.status
                        WHEN \'open\'         THEN 1
                        WHEN \'in_progress\'  THEN 2
                        WHEN \'pending_user\' THEN 3
                        WHEN \'resolved\'     THEN 4
                        WHEN \'rejected\'     THEN 5
                        WHEN \'closed\'       THEN 6
                        ELSE 7
                    END,
                    t.updated_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne un ticket avec les infos de l'utilisateur propriétaire.
     */
    public function findWithUser(int $id): ?array
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT t.*, u.username, u.email
               FROM tickets t
               JOIN users u ON u.id = t.user_id
              WHERE t.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Nombre de tickets ouverts (pour badge de modération).
     */
    public function countOpen(): int
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM tickets WHERE status IN (\'open\', \'in_progress\', \'pending_user\')'
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // --------------------------------------------------------
    // Helpers statiques
    // --------------------------------------------------------

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function statusColor(string $status): string
    {
        return self::STATUS_COLORS[$status] ?? 'secondary';
    }

    public static function isClosed(string $status): bool
    {
        return in_array($status, self::CLOSED_STATUSES, true);
    }
}
