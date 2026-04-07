<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Limitation du taux de tentatives de connexion par adresse IP.
 *
 * Règle : 5 échecs dans une fenêtre glissante de 15 minutes
 *         → blocage de l'IP pendant 30 minutes.
 * Le compte utilisateur n'est pas suspendu, seule l'IP est bloquée.
 */
class LoginRateLimit extends Model
{
    protected string $table       = 'login_rate_limits';
    protected bool   $hasUpdatedAt = false;

    public const WINDOW_MINUTES  = 15; // durée de la fenêtre de comptage
    public const MAX_ATTEMPTS    = 5;  // seuil déclenchant le blocage
    public const LOCKOUT_MINUTES = 30; // durée du blocage

    // ─── Vérification ───────────────────────────────────────────────────────

    /**
     * Vérifie si l'IP est actuellement bloquée.
     */
    public function isBlocked(string $ip): bool
    {
        return $this->getBlockedUntil($ip) !== null;
    }

    /**
     * Retourne la date/heure de fin de blocage, ou null si l'IP n'est pas bloquée.
     */
    public function getBlockedUntil(string $ip): ?string
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT blocked_until FROM `login_rate_limits`
             WHERE ip_address = ? AND blocked_until > NOW()
             LIMIT 1"
        );
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        return ($row !== false && !empty($row['blocked_until'])) ? $row['blocked_until'] : null;
    }

    // ─── Enregistrement ─────────────────────────────────────────────────────

    /**
     * Enregistre un échec de connexion pour l'IP.
     * Retourne true si l'IP vient d'être bloquée au cours de cet appel.
     */
    public function recordFailedAttempt(string $ip): bool
    {
        $windowCutoff = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $now          = date('Y-m-d H:i:s');

        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `login_rate_limits` WHERE ip_address = ? LIMIT 1"
        );
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        if ($row === false) {
            // Première tentative pour cette IP
            $this->getPdo()->prepare(
                "INSERT INTO `login_rate_limits`
                 (ip_address, attempts, window_start, blocked_until, created_at)
                 VALUES (?, 1, ?, NULL, ?)"
            )->execute([$ip, $now, $now]);
            return false;
        }

        // La fenêtre de comptage est-elle expirée ?
        if ($row['window_start'] < $windowCutoff) {
            // Réinitialiser la fenêtre (nouvelle tentative hors de la fenêtre précédente)
            $this->getPdo()->prepare(
                "UPDATE `login_rate_limits`
                 SET attempts = 1, window_start = ?, blocked_until = NULL
                 WHERE ip_address = ?"
            )->execute([$now, $ip]);
            return false;
        }

        // Dans la fenêtre active : incrémenter le compteur
        $newAttempts = (int)$row['attempts'] + 1;

        if ($newAttempts >= self::MAX_ATTEMPTS && empty($row['blocked_until'])) {
            // Seuil atteint : bloquer l'IP
            $blockedUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
            $this->getPdo()->prepare(
                "UPDATE `login_rate_limits`
                 SET attempts = ?, blocked_until = ?
                 WHERE ip_address = ?"
            )->execute([$newAttempts, $blockedUntil, $ip]);
            return true;
        }

        $this->getPdo()->prepare(
            "UPDATE `login_rate_limits` SET attempts = ? WHERE ip_address = ?"
        )->execute([$newAttempts, $ip]);
        return false;
    }

    /**
     * Supprime l'entrée de l'IP (réinitialisation après connexion réussie).
     */
    public function clearIp(string $ip): void
    {
        $this->getPdo()->prepare(
            "DELETE FROM `login_rate_limits` WHERE ip_address = ?"
        )->execute([$ip]);
    }

    // ─── Résolution IP ──────────────────────────────────────────────────────

    /**
     * Résout l'adresse IP réelle du client (gère les proxies / Docker gateway).
     * Utilise X-Forwarded-For uniquement si REMOTE_ADDR est une IP privée.
     */
    public static function resolveClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // IP publique directe : on l'utilise telle quelle
        if (!self::isPrivateIp($remoteAddr)) {
            return $remoteAddr;
        }

        // Derrière un proxy/Docker : lire X-Forwarded-For
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if ($xff !== null) {
            $candidate = trim(explode(',', $xff)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return $remoteAddr;
    }

    private static function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
