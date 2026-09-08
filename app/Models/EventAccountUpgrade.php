<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Session;
use App\Core\Model;

class EventAccountUpgrade extends Model
{
    protected string $table = 'event_account_upgrades';

    public const DEVELOPER_MODE_BYPASS_KEY = 'event.tycoon_dev_mode';
    private const DEVELOPER_STATE_SESSION_KEY = 'event_tycoon_developer_state';

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
        return 0.45;
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

    public static function getLevelMultiplier(int $level): float
    {
        $level = max(1, $level);
        return round(1.0 + (($level - 1) * 0.6), 4);
    }

    public static function getUpgradeIncomeForLevel(float $incomePerHour, int $level): float
    {
        return round(self::getIncomePerMinute($incomePerHour) * self::getLevelMultiplier($level), 6);
    }

    public static function getLevelUpgradeCost(string $upgradeKey, int $currentLevel): float
    {
        if (!isset(self::UPGRADES[$upgradeKey])) {
            return 0.0;
        }

        $baseCost = (float) self::UPGRADES[$upgradeKey]['cost'];
        $level = max(1, $currentLevel);
        return round($baseCost * (1.0 + ($level * 0.85)), 2);
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

    public function isDeveloperModeActive(): bool
    {
        return Supervisor::hasBypass(self::DEVELOPER_MODE_BYPASS_KEY);
    }

    public function getDeveloperState(int $accountId): array
    {
        $state = Session::get(self::DEVELOPER_STATE_SESSION_KEY, []);
        if (!is_array($state)) {
            return [
                'active' => false,
                'revenue_multiplier' => 1.0,
                'revenue_percentage' => 0.0,
                'label' => null,
                'expires_at' => null,
                'applied_by' => null,
            ];
        }

        $entry = $state[(string) $accountId] ?? null;
        if (!is_array($entry)) {
            return [
                'active' => false,
                'revenue_multiplier' => 1.0,
                'revenue_percentage' => 0.0,
                'label' => null,
                'expires_at' => null,
                'applied_by' => null,
            ];
        }

        $expiresAt = isset($entry['expires_at']) ? (int) $entry['expires_at'] : null;
        if ($expiresAt !== null && $expiresAt > 0 && time() >= $expiresAt) {
            unset($state[(string) $accountId]);
            Session::set(self::DEVELOPER_STATE_SESSION_KEY, $state);
            return [
                'active' => false,
                'revenue_multiplier' => 1.0,
                'revenue_percentage' => 0.0,
                'label' => null,
                'expires_at' => null,
                'applied_by' => null,
            ];
        }

        $multiplier = max(0.0, (float) ($entry['revenue_multiplier'] ?? 1.0));

        return [
            'active' => $this->isDeveloperModeActive(),
            'revenue_multiplier' => round($multiplier, 4),
            'revenue_percentage' => round((($multiplier - 1.0) * 100.0), 2),
            'label' => (string) ($entry['label'] ?? ''),
            'expires_at' => $expiresAt,
            'applied_by' => isset($entry['applied_by']) ? (int) $entry['applied_by'] : null,
        ];
    }

    public function clearDeveloperState(int $accountId): void
    {
        $state = Session::get(self::DEVELOPER_STATE_SESSION_KEY, []);
        if (!is_array($state)) {
            return;
        }

        unset($state[(string) $accountId]);
        Session::set(self::DEVELOPER_STATE_SESSION_KEY, $state);
    }

    public function setDeveloperRevenueMultiplier(int $accountId, float $percentage, int $durationValue, string $durationUnit, ?int $supervisorDbId = null): bool
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? '')) || !$this->isDeveloperModeActive()) {
            return false;
        }

        $durationValue = max(1, $durationValue);
        $seconds = match (strtolower(trim($durationUnit))) {
            'minute', 'minutes', 'min', 'mins' => $durationValue * 60,
            'hour', 'hours', 'h' => $durationValue * 3600,
            'day', 'days', 'j', 'jour', 'jours' => $durationValue * 86400,
            default => 0,
        };

        if ($seconds <= 0) {
            return false;
        }

        $multiplier = max(0.0, round(1.0 + ($percentage / 100.0), 4));
        $expiresAt = time() + $seconds;
        $state = Session::get(self::DEVELOPER_STATE_SESSION_KEY, []);
        if (!is_array($state)) {
            $state = [];
        }

        $state[(string) $accountId] = [
            'revenue_multiplier' => $multiplier,
            'label' => sprintf('%+.2f%% pendant %d %s', $percentage, $durationValue, $durationUnit),
            'expires_at' => $expiresAt,
            'applied_by' => $supervisorDbId,
            'created_at' => time(),
        ];

        Session::set(self::DEVELOPER_STATE_SESSION_KEY, $state);
        return true;
    }

    public function setDeveloperOverdraftLimit(int $accountId, float $limit): bool
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? '')) || !$this->isDeveloperModeActive()) {
            return false;
        }

        $limit = max(0.0, round($limit, 2));
        (new Account())->update($accountId, ['event_overdraft_limit' => $limit]);
        return true;
    }

    public function getEventOverdraftLimit(int $accountId): float
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return 0.0;
        }

        $limit = (float) ($account['event_overdraft_limit'] ?? 0.0);

        return max(0.0, $limit);
    }

    public function getEventOverdraftUnlockCost(): float
    {
        return 2000.0;
    }

    public function getEventOverdraftUpgradeCost(float $currentLimit): float
    {
        $base = 300.0 + ($currentLimit * 0.18);
        return round(max(300.0, $base), 2);
    }

    public function unlockEventOverdraft(int $accountId): bool
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return false;
        }

        if ((float) ($account['event_overdraft_limit'] ?? 0.0) > 0.0) {
            return true;
        }

        if ($this->isDeveloperModeActive()) {
            (new Account())->update($accountId, ['event_overdraft_limit' => 200.0]);
            return true;
        }

        $balance = (new Account())->getBalance($accountId);
        $unlockCost = $this->getEventOverdraftUnlockCost();
        if ($balance < $unlockCost) {
            return false;
        }

        (new Account())->update($accountId, ['event_overdraft_limit' => 200.0]);

        $tx = new \App\Models\Transaction();
        $tx->addTransaction(
            $accountId,
            'expense',
            $unlockCost,
            'Découvert',
            'Activation de l’autorisation de découvert événementiel',
            0
        );

        return true;
    }

    public function upgradeEventOverdraftLimit(int $accountId, float $steps = 1.0): bool
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return false;
        }

        $currentLimit = $this->getEventOverdraftLimit($accountId);
        if ($currentLimit <= 0.0 && !$this->isDeveloperModeActive()) {
            return false;
        }

        if ($currentLimit <= 0.0 && $this->isDeveloperModeActive()) {
            $currentLimit = 200.0;
        }

        $steps = max(1.0, min(10.0, $steps));
        $targetLimit = $currentLimit + (200.0 * $steps);
        if (!$this->isDeveloperModeActive()) {
            $targetLimit = min(15000.0, $targetLimit);
        }

        $cost = $this->isDeveloperModeActive() ? 0.0 : ($this->getEventOverdraftUpgradeCost($currentLimit) * $steps);

        if (!$this->isDeveloperModeActive()) {
            $balance = (new Account())->getBalance($accountId);
            if ($balance < $cost) {
                return false;
            }
        }

        (new Account())->update($accountId, ['event_overdraft_limit' => $targetLimit]);

        if ($cost > 0.0) {
            $tx = new \App\Models\Transaction();
            $tx->addTransaction(
                $accountId,
                'expense',
                round($cost, 2),
                'Découvert',
                'Amélioration du découvert événementiel',
                0
            );
        }

        return true;
    }

    public function getIncomeReductionFromOverdraft(int $accountId): float
    {
        $limit = $this->getEventOverdraftLimit($accountId);
        if ($limit <= 0.0) {
            return 0.0;
        }

        $balance = (new Account())->getBalance($accountId);
        if ($balance >= 0.0) {
            return 0.0;
        }

        $debt = min(abs($balance), $limit);
        $ratio = $debt / $limit;
        $reduction = 0.10 + ($ratio * 0.15);

        return round(min(0.25, max(0.10, $reduction)), 4);
    }

    public function getShopState(int $accountId): array
    {
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return [];
        }

        $rows = $this->findBy(['account_id' => $accountId]);
        $ownedByKey = [];
        $levelByKey = [];
        foreach ($rows as $row) {
            $key = (string) ($row['upgrade_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $ownedByKey[$key] = (int) ($row['quantity'] ?? 0);
            $levelByKey[$key] = max(1, (int) ($row['level'] ?? 1));
        }

        $shop = [];
        foreach (self::UPGRADES as $key => $definition) {
            $owned = (int) ($ownedByKey[$key] ?? 0);
            $level = (int) ($levelByKey[$key] ?? 1);
            $baseCost = (float) $definition['cost'];
            $incomePerHour = (float) $definition['income_per_hour'];
            $incomePerMinute = self::getUpgradeIncomeForLevel($incomePerHour, $level);
            $nextCost = self::getCostForNextUnit($baseCost, $owned);
            $nextLevelCost = self::getLevelUpgradeCost($key, $level);
            $shop[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'cost' => $nextCost,
                'level' => $level,
                'next_level_cost' => $nextLevelCost,
                'income_per_hour' => $incomePerHour,
                'income_per_minute' => $incomePerMinute,
                'owned' => $owned,
                'total_income_per_hour' => $owned * ($incomePerHour * self::getLevelMultiplier($level)),
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

    public function getLeaderboard(int $limit = 10): array
    {
        $accounts = (new Account())->findBy(['type' => 'event']);
        $rows = [];

        foreach ($accounts as $account) {
            $accountId = (int) ($account['id'] ?? 0);
            if ($accountId <= 0) {
                continue;
            }

            $summary = $this->getEventEconomySummary($accountId);
            $rows[] = [
                'account_id' => $accountId,
                'name' => (string) ($account['name'] ?? 'Compte événementiel'),
                'user_id' => (int) ($account['user_id'] ?? 0),
                'event_title' => (string) ($account['event_title'] ?? $account['name'] ?? 'Événement'),
                'income_per_minute' => (float) ($summary['total_income_per_minute'] ?? 0.0),
                'income_per_hour' => (float) ($summary['total_income_per_hour'] ?? 0.0),
                'prestige_tier' => (string) ($summary['prestige_tier'] ?? 'Démarrage'),
                'owned_upgrades_count' => (int) ($summary['owned_upgrades_count'] ?? 0),
                'total_spent' => (float) ($summary['total_spent'] ?? 0.0),
            ];
        }

        usort(
            $rows,
            static fn(array $a, array $b): int =>
                ($b['income_per_minute'] <=> $a['income_per_minute'])
                ?: ($b['total_spent'] <=> $a['total_spent'])
                ?: ($b['owned_upgrades_count'] <=> $a['owned_upgrades_count'])
        );

        $ranked = [];
        foreach ($rows as $index => $row) {
            $ranked[] = [
                'rank' => $index + 1,
                'account_id' => $row['account_id'],
                'name' => $row['name'],
                'user_id' => $row['user_id'],
                'event_title' => $row['event_title'],
                'income_per_minute' => round($row['income_per_minute'], 4),
                'income_per_hour' => round($row['income_per_hour'], 2),
                'prestige_tier' => $row['prestige_tier'],
                'owned_upgrades_count' => $row['owned_upgrades_count'],
                'total_spent' => round($row['total_spent'], 2),
            ];
        }

        return array_slice($ranked, 0, max(1, (int) $limit));
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
        $total = 25.0;
        foreach ($this->getShopState($accountId) as $upgrade) {
            $total += (float) ($upgrade['total_income_per_minute'] ?? 0.0);
        }

        $devState = $this->getDeveloperState($accountId);
        if (($devState['active'] ?? false) && (float) ($devState['revenue_multiplier'] ?? 1.0) !== 1.0) {
            $total *= (float) $devState['revenue_multiplier'];
        }

        $reduction = $this->getIncomeReductionFromOverdraft($accountId);
        if ($reduction > 0.0) {
            $total *= (1.0 - $reduction);
        }

        return round(max(25.0, $total), 4);
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

    public function getUpgradeLevelFailureReason(int $accountId, string $key, int $levelCount = 1): ?string
    {
        if (!isset(self::UPGRADES[$key])) {
            return 'Amélioration inconnue.';
        }

        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return 'Ce compte n’est pas un compte événementiel.';
        }

        if ($this->isPassiveIncomePaused($accountId)) {
            return 'Le versement automatique est suspendu. Réactivez-le avant d’améliorer une installation.';
        }

        $purchase = $this->findOneBy(['account_id' => $accountId, 'upgrade_key' => $key]);
        $currentLevel = (int) ($purchase['level'] ?? 1);
        $cost = 0.0;
        $levelCount = max(1, (int) $levelCount);
        for ($i = 0; $i < $levelCount; $i++) {
            $cost += self::getLevelUpgradeCost($key, $currentLevel + $i);
        }

        $balance = (float) (new Account())->getBalance($accountId);
        $overdraftLimit = $this->getEventOverdraftLimit($accountId);
        if (($balance - $cost) < -$overdraftLimit) {
            if (!$this->isDeveloperModeActive()) {
                return sprintf(
                    'Fonds insuffisants : solde %.2f €, coût total %.2f €, découvert autorisé %.2f €.',
                    $balance,
                    $cost,
                    $overdraftLimit
                );
            }
        }

        return null;
    }

    public function upgradeLevel(int $accountId, string $key, int $levelCount = 1): bool
    {
        if (!isset(self::UPGRADES[$key])) {
            return false;
        }

        $levelCount = max(1, (int) $levelCount);
        $account = (new Account())->find($accountId);
        if (!$account || !Account::isEventType((string) ($account['type'] ?? ''))) {
            return false;
        }

        if ($this->isPassiveIncomePaused($accountId) && !$this->isDeveloperModeActive()) {
            return false;
        }

        $purchase = $this->findOneBy(['account_id' => $accountId, 'upgrade_key' => $key]);
        $currentLevel = (int) ($purchase['level'] ?? 1);
        $totalCost = 0.0;
        for ($i = 0; $i < $levelCount; $i++) {
            $totalCost += self::getLevelUpgradeCost($key, $currentLevel + $i);
        }

        if (!$this->isDeveloperModeActive()) {
            $balance = (float) (new Account())->getBalance($accountId);
            $overdraftLimit = $this->getEventOverdraftLimit($accountId);
            if (($balance - $totalCost) < -$overdraftLimit) {
                return false;
            }
        } else {
            $totalCost = 0.0;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $newLevel = $currentLevel + $levelCount;
            if ($purchase) {
                $this->update((int) $purchase['id'], ['level' => $newLevel]);
            } else {
                $this->create([
                    'account_id' => $accountId,
                    'upgrade_key' => $key,
                    'quantity' => 0,
                    'level' => $newLevel,
                ]);
            }

            if ($totalCost > 0.0) {
                (new Transaction())->create([
                    'account_id' => $accountId,
                    'user_id' => (int) $account['user_id'],
                    'type' => 'expense',
                    'amount' => $totalCost,
                    'category' => 'Équipement événementiel',
                    'comment' => 'Amélioration de niveau : ' . self::UPGRADES[$key]['label'] . ($levelCount > 1 ? ' x' . $levelCount : ''),
                    'scheduled_at' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
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

        if ($this->isPassiveIncomePaused($accountId) && !$this->isDeveloperModeActive()) {
            return false;
        }

        $qty = max(1, (int) $quantity);
        $definition = self::UPGRADES[$key];
        $purchase = $this->findOneBy(['account_id' => $accountId, 'upgrade_key' => $key]);
        $ownedBefore = (int) ($purchase['quantity'] ?? 0);
        $currentLevel = (int) ($purchase['level'] ?? 1);
        $totalCost = self::getTotalCostForQuantity((float) $definition['cost'], $ownedBefore, $qty);
        if (!$this->isDeveloperModeActive()) {
            $balance = (float) (new Account())->getBalance($accountId);
            $overdraftLimit = $this->getEventOverdraftLimit($accountId);
            if (($balance - $totalCost) < -$overdraftLimit) {
                return false;
            }
        } else {
            $totalCost = 0.0;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $newQuantity = $qty + $ownedBefore;

            if ($purchase) {
                $this->update((int) $purchase['id'], [
                    'quantity' => $newQuantity,
                    'level' => max(1, $currentLevel),
                ]);
            } else {
                $this->create([
                    'account_id' => $accountId,
                    'upgrade_key' => $key,
                    'quantity' => $newQuantity,
                    'level' => 1,
                ]);
            }

            if ($totalCost > 0.0) {
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
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
