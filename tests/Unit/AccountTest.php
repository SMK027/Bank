<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\AccountAccess;
use App\Models\EventAccountUpgrade;
use App\Models\PaymentCard;
use App\Models\Checkbook;
use App\Models\User;
use Tests\TestDatabase;

class AccountTest extends TestCase
{
    private Account $account;
    private User $user;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->account = new Account();
        $this->user    = new User();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testCreateAccount(): void
    {
        $id = $this->account->createAccount(1, 'Compte courant', 'EUR', 500.0);
        $this->assertGreaterThan(0, $id);

        $found = $this->account->find($id);
        $this->assertSame('Compte courant', $found['name']);
        $this->assertSame('EUR', $found['currency']);
        $this->assertEquals(500.0, $found['overdraft']);
        $this->assertSame(1, $found['user_id']);
    }

    public function testGetByUser(): void
    {
        $this->account->createAccount(1, 'Compte A', 'EUR');
        $this->account->createAccount(1, 'Compte B', 'USD');
        $this->account->createAccount(2, 'Compte C', 'GBP');

        $user1Accounts = $this->account->getByUser(1);
        $this->assertCount(2, $user1Accounts);

        $user2Accounts = $this->account->getByUser(2);
        $this->assertCount(1, $user2Accounts);
    }

    public function testGetBalanceWithNoTransactions(): void
    {
        $id = $this->account->createAccount(1, 'Vide', 'EUR');
        $balance = $this->account->getBalance($id);
        $this->assertSame(0.0, $balance);
    }

    public function testGetBalanceWithTransactions(): void
    {
        $accId = $this->account->createAccount(1, 'Test', 'EUR');
        $tx = new Transaction();

        $tx->addTransaction($accId, 'income', 1000.0, 'Salaire', 'Janvier');
        $tx->addTransaction($accId, 'expense', 200.0, 'Alimentation', 'Courses');
        $tx->addTransaction($accId, 'expense', 50.0, 'Transport', 'Essence');

        $balance = $this->account->getBalance($accId);
        $this->assertSame(750.0, $balance);
    }

    public function testIsOwner(): void
    {
        $id = $this->account->createAccount(1, 'Mon compte', 'EUR');

        $this->assertTrue($this->account->isOwner($id, 1));
        $this->assertFalse($this->account->isOwner($id, 2));
    }

    public function testHasAccessOwner(): void
    {
        $id = $this->account->createAccount(1, 'Mon compte', 'EUR');
        $this->assertTrue($this->account->hasAccess($id, 1));
    }

    public function testHasAccessShared(): void
    {
        $accId = $this->account->createAccount(1, 'Partagé', 'EUR');
        $access = new AccountAccess();

        $this->assertFalse($this->account->hasAccess($accId, 2));

        $access->grantAccess($accId, 2, 'permanent');
        $this->assertTrue($this->account->hasAccess($accId, 2));
    }

    public function testGetAccessibleAccounts(): void
    {
        $this->account->createAccount(1, 'Compte 1', 'EUR');
        $acc2 = $this->account->createAccount(2, 'Compte 2', 'USD');

        $access = new AccountAccess();
        $access->grantAccess($acc2, 1, 'permanent');

        $result = $this->account->getAccessibleAccounts(1);
        $this->assertCount(1, $result['own']);
        $this->assertCount(1, $result['shared']);
        $this->assertTrue($result['shared'][0]['_shared']);
    }

    public function testNegativeBalance(): void
    {
        $accId = $this->account->createAccount(1, 'Découvert', 'EUR', 500.0);
        $tx = new Transaction();

        $tx->addTransaction($accId, 'expense', 300.0, 'Factures', 'Loyer');
        $balance = $this->account->getBalance($accId);
        $this->assertSame(-300.0, $balance);
    }

    public function testFreezeAccount(): void
    {
        $id = $this->account->createAccount(1, 'Compte gelé', 'EUR');
        $this->assertFalse($this->account->isFrozen($id));

        $this->account->freezeAccount($id);
        $this->assertTrue($this->account->isFrozen($id));
    }

    public function testUnfreezeAccount(): void
    {
        $id = $this->account->createAccount(1, 'Compte', 'EUR');
        $this->account->freezeAccount($id);
        $this->assertTrue($this->account->isFrozen($id));

        $this->account->unfreezeAccount($id);
        $this->assertFalse($this->account->isFrozen($id));
    }

    public function testIsFrozenReturnsFalseForNewAccount(): void
    {
        $id = $this->account->createAccount(1, 'Nouveau', 'EUR');
        $this->assertFalse($this->account->isFrozen($id));
    }

