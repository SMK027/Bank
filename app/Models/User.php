<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_BANNED    = 'banned';

    public function register(string $username, string $email, string $password, string $birthDate): int
    {
        $userId = $this->create([
            'username'    => $username,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'global_role' => 'user',
            'birth_date'  => $birthDate,
        ]);

        $this->assignAccountNumber($userId);

        return $userId;
    }

    // ---------------------------------------------------------------
    // Numéro de compte et code PIN
    // ---------------------------------------------------------------

    /**
     * Génère un numéro de compte unique au format BKxxxxxxxx (8 chiffres).
     */
    private function generateAccountNumber(): string
    {
        do {
            $number = 'BK' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $exists = $this->findOneBy(['account_number' => $number]);
        } while ($exists !== null);

        return $number;
    }

    /**
     * Attribue un nouveau numéro de compte à un utilisateur.
     * Retourne le numéro généré.
     */
    public function assignAccountNumber(int $userId): string
    {
        $number = $this->generateAccountNumber();
        $this->update($userId, ['account_number' => $number]);
        return $number;
    }

    /**
     * Réinitialise le numéro de compte d'un utilisateur (génère un nouveau numéro).
     * Retourne le nouveau numéro.
     */
    public function resetAccountNumber(int $userId): string
    {
        return $this->assignAccountNumber($userId);
    }

    /**
     * Trouve un utilisateur par son numéro de compte.
     */
    public function findByAccountNumber(string $accountNumber): ?array
    {
        return $this->findOneBy(['account_number' => $accountNumber]);
    }

    /**
     * Définit ou met à jour le code PIN (6 chiffres), stocké haché.
     * Efface aussi le drapeau pin_must_change s'il était actif.
     */
    public function setPin(int $userId, string $pin): bool
    {
        return $this->update($userId, [
            'pin_hash'       => password_hash($pin, PASSWORD_BCRYPT),
            'pin_must_change' => 0,
        ]);
    }

    /**
     * Réinitialise le PIN d'un utilisateur avec un code temporaire à 6 chiffres
     * généré aléatoirement. L'utilisateur devra le changer à sa prochaine connexion.
     * Retourne le code temporaire en clair (à communiquer à l'utilisateur).
     */
    public function resetPinByModerator(int $userId): string
    {
        $tempPin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->update($userId, [
            'pin_hash'       => password_hash($tempPin, PASSWORD_BCRYPT),
            'pin_must_change' => 1,
        ]);
        return $tempPin;
    }

    /**
     * Authentifie un utilisateur par numéro de compte + code PIN.
     * Lève automatiquement une suspension expirée.
     */
    public function authenticateByPin(string $accountNumber, string $pin): ?array
    {
        $user = $this->findByAccountNumber($accountNumber);
        if (!$user || empty($user['pin_hash'])) {
            return null;
        }

        if (!password_verify($pin, $user['pin_hash'])) {
            return null;
        }

        // Lever automatiquement une suspension expirée
        $status = $user['status'] ?? self::STATUS_ACTIVE;
        if ($status === self::STATUS_SUSPENDED && !empty($user['suspended_until'])) {
            if (strtotime($user['suspended_until']) < time()) {
                $this->activate((int) $user['id']);
                $user['status']          = self::STATUS_ACTIVE;
                $user['suspended_until'] = null;
            }
        }

        return $user;
    }

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findOneBy(['email' => $email]);
        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        // Lever automatiquement une suspension expirée
        $status = $user['status'] ?? self::STATUS_ACTIVE;
        if ($status === self::STATUS_SUSPENDED && !empty($user['suspended_until'])) {
            if (strtotime($user['suspended_until']) < time()) {
                $this->activate((int) $user['id']);
                $user['status']          = self::STATUS_ACTIVE;
                $user['suspended_until'] = null;
            }
        }

        return $user;
    }

    public function suspend(int $userId, ?string $until = null): bool
    {
        return $this->update($userId, [
            'status'          => self::STATUS_SUSPENDED,
            'suspended_until' => $until,
        ]);
    }

    public function ban(int $userId): bool
    {
        return $this->update($userId, [
            'status'          => self::STATUS_BANNED,
            'suspended_until' => null,
        ]);
    }

    public function activate(int $userId): bool
    {
        return $this->update($userId, [
            'status'          => self::STATUS_ACTIVE,
            'suspended_until' => null,
        ]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function updatePassword(int $userId, string $newPassword): bool
    {
        return $this->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
    }

    public function updateRole(int $userId, string $role): bool
    {
        $validRoles = ['user', 'moderator'];
        if (!in_array($role, $validRoles, true)) {
            return false;
        }
        return $this->update($userId, ['global_role' => $role]);
    }

    public function updateBirthDate(int $userId, string $birthDate): bool
    {
        return $this->update($userId, ['birth_date' => $birthDate]);
    }

    /**
     * Retourne vrai si l'utilisateur est mineur (moins de 18 ans).
     * Renvoie false si birth_date est NULL (utilisateur existant → traité comme majeur).
     */
    public static function isMinorFromDate(?string $birthDate): bool
    {
        if (empty($birthDate)) {
            return false;
        }
        $dob = \DateTime::createFromFormat('Y-m-d', $birthDate);
        if (!$dob) {
            return false;
        }
        return (new \DateTime())->diff($dob)->y < 18;
    }

    /**
     * Retourne vrai si l'utilisateur a un profil professionnel validé.
     */
    public static function isProfessional(?array $user): bool
    {
        return $user !== null && !empty($user['is_professional']);
    }

    /**
     * Met à jour le statut professionnel d'un utilisateur.
     */
    public function setProfessional(int $userId, string $companyName, string $siret): bool
    {
        return $this->update($userId, [
            'is_professional' => 1,
            'company_name'    => $companyName,
            'siret'           => $siret,
        ]);
    }

    /**
     * Retire le statut professionnel d'un utilisateur.
     */
    public function removeProfessional(int $userId): bool
    {
        return $this->update($userId, [
            'is_professional' => 0,
            'company_name'    => null,
            'siret'           => null,
        ]);
    }

    public function searchByQuery(string $q, int $limit = 15): array
    {
        $term = '%' . $q . '%';
        $stmt = $this->getPdo()->prepare(
            'SELECT id, username, email, birth_date
             FROM users
             WHERE username LIKE ? OR email LIKE ?
             ORDER BY username ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$term, $term]);
        return $stmt->fetchAll();
    }

    // ── Suspension TPE au niveau utilisateur ─────────────────────────────────

    /**
     * Vérifie si le commerçant est suspendu du TPE.
     * Tient compte de la réactivation automatique par `pos_suspended_until`.
     */
    public static function isPosSuspended(?array $user): bool
    {
        if ($user === null || empty($user['pos_suspended_at'])) {
            return false;
        }
        if (!empty($user['pos_suspended_until'])
            && strtotime((string) $user['pos_suspended_until']) <= time()
        ) {
            return false;
        }
        return true;
    }

    /** Suspend l'accès TPE pour cet utilisateur professionnel. */
    public function suspendPos(int $userId, int $moderatorId, string $reason, ?string $until = null): bool
    {
        $stmt = $this->getPdo()->prepare(
            'UPDATE users
                SET pos_suspended_at    = ?,
                    pos_suspended_until = ?,
                    pos_suspended_by    = ?,
                    pos_suspend_reason  = ?
              WHERE id = ?'
        );
        return (bool) $stmt->execute([
            date('Y-m-d H:i:s'),
            $until,
            $moderatorId,
            mb_substr($reason, 0, 500),
            $userId,
        ]);
    }

    /** Réactive l'accès TPE pour cet utilisateur professionnel. */
    public function resumePos(int $userId, int $moderatorId): bool
    {
        $stmt = $this->getPdo()->prepare(
            "UPDATE users
                SET pos_suspended_at    = NULL,
                    pos_suspended_until = NULL,
                    pos_suspended_by    = ?,
                    pos_suspend_reason  = ''
              WHERE id = ?"
        );
        return (bool) $stmt->execute([$moderatorId, $userId]);
    }

    /**
     * Retourne les commerçants professionnels dont le TPE est actuellement
     * suspendu (hors expirations dépassées).
     */
    public function getPosSuspendedMerchants(): array
    {
        $stmt = $this->getPdo()->query(
            "SELECT id, username, email, company_name, siret,
                    pos_suspended_at, pos_suspended_until,
                    pos_suspended_by, pos_suspend_reason
               FROM users
              WHERE is_professional = 1
                AND pos_suspended_at IS NOT NULL
                AND (pos_suspended_until IS NULL OR pos_suspended_until > NOW())
              ORDER BY pos_suspended_at DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Recherche des utilisateurs professionnels par nom/email/société/SIRET.
     * Utilisé par l'autocomplétion de suspension TPE.
     */
    public function searchProfessionalUsers(string $q, int $limit = 15): array
    {
        $term = '%' . $q . '%';
        $stmt = $this->getPdo()->prepare(
            'SELECT id, username, email, company_name, siret,
                    COALESCE(company_name, username) AS display_name
               FROM users
              WHERE is_professional = 1
                AND (username LIKE ? OR email LIKE ? OR company_name LIKE ? OR siret LIKE ?)
              ORDER BY COALESCE(company_name, username) ASC
              LIMIT ' . (int) $limit
        );
        $stmt->execute([$term, $term, $term, $term]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
