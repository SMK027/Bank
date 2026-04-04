<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AccountAccess extends Model
{
    protected string $file = 'accesses.json';

    public function grantAccess(int $accountId, int $userId, string $type = 'permanent', ?string $expiresAt = null): int
    {
        // Révoquer tout accès existant pour ce couple compte/utilisateur
        $existing = $this->findExisting($accountId, $userId);
        if ($existing) {
            $this->delete((int) $existing['id']);
        }

        return $this->create([
            'account_id' => $accountId,
            'user_id'    => $userId,
            'type'       => $type,
            'expires_at' => $type === 'temporary' ? $expiresAt : null,
        ]);
    }

    public function revokeAccess(int $accountId, int $userId): bool
    {
        $access = $this->findExisting($accountId, $userId);
        if ($access) {
            return $this->delete((int) $access['id']);
        }
        return false;
    }

    public function findExisting(int $accountId, int $userId): ?array
    {
        $records = $this->readAll();
        foreach ($records as $r) {
            if ((int) $r['account_id'] === $accountId && (int) $r['user_id'] === $userId) {
                return $r;
            }
        }
        return null;
    }

    public function hasValidAccess(int $accountId, int $userId): bool
    {
        $access = $this->findExisting($accountId, $userId);
        if (!$access) {
            return false;
        }
        if ($access['type'] === 'permanent') {
            return true;
        }
        if ($access['type'] === 'temporary' && !empty($access['expires_at'])) {
            return strtotime($access['expires_at']) >= time();
        }
        return false;
    }

    public function getAccessesForAccount(int $accountId): array
    {
        return $this->findBy(['account_id' => (string) $accountId]);
    }

    public function getValidAccessesForUser(int $userId): array
    {
        $accesses = $this->findBy(['user_id' => (string) $userId]);
        return array_values(array_filter($accesses, function ($a) {
            if ($a['type'] === 'permanent') {
                return true;
            }
            if ($a['type'] === 'temporary' && !empty($a['expires_at'])) {
                return strtotime($a['expires_at']) >= time();
            }
            return false;
        }));
    }
}
