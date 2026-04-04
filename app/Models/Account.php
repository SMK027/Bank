<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Account extends Model
{
    protected string $file = 'accounts.json';

    /**
     * Types de comptes : label + droit au découvert.
     */
    public const TYPES = [
        'standard' => ['label' => 'Compte courant',       'overdraft' => true],
        'pro'      => ['label' => 'Compte professionnel', 'overdraft' => true],
        'joint'    => ['label' => 'Compte joint',         'overdraft' => true],
        'savings'  => ['label' => 'Compte épargne',       'overdraft' => false],
        'online'   => ['label' => 'Banque en ligne',      'overdraft' => false],
        'minor'    => ['label' => 'Compte mineur',        'overdraft' => false],
    ];

    public static function typeAllowsOverdraft(string $type): bool
    {
        return self::TYPES[$type]['overdraft'] ?? true;
    }

    public function createAccount(int $userId, string $name, string $currency, float $overdraft = 0.0, string $type = 'standard'): int
    {
        if (!self::typeAllowsOverdraft($type)) {
            $overdraft = 0.0;
        }
        return $this->create([
            'user_id'   => $userId,
            'name'      => $name,
            'currency'  => $currency,
            'overdraft' => $overdraft,
            'type'      => $type,
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
            if (Transaction::isPending($t)) {
                continue;
            }
            if ($t['type'] === 'income') {
                $balance += (float) $t['amount'];
            } else {
                $balance -= (float) $t['amount'];
            }
        }
        return $balance;
    }

    public function getFutureBalance(int $accountId): float
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

    public function isFrozen(int $accountId): bool
    {
        $account = $this->find($accountId);
        return $account !== null && !empty($account['frozen']);
    }

    public function freezeAccount(int $accountId): bool
    {
        return $this->update($accountId, ['frozen' => true]);
    }

    public function unfreezeAccount(int $accountId): bool
    {
        return $this->update($accountId, ['frozen' => false]);
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
