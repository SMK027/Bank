<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\RecurringTransfer;
use Tests\TestDatabase;

class RecurringTransferTest extends TestCase
{
    private RecurringTransfer $model;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->model = new RecurringTransfer();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function make(array $overrides = []): int
    {
        $defaults = [
            'from_account_id'   => 1,
            'to_account_id'     => 2,
            'user_id'           => 10,
            'amount'            => 50.00,
            'motif'             => 'Loyer',
            'interval_days'     => 30,
            'next_execution_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ];
        $data = array_merge($defaults, $overrides);
        return $this->model->createRecurringTransfer(
            $data['from_account_id'],
            $data['to_account_id'],
            $data['user_id'],
            $data['amount'],
            $data['motif'],
            $data['interval_days'],
            $data['next_execution_at']
        );
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function testCreateRecurringTransferPersistsRecord(): void
    {
        $id = $this->make();
        $record = $this->model->find($id);

        $this->assertNotNull($record);
        $this->assertSame(1,  (int) $record['from_account_id']);
        $this->assertSame(2,  (int) $record['to_account_id']);
        $this->assertSame(10, (int) $record['user_id']);
        $this->assertSame(50.0, (float) $record['amount']);
        $this->assertSame('Loyer', $record['motif']);
        $this->assertSame(30, (int) $record['interval_days']);
        $this->assertSame(RecurringTransfer::STATUS_ACTIVE, $record['status']);
    }

    public function testGetDueReturnsDueRecords(): void
    {
        // Première exécution dans le passé → dû
        $idDue = $this->make(['next_execution_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        // Première exécution dans le futur → pas dû
        $this->make(['next_execution_at' => date('Y-m-d H:i:s', strtotime('+1 day'))]);

        $due = $this->model->getDue();
        $this->assertCount(1, $due);
        $this->assertSame($idDue, (int) $due[0]['id']);
    }

    public function testGetDueIgnoresFutureRecords(): void
    {
        $this->make(['next_execution_at' => date('Y-m-d H:i:s', strtotime('+2 days'))]);
        $due = $this->model->getDue();
        $this->assertSame([], $due);
    }

    public function testGetDueIgnoresCancelledRecords(): void
    {
        // Crée un enregistrement échu, puis l'annule
        $id = $this->make(['next_execution_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        $this->model->cancel($id);

        $due = $this->model->getDue();
        $this->assertSame([], $due);
    }

    public function testMarkExecutedAdvancesNextExecutionDate(): void
    {
        $past     = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $interval = 7;
        $id       = $this->make(['next_execution_at' => $past, 'interval_days' => $interval]);

        $recordBefore = $this->model->find($id);
        $this->model->markExecuted($id);
        $recordAfter = $this->model->find($id);

        $before = strtotime($recordBefore['next_execution_at']);
        $after  = strtotime($recordAfter['next_execution_at']);

        $this->assertEqualsWithDelta($interval * 86400, $after - $before, 5);
    }

    public function testMarkExecutedCanBeCalledMultipleTimes(): void
    {
        $interval = 14;
        $id       = $this->make(['next_execution_at' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'interval_days' => $interval]);

        // Première exécution
        $this->model->markExecuted($id);
        $after1 = strtotime($this->model->find($id)['next_execution_at']);

        // On replace la date dans le passé (on simule l'écoulement du temps)
        $before2Ts = $after1 - $interval * 86400 - 3600;
        $this->model->update($id, ['next_execution_at' => date('Y-m-d H:i:s', $before2Ts)]);

        // Deuxième exécution : la date doit avancer de exactement interval_days
        $before2 = strtotime($this->model->find($id)['next_execution_at']);
        $this->model->markExecuted($id);
        $after2 = strtotime($this->model->find($id)['next_execution_at']);

        $this->assertEqualsWithDelta($interval * 86400, $after2 - $before2, 5);
    }

    public function testMarkExecutedSetsLastExecutedAt(): void
    {
        $id = $this->make(['next_execution_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);
        $this->assertNull($this->model->find($id)['last_executed_at']);

        $this->model->markExecuted($id);
        $this->assertNotNull($this->model->find($id)['last_executed_at']);
    }

    public function testCancelSetsStatusCancelled(): void
    {
        $id = $this->make();
        $this->model->cancel($id);
        $record = $this->model->find($id);

        $this->assertSame(RecurringTransfer::STATUS_CANCELLED, $record['status']);
    }

    public function testCanCancelActiveReturnsTrue(): void
    {
        $id = $this->make();
        $record = $this->model->find($id);
        $this->assertTrue($this->model->canCancel($record));
    }

    public function testCanCancelCancelledReturnsFalse(): void
    {
        $id = $this->make();
        $this->model->cancel($id);
        $record = $this->model->find($id);
        $this->assertFalse($this->model->canCancel($record));
    }

    public function testGetByUserReturnsOnlyMatchingUser(): void
    {
        $this->make(['user_id' => 1]);
        $this->make(['user_id' => 1]);
        $this->make(['user_id' => 2]);

        $results = $this->model->getByUser(1);
        $this->assertCount(2, $results);
        foreach ($results as $r) {
            $this->assertSame(1, (int) $r['user_id']);
        }
    }

    public function testGetByUserReturnsEmptyForUnknownUser(): void
    {
        $this->make(['user_id' => 5]);
        $this->assertSame([], $this->model->getByUser(999));
    }

    public function testGetByAccountReturnsMatchingRecords(): void
    {
        $this->make(['from_account_id' => 10]);
        $this->make(['to_account_id'   => 10]);
        $this->make(['from_account_id' => 20, 'to_account_id' => 30]);

        $results = $this->model->getByAccount(10);
        $this->assertCount(2, $results);
    }
}
