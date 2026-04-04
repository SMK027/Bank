<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\AccountAccess;
use App\Models\User;

class AccountTest extends TestCase
{
    private string $tmpDir;
    private Account $account;
    private User $user;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bankapp_account_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->account = new Account($this->tmpDir);
        $this->user = new User($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*'));
        rmdir($this->tmpDir);
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
        $tx = new Transaction($this->tmpDir);

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
        $access = new AccountAccess($this->tmpDir);

        $this->assertFalse($this->account->hasAccess($accId, 2));

        $access->grantAccess($accId, 2, 'permanent');
        $this->assertTrue($this->account->hasAccess($accId, 2));
    }

    public function testGetAccessibleAccounts(): void
    {
        $this->account->createAccount(1, 'Compte 1', 'EUR');
        $acc2 = $this->account->createAccount(2, 'Compte 2', 'USD');

        $access = new AccountAccess($this->tmpDir);
        $access->grantAccess($acc2, 1, 'permanent');

        $result = $this->account->getAccessibleAccounts(1);
        $this->assertCount(1, $result['own']);
        $this->assertCount(1, $result['shared']);
        $this->assertTrue($result['shared'][0]['_shared']);
    }

    public function testNegativeBalance(): void
    {
        $accId = $this->account->createAccount(1, 'Découvert', 'EUR', 500.0);
        $tx = new Transaction($this->tmpDir);

        $tx->addTransaction($accId, 'expense', 300.0, 'Factures', 'Loyer');
        $balance = $this->account->getBalance($accId);
        $this->assertSame(-300.0, $balance);
    }
}
