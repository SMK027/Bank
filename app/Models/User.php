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
        return $this->create([
            'username'    => $username,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'global_role' => 'user',
            'birth_date'  => $birthDate,
        ]);
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
}
