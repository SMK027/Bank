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
            $shop[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'cost' => (float) $definition['cost'],
                'income_per_hour' => (float) $definition['income_per_hour'],
                'owned' => $owned,
                'total_income_per_hour' => $owned * (float) $definition['income_per_hour'],
                'next_cost' => (float) $definition['cost'],
            ];
        }

        return $shop;
    }

    public function getPassiveIncome(int $accountId): float
    {
        $total = 0.0;
        foreach ($this->getShopState($accountId) as $upgrade) {
            $total += (float) ($upgrade['total_income_per_hour'] ?? 0.0);
        }
        return round($total, 2);
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
        $totalCost = (float) $definition['cost'] * $qty;
        $balance = (float) (new Account())->getBalance($accountId);
        if ($balance < $totalCost) {
            return false;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $purchase = $this->findOneBy(['account_id' => $accountId, 'upgrade_key' => $key]);
            $newQuantity = $qty + ((int) ($purchase['quantity'] ?? 0));

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
