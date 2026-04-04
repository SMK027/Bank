<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Account extends Model
{
    protected string $file = 'accounts.json';

    public function createAccount(int $userId, string $name, string $currency, float $overdraft = 0.0): int
    {
        return $this->create([
            'user_id'   => $userId,
            'name'      => $name,
            'currency'  => $currency,
            'overdraft' => $overdraft,
        ]);
    }

    public function getByUser(int $userId): array
    {
        return $this->findBy(['user_id' => (string) $userId]);
    }

    public function getBalance(int $accountId): float
    {
        $transactionModel = new Transaction($this->dataDir);
        $transactions = $transactionModel->findBy(['account_id' => (string) $accountId]);
        $balance = 0.0;
        foreach ($transactions as $t) {
            if ($t['type'] === 'income') {
                $balance += (float) $t['amount'];
            } else {
                $balance -= (float) $t['amount'];
            }
        }
        return $balance;
    }

    public function isOwner(int $accountId, int $userId): bool
    {
        $account = $this->find($accountId);
        return $account && (int) $account['user_id'] === $userId;
    }

    public function hasAccess(int $accountId, int $userId): bool
    {
        if ($this->isOwner($accountId, $userId)) {
            return true;
        }
        $accessModel = new AccountAccess($this->dataDir);
        return $accessModel->hasValidAccess($accountId, $userId);
    }

    public function getAccessibleAccounts(int $userId): array
    {
        $ownAccounts = $this->getByUser($userId);

        $accessModel = new AccountAccess($this->dataDir);
        $sharedAccesses = $accessModel->getValidAccessesForUser($userId);

        $sharedAccounts = [];
        foreach ($sharedAccesses as $access) {
            $account = $this->find((int) $access['account_id']);
            if ($account) {
                $account['_shared'] = true;
                $account['_access_type'] = $access['type'];
                $account['_access_expires'] = $access['expires_at'] ?? null;
                $sharedAccounts[] = $account;
            }
        }

        return ['own' => $ownAccounts, 'shared' => $sharedAccounts];
    }
}