    public function testTransferOwnershipWithCardsAndCheckbooks(): void
    {
        $accId = $this->account->createAccount(1, 'Compte Transféré', 'EUR');

        // Création carte et chéquier raccordés au compte pour l'utilisateur 1
        $cardModel = new PaymentCard();
        $cardId = $cardModel->create([
            'user_id' => 1,
            'account_id' => $accId,
            'card_number' => '4242123412341234',
            'last4' => '1234',
            'label' => 'Carte 1',
        ]);

        $checkbookModel = new Checkbook();
        $checkbookId = $checkbookModel->create([
            'user_id' => 1,
            'account_id' => $accId,
            'status' => 'active',
        ]);

        // Ajout d'un accès partagé préalable pour l'utilisateur 2
        $accessModel = new AccountAccess();
        $accessModel->grantAccess($accId, 2, 'permanent');
        $this->assertTrue($this->account->hasAccess($accId, 2));

        // Transfert du compte de l'utilisateur 1 à l'utilisateur 2
        $success = $this->account->transferOwnership($accId, 2);
        $this->assertTrue($success);

        // Vérification compte
        $updatedAccount = $this->account->find($accId);
        $this->assertEquals(2, $updatedAccount['user_id']);
        $this->assertTrue($this->account->isOwner($accId, 2));
        $this->assertFalse($this->account->isOwner($accId, 1));

        // Vérification carte
        $updatedCard = $cardModel->find($cardId);
        $this->assertEquals(2, $updatedCard['user_id']);

        // Vérification chéquier
        $updatedCheckbook = $checkbookModel->find($checkbookId);
        $this->assertEquals(2, $updatedCheckbook['user_id']);

        // L'accès partagé de l'utilisateur 2 a été nettoyé
        $this->assertFalse($accessModel->hasValidAccess($accId, 2));
    }

    // --- Tests allowed types avec statut professionnel ---

    public function testAllowedTypesAdultNonPro(): void
    {
        $types = Account::getAllowedTypes(false, false);
        $this->assertArrayNotHasKey('pro', $types);
        $this->assertArrayNotHasKey('minor', $types);
        $this->assertArrayHasKey('standard', $types);
        $this->assertArrayHasKey('joint', $types);
        $this->assertArrayHasKey('savings', $types);
        $this->assertArrayHasKey('online', $types);
    }

    public function testAllowedTypesAdultPro(): void
    {
        $types = Account::getAllowedTypes(false, true);
        $keys = array_keys($types);
        $this->assertSame(['pro', 'vault', 'savings', 'event'], $keys);
        $this->assertArrayNotHasKey('standard', $types);
        $this->assertArrayNotHasKey('joint', $types);
        $this->assertArrayNotHasKey('online', $types);
        $this->assertArrayNotHasKey('minor', $types);
    }

    public function testAllowedTypesMinorIgnoresPro(): void
    {
        $types = Account::getAllowedTypes(true, true);
        $this->assertArrayNotHasKey('pro', $types);
        $this->assertArrayHasKey('savings', $types);
    }

    public function testEventAccountsDoNotAllowCardsManualOperationsOrBudgeting(): void
    {
        $this->assertFalse(Account::typeAllowsCard('event'));
        $this->assertFalse(Account::manualOperationsAllowed('event'));
        $this->assertFalse(Account::typeAllowsBudget('event'));
    }

