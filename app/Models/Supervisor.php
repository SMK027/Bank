<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Gestion des comptes superviseurs.
 *
 * Un superviseur peut contourner provisoirement une fonctionnalité désactivée
 * via un formulaire dédié (identifiant + code PIN). Le bypass est stocké en
 * session et expire à la fin de la session ou explicitement révoqué.
 */
class Supervisor extends Model
{
    protected string $table = 'supervisors';

    // ── Clé de session utilisée pour stocker les bypasses actifs ─────────────
    public const SESSION_KEY = 'supervisor_bypasses';

    // ── Création ─────────────────────────────────────────────────────────────

    /**
     * Crée un nouveau superviseur. Retourne l'ID inséré.
     *
     * @throws \InvalidArgumentException si l'identifiant est déjà pris.
     */
    public function createSupervisor(
        string $firstName,
        string $lastName,
        string $supervisorId,
        string $pin,
        int    $createdBy
    ): int {
        if ($this->findBySupervisorId($supervisorId) !== null) {
            throw new \InvalidArgumentException('Cet identifiant de superviseur est déjà utilisé.');
        }

        return $this->create([
            'first_name'    => mb_substr(trim($firstName), 0, 100),
            'last_name'     => mb_substr(trim($lastName), 0, 100),
            'supervisor_id' => mb_substr(trim($supervisorId), 0, 64),
            'pin_hash'      => password_hash($pin, PASSWORD_BCRYPT),
            'status'        => 'active',
            'created_by'    => $createdBy,
        ]);
    }

    // ── Recherche ─────────────────────────────────────────────────────────────

    public function findBySupervisorId(string $supervisorId): ?array
    {
        return $this->findOneBy(['supervisor_id' => $supervisorId]);
    }

    // ── Authentification ─────────────────────────────────────────────────────

    /**
     * Vérifie l'identifiant et le PIN. Retourne le superviseur si valide et actif,
     * null sinon.
     */
    public function authenticate(string $supervisorId, string $pin): ?array
    {
        $supervisor = $this->findBySupervisorId($supervisorId);
        if ($supervisor === null) {
            return null;
        }
        if (($supervisor['status'] ?? '') !== 'active') {
            return null;
        }
        if (!password_verify($pin, (string) $supervisor['pin_hash'])) {
            return null;
        }
        return $supervisor;
    }

    // ── PIN ──────────────────────────────────────────────────────────────────

    /**
     * Réinitialise le PIN d'un superviseur. Retourne le nouveau PIN en clair
     * (à afficher une seule fois puis à oublier).
     */
    public function resetPin(int $supervisorId): string
    {
        $newPin = $this->generatePin();
        $this->update($supervisorId, [
            'pin_hash' => password_hash($newPin, PASSWORD_BCRYPT),
        ]);
        return $newPin;
    }

    /**
     * Change le PIN vers une valeur fournie explicitement.
     */
    public function setPin(int $supervisorId, string $pin): void
    {
        $this->update($supervisorId, [
            'pin_hash' => password_hash($pin, PASSWORD_BCRYPT),
        ]);
    }

    /**
     * Génère un PIN numérique à 6 chiffres.
     */
    public static function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // ── Statut ───────────────────────────────────────────────────────────────

    public function setStatus(int $supervisorId, string $status): void
    {
        $this->update($supervisorId, ['status' => $status]);
    }

    // ── Bypass session ───────────────────────────────────────────────────────

    /**
     * Enregistre un bypass actif en session pour la fonctionnalité donnée.
     */
    public static function grantBypass(string $featureKey, int $supervisorDbId): void
    {
        $bypasses = \App\Core\Session::get(self::SESSION_KEY, []);
        $bypasses[$featureKey] = [
            'supervisor_id' => $supervisorDbId,
            'granted_at'    => time(),
        ];
        \App\Core\Session::set(self::SESSION_KEY, $bypasses);
    }

    /**
     * Vérifie si un bypass est actif en session pour la fonctionnalité donnée.
     */
    public static function hasBypass(string $featureKey): bool
    {
        $bypasses = \App\Core\Session::get(self::SESSION_KEY, []);
        return isset($bypasses[$featureKey]);
    }

    /**
     * Révoque tous les bypasses de la session courante.
     */
    public static function revokeAllBypasses(): void
    {
        \App\Core\Session::remove(self::SESSION_KEY);
    }
}
