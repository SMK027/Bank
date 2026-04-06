<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\Transaction;
use Tests\TestDatabase;

class TransactionTest extends TestCase
{
    private Transaction $transaction;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->transaction = new Transaction();
    }

    protected function tearDown(): void
    {
        Database::reset();
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

    /* ------------------------------------------------------------------
     *  getProtectedIds
     * ----------------------------------------------------------------*/

    public function testGetProtectedIdsEmptyInputReturnsEmpty(): void
    {
        $this->assertSame([], $this->transaction->getProtectedIds([]));
    }

    public function testGetProtectedIdsUnlinkedTransactionNotProtected(): void
    {
        $id = $this->transaction->addTransaction(1, 'income', 100.0, 'Salaire');
        $this->assertSame([], $this->transaction->getProtectedIds([$id]));
    }

    public function testGetProtectedIdsTransactionLinkedToTransferIsProtected(): void
    {
        $debitId  = $this->transaction->addTransaction(1, 'expense', 200.0, 'Virement');
        $creditId = $this->transaction->addTransaction(2, 'income',  200.0, 'Virement');

        $pdo = \App\Core\Database::getInstance();
        $pdo->exec("INSERT INTO transfers (from_account_id, to_account_id, user_id, amount, motif, status, debit_tx_id, credit_tx_id)
                    VALUES (1, 2, 1, 200.0, 'Test', 'success', $debitId, $creditId)");

        $protected = $this->transaction->getProtectedIds([$debitId, $creditId]);
        $this->assertContains($debitId,  $protected);
        $this->assertContains($creditId, $protected);
    }

    public function testGetProtectedIdsTransactionLinkedToDirectDebitIsProtected(): void
    {
        $debitId  = $this->transaction->addTransaction(3, 'expense', 50.0, 'Prélèvement');
        $creditId = $this->transaction->addTransaction(4, 'income',  50.0, 'Prélèvement');

        $pdo = \App\Core\Database::getInstance();
        $pdo->exec("INSERT INTO direct_debits (mandate_number, scheduled_at, amount, to_account_id, from_account_id, status, debit_tx_id, credit_tx_id, created_by)
                    VALUES ('MND-X', '2026-01-01 00:00:00', 50.0, 4, 3, 'success', $debitId, $creditId, 1)");

        $protected = $this->transaction->getProtectedIds([$debitId, $creditId]);
        $this->assertContains($debitId,  $protected);
        $this->assertContains($creditId, $protected);
    }

    public function testGetProtectedIdsOnlyReturnsLinkedSubset(): void
    {
        $free1 = $this->transaction->addTransaction(1, 'income',  100.0, 'Salaire');
        $free2 = $this->transaction->addTransaction(1, 'expense', 30.0,  'Transport');
        $linked = $this->transaction->addTransaction(1, 'expense', 500.0, 'Virement');

        $pdo = \App\Core\Database::getInstance();
        $pdo->exec("INSERT INTO transfers (from_account_id, to_account_id, user_id, amount, motif, status, debit_tx_id, credit_tx_id)
                    VALUES (1, 2, 1, 500.0, 'Test', 'success', $linked, 0)");

        $protected = $this->transaction->getProtectedIds([$free1, $free2, $linked]);
        $this->assertNotContains($free1, $protected);
        $this->assertNotContains($free2, $protected);
        $this->assertContains($linked, $protected);
    }
}
