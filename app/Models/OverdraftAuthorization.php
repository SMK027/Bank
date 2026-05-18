<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class OverdraftAuthorization extends Model
{
    protected string $table = 'overdraft_authorizations';

    // ── Création ─────────────────────────────────────────────────────────────

    /**
     * Crée une nouvelle autorisation de dépassement pour un compte.
     *
     * @param int         $accountId   Compte concerné
     * @param int         $moderatorId Modérateur émetteur
     * @param float       $extraLimit  Montant supplémentaire autorisé (en plus du découvert existant)
     * @param string      $startDate   Date de début (YYYY-MM-DD)
     * @param string|null $endDate     Date de fin (YYYY-MM-DD) ou null = sans date de fin
     * @param string      $reason      Motif de l'autorisation
     */
    public function createAuthorization(
        int $accountId,
        int $moderatorId,
        float $extraLimit,
        string $startDate,
        ?string $endDate,
        string $reason
    ): int {
        return $this->create([
            'account_id'   => $accountId,
            'moderator_id' => $moderatorId,
            'extra_limit'  => round($extraLimit, 2),
            'reason'       => mb_substr(trim($reason), 0, 500),
            'start_date'   => $startDate,
            'end_date'     => $endDate,
        ]);
    }

    // ── Révocation ───────────────────────────────────────────────────────────

    /**
     * Révoque une autorisation. Retourne false si introuvable ou déjà révoquée.
     */
    public function revoke(int $id, int $moderatorId): bool
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE overdraft_authorizations
                SET revoked_at = ?, revoked_by = ?
              WHERE id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $moderatorId, $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Retourne l'autorisation active pour un compte, ou null si aucune.
     *
     * Une autorisation est active si :
     *  - revoked_at IS NULL
     *  - start_date <= CURDATE()
     *  - end_date IS NULL OR end_date >= CURDATE()
     */
    public function getActiveForAccount(int $accountId): ?array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT oa.*, u.username AS moderator_name
               FROM overdraft_authorizations oa
               LEFT JOIN users u ON u.id = oa.moderator_id
              WHERE oa.account_id = ?
                AND oa.revoked_at IS NULL
                AND oa.start_date <= CURDATE()
                AND (oa.end_date IS NULL OR oa.end_date >= CURDATE())
              ORDER BY oa.created_at DESC
              LIMIT 1'
        );
        $stmt->execute([$accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retourne le montant supplémentaire de découvert autorisé pour un compte.
     * Retourne 0.0 si aucune autorisation active.
     */
    public function getExtraLimitForAccount(int $accountId): float
    {
        $auth = $this->getActiveForAccount($accountId);
        return $auth ? (float) $auth['extra_limit'] : 0.0;
    }

    /**
     * Toutes les autorisations d'un compte (actives + révoquées + expirées).
     */
    public function getForAccount(int $accountId): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT oa.*,
                    u.username  AS moderator_name,
                    r.username  AS revoker_name
               FROM overdraft_authorizations oa
               LEFT JOIN users u ON u.id = oa.moderator_id
               LEFT JOIN users r ON r.id = oa.revoked_by
              WHERE oa.account_id = ?
              ORDER BY oa.created_at DESC'
        );
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Toutes les autorisations (pour la vue de modération globale).
     */
    public function getAll(): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT oa.*,
                    a.name      AS account_name,
                    a.currency  AS account_currency,
                    a.overdraft AS account_overdraft,
                    u.username  AS moderator_name,
                    r.username  AS revoker_name
               FROM overdraft_authorizations oa
               JOIN accounts a ON a.id = oa.account_id
               LEFT JOIN users u ON u.id = oa.moderator_id
               LEFT JOIN users r ON r.id = oa.revoked_by
              ORDER BY oa.created_at DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Statut ───────────────────────────────────────────────────────────────

    /**
     * Détermine le statut lisible d'une autorisation.
     * Retourne 'active', 'revoked', 'expired', ou 'pending'.
     */
    public static function computeStatus(array $auth): string
    {
        if ($auth['revoked_at'] !== null) {
            return 'revoked';
        }
        $today = date('Y-m-d');
        if ($auth['start_date'] > $today) {
            return 'pending';
        }
        if ($auth['end_date'] !== null && $auth['end_date'] < $today) {
            return 'expired';
        }
        return 'active';
    }
}
