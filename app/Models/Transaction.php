<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Transaction extends Model
{
    protected string $file = 'transactions.json';

    public const CATEGORIES = [
        'Alimentation',
        'Transport',
        'Logement',
        'Santé',
        'Loisirs',
        'Vêtements',
        'Éducation',
        'Épargne',
        'Salaire',
        'Freelance',
        'Investissement',
        'Cadeaux',
        'Factures',
        'Autre',
    ];

    public function addTransaction(int $accountId, string $type, float $amount, string $category, string $comment = '', int $userId = 0): int
    {
        return $this->create([
            'account_id' => $accountId,
            'user_id'    => $userId,
            'type'       => $type,
            'amount'     => $amount,
            'category'   => $category,
            'comment'    => $comment,
        ]);
    }

    public function getByAccount(int $accountId, string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        return $this->findBy(['account_id' => (string) $accountId], $orderBy, $direction);
    }

    public function getTotalIncome(int $accountId): float
    {
        $transactions = $this->findBy(['account_id' => (string) $accountId]);
        $total = 0.0;
        foreach ($transactions as $t) {
            if ($t['type'] === 'income') {
                $total += (float) $t['amount'];
            }
        }
        return $total;
    }

    public function getTotalExpense(int $accountId): float
    {
        $transactions = $this->findBy(['account_id' => (string) $accountId]);
        $total = 0.0;
        foreach ($transactions as $t) {
            if ($t['type'] === 'expense') {
                $total += (float) $t['amount'];
            }
        }
        return $total;
    }
}
