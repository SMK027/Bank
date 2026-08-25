<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\AccountAccess;
use App\Models\EventAccountUpgrade;
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
        $this->assertSame(0.0, $upgradeModel->getPassiveIncome($id));

        $initialCost = $upgradeModel->getShopState($id)['ticket_booth']['cost'];
        $this->assertEqualsWithDelta(80.0, $initialCost, 0.001);

        $this->assertTrue($upgradeModel->buyUpgrade($id, 'ticket_booth', 1));
        $this->assertGreaterThan(0.0, $upgradeModel->getPassiveIncome($id));
        $this->assertEqualsWithDelta(0.5, $upgradeModel->getPassiveIncome($id), 0.001);

        $nextCost = $upgradeModel->getShopState($id)['ticket_booth']['cost'];
        $this->assertGreaterThan($initialCost, $nextCost);
    }
}
