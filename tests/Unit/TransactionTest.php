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
        $account = new \App\Models\Account();
        $account->createAccount(1, 'Compte 1', 'EUR');
        $account->createAccount(1, 'Compte 2', 'EUR');
        $account->createAccount(1, 'Compte 3', 'EUR');
        $account->createAccount(1, 'Compte 4', 'EUR');
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

    public function testExpenseCategories(): void
    {
        $this->assertIsArray(Transaction::EXPENSE_CATEGORIES);
        $this->assertArrayHasKey('Alimentation', Transaction::EXPENSE_CATEGORIES);
        $this->assertArrayHasKey('Transport', Transaction::EXPENSE_CATEGORIES);
        $this->assertArrayHasKey('Factures', Transaction::EXPENSE_CATEGORIES);
        $this->assertArrayHasKey('Autre', Transaction::EXPENSE_CATEGORIES);
    }

    public function testIncomeCategories(): void
    {
        $this->assertIsArray(Transaction::INCOME_CATEGORIES);
        $this->assertArrayHasKey('Salaire', Transaction::INCOME_CATEGORIES);
        $this->assertArrayHasKey('Freelance', Transaction::INCOME_CATEGORIES);
        $this->assertArrayHasKey('Investissement', Transaction::INCOME_CATEGORIES);
        $this->assertArrayHasKey('Autre', Transaction::INCOME_CATEGORIES);
    }

    public function testGetCategoriesForType(): void
    {
        $this->assertSame(Transaction::EXPENSE_CATEGORIES, Transaction::getCategoriesForType('expense'));
        $this->assertSame(Transaction::INCOME_CATEGORIES, Transaction::getCategoriesForType('income'));
    }

    public function testIsValidCategory(): void
    {
        $this->assertTrue(Transaction::isValidCategory('Alimentation', 'expense'));
        $this->assertFalse(Transaction::isValidCategory('Salaire', 'expense'));
        $this->assertTrue(Transaction::isValidCategory('Salaire', 'income'));
        $this->assertFalse(Transaction::isValidCategory('Alimentation', 'income'));
        $this->assertFalse(Transaction::isValidCategory('Inexistante', 'expense'));
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

    /* ------------------------------------------------------------------
     *  getByAccountBetween / getBalanceBeforeDate
     * ----------------------------------------------------------------*/

    public function testGetByAccountBetweenReturnsTransactionsInRange(): void
    {
        $pdo = \App\Core\Database::getInstance();

        // Insère des transactions avec des dates contrôlées
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, comment, created_at)
                    VALUES (5, 'income', 100.0, 'Salaire', 'Jan', '2025-01-15 10:00:00')");
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, comment, created_at)
                    VALUES (5, 'expense', 30.0, 'Alimentation', 'Jan', '2025-01-20 12:00:00')");
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, comment, created_at)
                    VALUES (5, 'income', 200.0, 'Freelance', 'Fev', '2025-02-05 09:00:00')");

        $results = $this->transaction->getByAccountBetween(5, '2025-01-01', '2025-01-31');
        $this->assertCount(2, $results);
        foreach ($results as $r) {
            $this->assertSame('5', (string) $r['account_id']);
        }
    }

    public function testGetByAccountBetweenExcludesOtherAccounts(): void
    {
        $pdo = \App\Core\Database::getInstance();
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, created_at)
                    VALUES (6, 'income', 50.0, 'Salaire', '2025-03-10 08:00:00')");
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, created_at)
                    VALUES (7, 'income', 75.0, 'Salaire', '2025-03-10 08:00:00')");

        $results = $this->transaction->getByAccountBetween(6, '2025-03-01', '2025-03-31');
        $this->assertCount(1, $results);
        $this->assertSame('6', (string) $results[0]['account_id']);
    }

    public function testGetByAccountBetweenReturnsEmptyWhenNoneInRange(): void
    {
        $pdo = \App\Core\Database::getInstance();
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, created_at)
                    VALUES (8, 'income', 100.0, 'Salaire', '2025-01-15 10:00:00')");

        $results = $this->transaction->getByAccountBetween(8, '2025-06-01', '2025-06-30');
        $this->assertSame([], $results);
    }

    public function testGetBalanceBeforeDateReturnsZeroWithNoTransactions(): void
    {
        $bal = $this->transaction->getBalanceBeforeDate(99, '2025-01-01');
        $this->assertSame(0.0, $bal);
    }

    public function testGetBalanceBeforeDateComputesCorrectly(): void
    {
        $pdo = \App\Core\Database::getInstance();
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, created_at)
                    VALUES (10, 'income', 1000.0, 'Salaire', '2025-01-01 09:00:00')");
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, created_at)
                    VALUES (10, 'expense', 200.0, 'Alimentation', '2025-01-15 10:00:00')");
        // Cette transaction est après la coupure → ne doit pas être comptée
        $pdo->exec("INSERT INTO transactions (account_id, type, amount, category, created_at)
                    VALUES (10, 'income', 500.0, 'Freelance', '2025-02-01 09:00:00')");

        // Solde avant le 1er février : 1000 - 200 = 800
        $bal = $this->transaction->getBalanceBeforeDate(10, '2025-02-01');
        $this->assertSame(800.0, $bal);
    }
}

