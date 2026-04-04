<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $file = 'users.json';

    public function register(string $username, string $email, string $password): int
    {
        return $this->create([
            'username'    => $username,
            'email'       => $email,
            'password'    => password_hash($password, PASSWORD_BCRYPT),
            'global_role' => 'user',
        ]);
    }

    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findOneBy(['email' => $email]);
        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }
        return $user;
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
}
