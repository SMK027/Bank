<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Gestion des tokens de réinitialisation de mot de passe.
 * Le token brut est envoyé par email (jamais stocké) ; seul son hash SHA-256 est en base.
 */
class PasswordReset extends Model
{
    protected string $table      = 'password_resets';
    protected bool   $hasUpdatedAt = false;

    /** Durée de validité du token en secondes (30 minutes). */
    private const TTL = 1800;

    /**
     * Crée un token pour l'utilisateur (supprime les anciens au préalable).
     * Retourne le token en clair à envoyer par email.
     */
    public function createToken(int $userId): string
    {
        // Invalider les tokens précédents
        $this->getPdo()
             ->prepare("DELETE FROM `password_resets` WHERE user_id = ?")
             ->execute([$userId]);

        $token = bin2hex(random_bytes(32)); // 64 caractères hex
        $hash  = hash('sha256', $token);

        $this->create([
            'user_id'    => $userId,
            'token_hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL),
        ]);

        return $token;
    }

    /**
     * Retrouve un enregistrement valide (non expiré, non utilisé) à partir du token brut.
     */
    public function findValidByToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `password_resets`
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Marque un token comme utilisé (usage unique).
     */
    public function markUsed(int $id): void
    {
        $this->getPdo()
             ->prepare("UPDATE `password_resets` SET used_at = NOW() WHERE id = ?")
             ->execute([$id]);
    }
}
