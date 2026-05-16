<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Gère les feature flags de l'application (connexion, inscription,
 * virements, partages, etc.). Les flags sont stockés en base et
 * mis en cache en mémoire pour la durée d'une requête.
 *
 * @see database/migrations/054_feature_flags.sql
 */
class FeatureFlag extends Model
{
    protected string $table = 'feature_flags';
    protected bool $hasCreatedAt = false; // table sans created_at
    // updated_at est géré directement par MySQL (ON UPDATE CURRENT_TIMESTAMP)
    protected bool $hasUpdatedAt = false;

    /** Cache mémoire des flags pour la durée d'une requête. */
    private static ?array $cache = null;

    /**
     * Indique si un flag est activé. Renvoie le défaut si le flag n'existe
     * pas (ex. table absente en phase de migration), pour éviter de casser
     * l'application avant que la migration ait été exécutée.
     */
    public static function isEnabled(string $key, bool $default = true): bool
    {
        $flags = self::loadAll();
        if (!array_key_exists($key, $flags)) {
            return $default;
        }
        return (bool) $flags[$key]['enabled'];
    }

    /**
     * Retourne la définition d'un flag (clé, label, description...).
     */
    public static function get(string $key): ?array
    {
        $flags = self::loadAll();
        return $flags[$key] ?? null;
    }

    /**
     * Retourne tous les flags, groupés par catégorie pour l'affichage.
     *
     * @return array<string, array<int, array>>
     */
    public static function getAllGrouped(): array
    {
        $grouped = [];
        foreach (self::loadAll() as $flag) {
            $grouped[$flag['category']][] = $flag;
        }
        ksort($grouped);
        return $grouped;
    }

    /**
     * Active ou désactive un flag. Renvoie true si la mise à jour a réussi.
     */
    public static function setEnabled(string $key, bool $enabled, ?int $moderatorId = null): bool
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE `feature_flags`
                SET `enabled` = ?, `updated_by` = ?
              WHERE `flag_key` = ?'
        );
        $ok = $stmt->execute([$enabled ? 1 : 0, $moderatorId, $key]);
        self::$cache = null;
        return $ok && $stmt->rowCount() > 0;
    }

    /**
     * Charge tous les flags (avec cache mémoire requête).
     *
     * @return array<string, array>
     */
    private static function loadAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $pdo  = Database::getInstance();
            $rows = $pdo->query('SELECT * FROM `feature_flags`')->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            // Table absente (migration pas encore exécutée) : on considère
            // que toutes les fonctionnalités sont actives par défaut.
            return self::$cache = [];
        }

        $cache = [];
        foreach ($rows as $row) {
            $cache[$row['flag_key']] = $row;
        }
        return self::$cache = $cache;
    }

    /** Pour les tests : réinitialise le cache. */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
