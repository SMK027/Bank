<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class EventAccountUpgrade extends Model
{
    protected string $table = 'event_account_upgrades';

    public const UPGRADES = [
        'ticket_booth' => [
            'label' => 'Stand de billets',
            'cost' => 80.0,
            'income_per_hour' => 12.0,
            'description' => 'Un stand simple mais efficace pour vendre plus de billets sur place.',
        ],
        'food_stall' => [
            'label' => 'Food truck',
            'cost' => 180.0,
            'income_per_hour' => 30.0,
            'description' => 'Le public dépense davantage avec des options de restauration premium.',
        ],
        'merchandising' => [
            'label' => 'Boutique merch',
            'cost' => 320.0,
            'income_per_hour' => 58.0,
            'description' => 'Des articles à la vente augmentent fortement le panier moyen du public.',
        ],
        'vip_zone' => [
            'label' => 'Zone VIP',
            'cost' => 520.0,
            'income_per_hour' => 110.0,
            'description' => 'Des offres premium et un accueil exclusif maximisent les revenus.',
        ],
    ];

    public static function getDefinitions(): array
    {
        return self::UPGRADES;
    }

    public static function getPriceGrowth(): float
    {
        return 0.65;
    }

    public static function getCostForNextUnit(float $baseCost, int $ownedQuantity): float
    {
        return round($baseCost * (1.0 + ($ownedQuantity * self::getPriceGrowth())), 2);
    }

    public static function getTotalCostForQuantity(float $baseCost, int $ownedQuantity, int $quantity): float
    {
        $total = 0.0;
        for ($i = 0; $i < $quantity; $i++) {
            $total += self::getCostForNextUnit($baseCost, $ownedQuantity + $i);
        }
        return round($total, 2);
    }

    public static function getIncomePerMinute(float $incomePerHour): float
    {
        return round($incomePerHour / 60.0, 6);
    }

    public function getShopState(int $accountId): array
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return [];
        }

        $rows = $this->findBy(['account_id' => $accountId]);
        $ownedByKey = [];
        foreach ($rows as $row) {
            $ownedByKey[(string) $row['upgrade_key']] = (int) ($row['quantity'] ?? 0);
        }

        $shop = [];
        foreach (self::UPGRADES as $key => $definition) {
            $owned = (int) ($ownedByKey[$key] ?? 0);
            $baseCost = (float) $definition['cost'];
            $incomePerHour = (float) $definition['income_per_hour'];
            $incomePerMinute = self::getIncomePerMinute($incomePerHour);
            $nextCost = self::getCostForNextUnit($baseCost, $owned);
            $shop[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'cost' => $nextCost,
                'income_per_hour' => $incomePerHour,
                'income_per_minute' => $incomePerMinute,
                'owned' => $owned,
                'total_income_per_hour' => $owned * $incomePerHour,
                'total_income_per_minute' => $owned * $incomePerMinute,
                'next_cost' => $nextCost,
            ];
        }

        return $shop;
    }

    public function getPassiveIncome(int $accountId): float
    {
        $total = 0.0;
        foreach ($this->getShopState($accountId) as $upgrade) {
            $total += (float) ($upgrade['total_income_per_minute'] ?? 0.0);
        }
        return round($total, 4);
    }

    public function creditPassiveIncomeForAccount(int $accountId): float
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return 0.0;
        }

        if (!Account::isOperationalNow($account)) {
            return 0.0;
        }

        $amount = $this->getPassiveIncome($accountId);
        if ($amount <= 0.0) {
            return 0.0;
        }

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            "SELECT created_at
               FROM transactions
               WHERE account_id = ?
                 AND type = 'income'
                 AND category = 'Revenu passif'
               ORDER BY created_at DESC
               LIMIT 1"
        );
        $stmt->execute([$accountId]);
        $lastCredit = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($lastCredit && !empty($lastCredit['created_at'])) {
            $lastTimestamp = strtotime((string) $lastCredit['created_at']);
            if ($lastTimestamp !== false && (time() - $lastTimestamp) < 60) {
                return 0.0;
            }
        }

        $tx = new \App\Models\Transaction();
        $tx->addTransaction(
            $accountId,
            'income',
            round($amount, 2),
            'Revenu passif',
            'Versement automatique — revenu passif (1 min)',
            0
        );

        return round($amount, 2);
    }

    public function processAllPassiveIncome(): int
    {
        $count = 0;
        $accounts = (new Account())->findBy(['type' => 'event']);
        foreach ($accounts as $account) {
            $accountId = (int) ($account['id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            if ($this->creditPassiveIncomeForAccount($accountId) > 0.0) {
                $count++;
            }
        }

        return $count;
    }

    public function buyUpgrade(int $accountId, string $key, int $quantity = 1): bool
    {
        if (!isset(self::UPGRADES[$key])) {
            return false;
        }

        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return false;
        }

        $qty = max(1, (int) $quantity);
        $definition = self::UPGRADES[$key];
        $purchase = $this->findOneBy(['account_id' => $accountId, 'upgrade_key' => $key]);
        $ownedBefore = (int) ($purchase['quantity'] ?? 0);
        $totalCost = self::getTotalCostForQuantity((float) $definition['cost'], $ownedBefore, $qty);
        $balance = (float) (new Account())->getBalance($accountId);
        if ($balance < $totalCost) {
            return false;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $newQuantity = $qty + $ownedBefore;

            if ($purchase) {
                $this->update((int) $purchase['id'], ['quantity' => $newQuantity]);
            } else {
                $this->create([
                    'account_id' => $accountId,
                    'upgrade_key' => $key,
                    'quantity' => $newQuantity,
                ]);
            }

            (new Transaction())->create([
                'account_id' => $accountId,
                'user_id' => (int) $account['user_id'],
                'type' => 'expense',
                'amount' => $totalCost,
                'category' => 'Équipement événementiel',
                'comment' => 'Achat : ' . $definition['label'] . ($qty > 1 ? ' x' . $qty : ''),
                'scheduled_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