    public function testEventAccountStartsWithStarterCashAndUpgradeIncome(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $this->assertEquals(500.0, $this->account->getBalance($id));

        $upgradeModel = new EventAccountUpgrade();
        $this->assertEqualsWithDelta(25.0, $upgradeModel->getPassiveIncome($id), 0.001);

        $initialCost = $upgradeModel->getShopState($id)['ticket_booth']['cost'];
        $this->assertEqualsWithDelta(80.0, $initialCost, 0.001);

        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 1));
        $this->assertGreaterThan(25.0, $upgradeModel->getPassiveIncome($id));
        $this->assertEqualsWithDelta(25.5, $upgradeModel->getPassiveIncome($id), 0.001);

        $nextCost = $upgradeModel->getShopState($id)['ticket_booth']['cost'];
        $this->assertGreaterThan($initialCost, $nextCost);
    }

    public function testEventPassiveIncomeCanBePausedAndResumedWithCompensation(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 1));
        $this->assertFalse($upgradeModel->isPassiveIncomePaused($id));

        $this->assertTrue($upgradeModel->pausePassiveIncome($id));
        $this->assertTrue($upgradeModel->isPassiveIncomePaused($id));
        $this->assertFalse($upgradeModel->buyUpgrade($id, 'food_stall', 1));

        $compensation = $upgradeModel->resumePassiveIncome($id);
        $this->assertGreaterThanOrEqual(0.0, $compensation);
        $this->assertFalse($upgradeModel->isPassiveIncomePaused($id));
    }

    public function testEventAlwaysHasMinimumPassiveIncome(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertEqualsWithDelta(25.0, $upgradeModel->getPassiveIncome($id), 0.001);
    }

    public function testEventUpgradeLevelAffectsIncomeAndCosts(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 2));

        $shop = $upgradeModel->getShopState($id);
        $this->assertSame(1, (int) $shop['ticket_booth']['level']);
        $this->assertEqualsWithDelta(1.0, (float) $shop['ticket_booth']['total_income_per_minute'], 0.001);
        $this->assertGreaterThan(0.0, (float) $shop['ticket_booth']['next_level_cost']);

        $this->assertTrue($upgradeModel->upgradeLevel($id, 'ticket_booth'));
        $updated = $upgradeModel->getShopState($id);
        $this->assertSame(2, (int) $updated['ticket_booth']['level']);
        $this->assertGreaterThan((float) $shop['ticket_booth']['total_income_per_minute'], (float) $updated['ticket_booth']['total_income_per_minute']);
    }

    public function testEventUpgradeCanLevelUpInBulk(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $tx = new Transaction();
        $tx->addTransaction($id, 'income', 1000.0, 'Capital', 'Funding de test');

        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 1));
        $this->assertTrue($upgradeModel->upgradeLevel($id, 'ticket_booth', 3));

        $updated = $upgradeModel->getShopState($id);
        $this->assertSame(4, (int) $updated['ticket_booth']['level']);
    }

    public function testEventUpgradeFailureReasonIdentifiesRootCause(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 1));
        $this->assertTrue($upgradeModel->pausePassiveIncome($id));

        $reason = $upgradeModel->getUpgradeLevelFailureReason($id, 'ticket_booth');
        $this->assertNotNull($reason);
        $this->assertStringContainsString('versement automatique', strtolower($reason));

        $this->assertFalse($upgradeModel->upgradeLevel($id, 'ticket_booth'));
    }

    public function testEventEconomySummaryProvidesProgressionMetrics(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 1));

        $summary = $upgradeModel->getEventEconomySummary($id);
        $this->assertArrayHasKey('prestige_tier', $summary);
        $this->assertArrayHasKey('total_income_per_minute', $summary);
        $this->assertGreaterThan(0.0, $summary['total_income_per_minute']);
        $this->assertGreaterThanOrEqual(1, $summary['owned_upgrades_count']);
        $this->assertNotEmpty($summary['next_upgrade_key']);
    }

    public function testEventUpgradeCanBeBoughtInBulk(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 3));

        $shop = $upgradeModel->getShopState($id);
        $this->assertSame(3, (int) $shop['ticket_booth']['owned']);
        $this->assertGreaterThan(0.0, $shop['ticket_booth']['total_income_per_minute']);
    }

    public function testEventEarlyGameAndOverdraftUnlockAreBalanced(): void
    {
        $id = $this->account->createAccount(
            1,
            'Festival local',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival local',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertLessThan(0.65, EventAccountUpgrade::getPriceGrowth());
        $this->assertSame(0.0, $upgradeModel->getEventOverdraftLimit($id));
        $this->assertFalse($upgradeModel->unlockEventOverdraft($id));

        $tx = new Transaction();
        $tx->addTransaction($id, 'income', 2000.0, 'Capital', 'Test de démarrage');

        $this->assertTrue($upgradeModel->unlockEventOverdraft($id));
        $this->assertEqualsWithDelta(200.0, $upgradeModel->getEventOverdraftLimit($id), 0.001);

        $tx->addTransaction($id, 'expense', 2500.0, 'Dépense', 'Solde négatif de test');
        $reduction = $upgradeModel->getIncomeReductionFromOverdraft($id);
        $this->assertGreaterThanOrEqual(0.10, $reduction);
        $this->assertLessThanOrEqual(0.25, $reduction);
    }

    public function testEventLeaderboardRanksAccountsByPerformance(): void
    {
        $firstId = $this->account->createAccount(
            1,
            'Festival A',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival A',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $secondId = $this->account->createAccount(
            1,
            'Festival B',
            'EUR',
            0.0,
            'event',
            null,
            false,
            [
                'title' => 'Festival B',
                'start_at' => date('Y-m-d H:i:s', time() - 3600),
                'end_at' => date('Y-m-d H:i:s', time() + 3600),
            ]
        );

        $upgradeModel = new EventAccountUpgrade();
        $this->assertTrue($upgradeModel->buyUpgrade($firstId, 'ticket_booth', 2));
        $this->assertTrue($upgradeModel->buyUpgrade($secondId, 'food_stall', 1));

        $leaderboard = $upgradeModel->getLeaderboard(10);
        $this->assertNotEmpty($leaderboard);
        $this->assertSame('Festival B', $leaderboard[0]['name']);
        $this->assertSame(1, $leaderboard[0]['rank']);
        $this->assertTrue($leaderboard[0]['income_per_minute'] >= $leaderboard[1]['income_per_minute']);
    }
}
