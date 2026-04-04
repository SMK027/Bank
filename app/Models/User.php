<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle User — exemple de modèle concret.
 * À adapter selon les besoins du projet.
 */
class User extends Model
{
    protected string $table = 'users';

    /**
     * Inscription d'un nouvel utilisateur.
     */
    public function register(string $username, string $email, string $password): int
    {
        return $this->create([
            'username'    => $username,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'global_role' => 'user',
        ]);
    }

    /**
     * Authentification par email et mot de passe.
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findOneBy(['email' => $email]);
        if (!$user) {
            return null;
        }
        if (!password_verify($password, $user['password'])) {
            return null;
        }
        return $user;
    }

    /**
     * Recherche un utilisateur par nom d'utilisateur.
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findOneBy(['username' => $username]);
    }

    /**
     * Recherche un utilisateur par email.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Met à jour le mot de passe d'un utilisateur.
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        return $this->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);
    }

    /**
     * Met à jour le rôle global d'un utilisateur.
     */
    public function updateGlobalRole(int $userId, string $role): bool
    {
        return $this->update($userId, ['global_role' => $role]);
    }
}
