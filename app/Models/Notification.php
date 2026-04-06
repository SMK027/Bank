<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle de notifications in-app.
 *
 * Types utilisateur (events liés aux comptes/opérations) :
 *   transfer_received    — virement entrant
 *   transfer_cancelled   — virement annulé par la modération
 *   account_frozen       — compte gelé par la modération
 *   account_unfrozen     — compte dégelé par la modération
 *   account_suspended    — compte utilisateur suspendu
 *   account_banned       — compte utilisateur banni
 *   account_activated    — compte utilisateur réactivé
 *   access_granted       — accès partagé accordé
 *   access_revoked       — accès partagé révoqué
 *   ticket_replied       — réponse à un ticket de support
 *   direct_debit_success        — prélèvement exécuté avec succès
 *   direct_debit_failed         — prélèvement échoué (compte gelé ou erreur technique)
 *   direct_debit_rejected       — prélèvement rejeté (auto ou modération)
 *   direct_debit_cancelled      — prélèvement annulé par la modération
 *   recurring_transfer_failed   — virement récurrent non exécuté
 *   mandate_revoked             — mandat révoqué par la modération
 *
 * Types modérateur supplémentaires :
 *   mod_new_ticket       — nouveau ticket ouvert
 *   mod_transfer_pending — virement planifié en attente
 *   mod_direct_debit_due — prélèvement en attente d'exécution
 */
class Notification extends Model
{
    protected string $table = 'notifications';

    // ── Types ────────────────────────────────────────────────────────────────

    public const ICONS = [
        'transfer_received'     => 'bi-arrow-down-circle-fill text-success',
        'transfer_cancelled'    => 'bi-x-circle-fill text-danger',
        'account_frozen'        => 'bi-snow text-primary',
        'account_unfrozen'      => 'bi-thermometer-sun text-success',
        'account_suspended'     => 'bi-pause-circle-fill text-warning',
        'account_banned'        => 'bi-slash-circle-fill text-danger',
        'account_activated'     => 'bi-play-circle-fill text-success',
        'access_granted'        => 'bi-person-check-fill text-success',
        'access_revoked'        => 'bi-person-x-fill text-danger',
        'ticket_replied'        => 'bi-chat-left-text-fill text-primary',
        'direct_debit_success'       => 'bi-check-circle-fill text-success',
        'direct_debit_failed'        => 'bi-exclamation-circle-fill text-danger',
        'direct_debit_rejected'      => 'bi-slash-circle text-danger',
        'direct_debit_cancelled'     => 'bi-slash-circle text-warning',
        'recurring_transfer_failed'  => 'bi-arrow-repeat text-danger',
        'mandate_revoked'            => 'bi-file-earmark-x text-danger',
        'mod_new_ticket'             => 'bi-ticket-perforated-fill text-warning',
        'mod_transfer_pending'  => 'bi-arrow-left-right text-primary',
        'mod_direct_debit_due'  => 'bi-file-earmark-arrow-down text-warning',
    ];

    // ── Création ─────────────────────────────────────────────────────────────

    /**
     * Crée une notification pour un utilisateur.
     */
    public function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        return $this->create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'link'    => $link,
            'is_read' => 0,
        ]);
    }

    /**
     * Envoie la même notification à tous les modérateurs.
     */
    public function notifyModerators(string $type, string $title, ?string $body = null, ?string $link = null): void
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->query("SELECT id FROM `users` WHERE `global_role` = 'moderator'");
        $mods = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($mods as $modId) {
            $this->notify((int) $modId, $type, $title, $body, $link);
        }
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Toutes les notifications d'un utilisateur (plus récentes en tête).
     */
    public function getForUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT * FROM `notifications`
             WHERE `user_id` = ?
             ORDER BY `created_at` DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Nombre de notifications non lues d'un utilisateur.
     */
    public function countUnread(int $userId): int
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT COUNT(*) FROM `notifications` WHERE `user_id` = ? AND `is_read` = 0'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * Marque une notification comme lue (vérifie l'appartenance).
     */
    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->getPdo()->prepare(
            'UPDATE `notifications` SET `is_read` = 1, `updated_at` = CURRENT_TIMESTAMP
             WHERE `id` = ? AND `user_id` = ?'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues.
     */
    public function markAllRead(int $userId): void
    {
        $stmt = $this->getPdo()->prepare(
            'UPDATE `notifications` SET `is_read` = 1, `updated_at` = CURRENT_TIMESTAMP
             WHERE `user_id` = ? AND `is_read` = 0'
        );
        $stmt->execute([$userId]);
    }

    /**
     * Supprime une notification (vérifie l'appartenance).
     */
    public function deleteForUser(int $id, int $userId): bool
    {
        $stmt = $this->getPdo()->prepare(
            'DELETE FROM `notifications` WHERE `id` = ? AND `user_id` = ?'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Supprime toutes les notifications lues d'un utilisateur.
     */
    public function deleteReadForUser(int $userId): void
    {
        $stmt = $this->getPdo()->prepare(
            'DELETE FROM `notifications` WHERE `user_id` = ? AND `is_read` = 1'
        );
        $stmt->execute([$userId]);
    }

    // ── Helpers statiques ────────────────────────────────────────────────────

    public static function iconClass(string $type): string
    {
        return self::ICONS[$type] ?? 'bi-bell-fill text-muted';
    }
}
