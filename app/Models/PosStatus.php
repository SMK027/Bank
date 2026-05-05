<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * État global du terminal de paiement (TPE).
 *
 * Une seule ligne (id=1) pilote l'activation du TPE pour toute la
 * plateforme. La modération peut désactiver le TPE en temps réel
 * avec un motif obligatoire et, optionnellement, jusqu'à une date
 * donnée (réactivation automatique au premier appel à `current()`
 * après expiration).
 */
class PosStatus
{
    public const ID = 1;

    /**
     * Retourne l'état courant du TPE. Si une date de réactivation
     * automatique est dépassée, l'état est restauré transparent à
     * l'appelant (et persisté en base).
     *
     * @return array{
     *   disabled_at:?string, disabled_until:?string, disabled_by:?int,
     *   reason:string, updated_at:?string, is_disabled:bool
     * }
     */
    public static function current(): array
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM pos_status WHERE id = ? LIMIT 1');
        $stmt->execute([self::ID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            // Bootstrap si la migration n'a pas pu insérer la ligne.
            $pdo->prepare('INSERT INTO pos_status (id) VALUES (?)')->execute([self::ID]);
            $row = [
                'id'             => self::ID,
                'disabled_at'    => null,
                'disabled_until' => null,
                'disabled_by'    => null,
                'reason'         => '',
                'updated_at'     => null,
            ];
        }

        // Réactivation automatique si la date d'expiration est passée.
        if (!empty($row['disabled_at'])
            && !empty($row['disabled_until'])
            && strtotime((string) $row['disabled_until']) <= time()
        ) {
            self::doEnable(null);
            $row['disabled_at']    = null;
            $row['disabled_until'] = null;
            $row['disabled_by']    = null;
            $row['reason']         = '';
        }

        $row['is_disabled'] = !empty($row['disabled_at']);
        return $row;
    }

    public static function isDisabled(): bool
    {
        return self::current()['is_disabled'];
    }

    /**
     * Désactive le TPE.
     *
     * @param int         $moderatorId  Auteur de la désactivation.
     * @param string      $reason       Motif (obligatoire, ≤ 500 car.).
     * @param string|null $until        Date/heure (Y-m-d H:i:s) ou null.
     */
    public static function disable(int $moderatorId, string $reason, ?string $until = null): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE pos_status
                SET disabled_at    = ?,
                    disabled_until = ?,
                    disabled_by    = ?,
                    reason         = ?
              WHERE id = ?'
        );
        $stmt->execute([
            date('Y-m-d H:i:s'),
            $until,
            $moderatorId,
            mb_substr($reason, 0, 500),
            self::ID,
        ]);
    }

    /** Réactive le TPE. */
    public static function enable(int $moderatorId): void
    {
        self::doEnable($moderatorId);
    }

    private static function doEnable(?int $moderatorId): void
    {
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE pos_status
                SET disabled_at    = NULL,
                    disabled_until = NULL,
                    disabled_by    = ?,
                    reason         = \'\'
              WHERE id = ?'
        );
        $stmt->execute([$moderatorId, self::ID]);
    }
}
