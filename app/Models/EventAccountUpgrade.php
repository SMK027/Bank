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
            'income_per_hour' => 30.0,
            'description' => 'Un stand simple mais efficace pour vendre plus de billets sur place.',
        ],
        'food_stall' => [
            'label' => 'Food truck',
            'cost' => 180.0,
            'income_per_hour' => 90.0,
            'description' => 'Le public dépense davantage avec des options de restauration premium.',
        ],
        'merchandising' => [
            'label' => 'Boutique merch',
            'cost' => 320.0,
            'income_per_hour' => 210.0,
            'description' => 'Des articles à la vente augmentent fortement le panier moyen du public.',
        ],
        'vip_zone' => [
            'label' => 'Zone VIP',
            'cost' => 520.0,
            'income_per_hour' => 420.0,
            'description' => 'Des offres premium et un accueil exclusif maximisent les revenus.',
        ],
        'sound_system' => [
            'label' => 'Sono et éclairage live',
            'cost' => 780.0,
            'income_per_hour' => 620.0,
            'description' => 'Une scène bien équipée favorise les retours, les artistes et les ventes de boissons.',
        ],
        'sponsor_wall' => [
            'label' => 'Mur de sponsors',
            'cost' => 1100.0,
            'income_per_hour' => 930.0,
            'description' => 'Des partenariats crédibles ajoutent des ressources et une visibilité massive.',
        ],
        'main_stage' => [
            'label' => 'Scène principale',
            'cost' => 1500.0,
            'income_per_hour' => 1380.0,
            'description' => 'Un grand espace de concert attire plus de public et transforme l’événement en attraction.',
        ],
        'food_court' => [
            'label' => 'Cour de restauration',
            'cost' => 1900.0,
            'income_per_hour' => 1820.0,
            'description' => 'Une zone gastronomie complète multiplie les achats et la durée de présence sur site.',
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

    public static function getTotalSpentForOwned(float $baseCost, int $ownedQuantity): float
    {
        $total = 0.0;
        for ($i = 0; $i < $ownedQuantity; $i++) {
            $total += self::getCostForNextUnit($baseCost, $i);
        }

        return round($total, 2);
    }

    public static function getPrestigeTier(float $totalIncomePerMinute): string
    {
        if ($totalIncomePerMinute >= 40.0) {
            return 'Événement légendaire';
        }
        if ($totalIncomePerMinute >= 20.0) {
            return 'Événement premium';
        }
        if ($totalIncomePerMinute >= 8.0) {
            return 'Événement populaire';
        }
        if ($totalIncomePerMinute >= 2.0) {
            return 'Bons débuts';
        }

        return 'Démarrage';
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
                'total_spent' => self::getTotalSpentForOwned($baseCost, $owned),
            ];
        }

        return $shop;
    }

    public function getEventEconomySummary(int $accountId): array
    {
        $shop = $this->getShopState($accountId);
        $totalIncomePerMinute = 0.0;
        $ownedUpgradesCount = 0;
        $totalSpent = 0.0;
        $nextUpgrade = null;

        foreach ($shop as $upgrade) {
            $totalIncomePerMinute += (float) ($upgrade['total_income_per_minute'] ?? 0.0);
            $ownedUpgradesCount += (int) ($upgrade['owned'] ?? 0);
            $totalSpent += (float) ($upgrade['total_spent'] ?? 0.0);

            if ($nextUpgrade === null && ((int) ($upgrade['owned'] ?? 0)) < 3) {
                $nextUpgrade = $upgrade;
            }
        }

        if ($nextUpgrade === null) {
            $nextUpgrade = end($shop) ?: null;
        }

        return [
            'total_income_per_minute' => round($totalIncomePerMinute, 4),
            'total_income_per_hour' => round($totalIncomePerMinute * 60.0, 2),
            'owned_upgrades_count' => $ownedUpgradesCount,
            'total_spent' => round($totalSpent, 2),
            'prestige_tier' => self::getPrestigeTier($totalIncomePerMinute),
            'next_upgrade_key' => $nextUpgrade['key'] ?? null,
            'next_upgrade_label' => $nextUpgrade['label'] ?? null,
            'next_upgrade_cost' => (float) ($nextUpgrade['next_cost'] ?? 0.0),
        ];
    }

    public function isPassiveIncomePaused(int $accountId): bool
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return false;
        }

        return !empty($account['passive_income_paused_at']);
    }

    public function pausePassiveIncome(int $accountId): bool
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return false;
        }

        if (!empty($account['passive_income_paused_at'])) {
            return true;
        }

        (new Account())->update($accountId, [
            'passive_income_paused_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function resumePassiveIncome(int $accountId): float
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return 0.0;
        }

        if (empty($account['passive_income_paused_at'])) {
            return 0.0;
        }

        $pausedAt = strtotime((string) $account['passive_income_paused_at']);
        $now = time();
        $pausedSeconds = max(0, $now - $pausedAt);
        $perMinute = $this->getPassiveIncome($accountId);
        $compensation = round(($pausedSeconds / 60.0) * $perMinute, 2);

        (new Account())->update($accountId, [
            'passive_income_paused_at' => null,
            'passive_income_last_reactivated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($compensation > 0.0) {
            $tx = new \App\Models\Transaction();
            $tx->addTransaction(
                $accountId,
                'income',
                $compensation,
                'Revenu passif',
                'Réactivation du versement automatique — compensation cumulée',
                0
            );
        }

        return $compensation;
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

        if (!Account::isOperationalNow($account) || $this->isPassiveIncomePaused($accountId)) {
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

        if ($this->isPassiveIncomePaused($accountId)) {
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
