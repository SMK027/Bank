<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Transaction;

class TransactionTest extends TestCase
{
    private string $tmpDir;
    private Transaction $transaction;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bankapp_tx_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->transaction = new Transaction($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*'));
        rmdir($this->tmpDir);
    }

    public function testAddTransaction(): void
    {
        $id = $this->transaction->addTransaction(1, 'income', 500.0, 'Salaire', 'Paye janvier');
        $this->assertGreaterThan(0, $id);

        $found = $this->transaction->find($id);
        $this->assertSame(1, $found['account_id']);
        $this->assertSame('income', $found['type']);
        $this->assertEquals(500.0, $found['amount']);
        $this->assertSame('Salaire', $found['category']);
        $this->assertSame('Paye janvier', $found['comment']);
    }

    public function testGetByAccount(): void
    {
        $this->transaction->addTransaction(1, 'income', 100.0, 'Salaire');
        $this->transaction->addTransaction(1, 'expense', 50.0, 'Alimentation');
        $this->transaction->addTransaction(2, 'income', 200.0, 'Freelance');

        $acc1Tx = $this->transaction->getByAccount(1);
        $this->assertCount(2, $acc1Tx);

        $acc2Tx = $this->transaction->getByAccount(2);
        $this->assertCount(1, $acc2Tx);
    }

    public function testGetTotalIncome(): void
    {
        $this->transaction->addTransaction(1, 'income', 1000.0, 'Salaire');
        $this->transaction->addTransaction(1, 'income', 500.0, 'Freelance');
        $this->transaction->addTransaction(1, 'expense', 200.0, 'Alimentation');

        $total = $this->transaction->getTotalIncome(1);
        $this->assertSame(1500.0, $total);
    }

    public function testGetTotalExpense(): void
    {
        $this->transaction->addTransaction(1, 'income', 1000.0, 'Salaire');
        $this->transaction->addTransaction(1, 'expense', 200.0, 'Alimentation');
        $this->transaction->addTransaction(1, 'expense', 100.0, 'Transport');

        $total = $this->transaction->getTotalExpense(1);
        $this->assertSame(300.0, $total);
    }

    public function testCategoriesConstant(): void
    {
        $this->assertIsArray(Transaction::CATEGORIES);
        $this->assertContains('Alimentation', Transaction::CATEGORIES);
        $this->assertContains('Salaire', Transaction::CATEGORIES);
        $this->assertContains('Transport', Transaction::CATEGORIES);
    }

    public function testGetTotalIncomeWithNoTransactions(): void
    {
        $total = $this->transaction->getTotalIncome(999);
        $this->assertSame(0.0, $total);
    }

    public function testGetTotalExpenseWithNoTransactions(): void
    {
        $total = $this->transaction->getTotalExpense(999);
        $this->assertSame(0.0, $total);
    }

    public function testDeleteTransaction(): void
    {
        $id = $this->transaction->addTransaction(1, 'income', 100.0, 'Salaire');
        $this->assertTrue($this->transaction->delete($id));
        $this->assertNull($this->transaction->find($id));
    }

    public function testAddTransactionStoresUserId(): void
    {
        $id = $this->transaction->addTransaction(1, 'income', 250.0, 'Salaire', 'Juillet', 42);
        $found = $this->transaction->find($id);
        $this->assertSame(42, (int) $found['user_id']);
    }
}
