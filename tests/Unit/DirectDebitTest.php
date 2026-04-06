<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\DirectDebit;
use Tests\TestDatabase;

class DirectDebitTest extends TestCase
{
    private DirectDebit $dd;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->dd = new DirectDebit();
    }

    /* ------------------------------------------------------------------
     *  createDirectDebit + find
     * ----------------------------------------------------------------*/

    public function testCreateDirectDebit(): void
    {
        $id = $this->dd->createDirectDebit('MND-001', '2026-04-06 10:00:00', 100.0, 1, 2, 'Test', 1);
        $this->assertGreaterThan(0, $id);

        $record = $this->dd->find($id);
        $this->assertSame('MND-001', $record['mandate_number']);
        $this->assertSame('scheduled', $record['status']);
        $this->assertEquals(100.0, (float) $record['amount']);
    }

    /* ------------------------------------------------------------------
     *  canCancel
     * ----------------------------------------------------------------*/

    public function testCanCancelScheduled(): void
    {
        $this->assertTrue($this->dd->canCancel(['status' => 'scheduled']));
    }

    public function testCanCancelNonScheduled(): void
    {
        $this->assertFalse($this->dd->canCancel(['status' => 'success']));
        $this->assertFalse($this->dd->canCancel(['status' => 'rejected']));
    }

    /* ------------------------------------------------------------------
     *  canReject
     * ----------------------------------------------------------------*/

    public function testCanRejectWithin48hTo14d(): void
    {
        $executedAt = date('Y-m-d H:i:s', time() - 3 * 24 * 3600); // 3 jours
        $this->assertTrue($this->dd->canReject([
            'status'      => 'success',
            'executed_at' => $executedAt,
        ]));
    }

    public function testCanRejectTooRecent(): void
    {
        $executedAt = date('Y-m-d H:i:s', time() - 3600); // 1h
        $this->assertFalse($this->dd->canReject([
            'status'      => 'success',
            'executed_at' => $executedAt,
        ]));
    }

    public function testCanRejectTooOld(): void
    {
        $executedAt = date('Y-m-d H:i:s', time() - 15 * 24 * 3600); // 15 jours
        $this->assertFalse($this->dd->canReject([
            'status'      => 'success',
            'executed_at' => $executedAt,
        ]));
    }

    public function testCanRejectWrongStatus(): void
    {
        $this->assertFalse($this->dd->canReject(['status' => 'scheduled', 'executed_at' => date('Y-m-d H:i:s')]));
    }

    /* ------------------------------------------------------------------
     *  canRetry
     * ----------------------------------------------------------------*/

    public function testCanRetryRejected(): void
    {
        $this->assertTrue($this->dd->canRetry(['status' => 'rejected']));
    }

    public function testCanRetryFailed(): void
    {
        $this->assertTrue($this->dd->canRetry(['status' => 'failed']));
    }

    public function testCanRetryScheduled(): void
    {
        $this->assertFalse($this->dd->canRetry(['status' => 'scheduled']));
    }

    public function testCanRetrySuccess(): void
    {
        $this->assertFalse($this->dd->canRetry(['status' => 'success']));
    }

    public function testCanRetryCancelled(): void
    {
        $this->assertFalse($this->dd->canRetry(['status' => 'cancelled']));
    }

    /* ------------------------------------------------------------------
     *  retry — crée un nouveau prélèvement planifié
     * ----------------------------------------------------------------*/

    public function testRetryRejectedCreatesNewScheduled(): void
    {
        $id = $this->dd->createDirectDebit('MND-002', '2026-04-01 08:00:00', 250.0, 10, 20, 'Loyer', 5);
        $this->dd->markAutoRejected($id);

        $newId = $this->dd->retry($id);
        $this->assertNotNull($newId);
        $this->assertNotSame($id, $newId);

        $newRecord = $this->dd->find($newId);
        $this->assertSame('scheduled', $newRecord['status']);
        $this->assertSame('MND-002', $newRecord['mandate_number']);
        $this->assertEquals(250.0, (float) $newRecord['amount']);
        $this->assertEquals(10, (int) $newRecord['to_account_id']);
        $this->assertEquals(20, (int) $newRecord['from_account_id']);
        $this->assertSame('Loyer', $newRecord['motif']);
    }

    public function testRetryFailedCreatesNewScheduled(): void
    {
        $id = $this->dd->createDirectDebit('MND-003', '2026-04-01 08:00:00', 50.0, 3, null, null, 1);
        $this->dd->markFailed($id);

        $newId = $this->dd->retry($id);
        $this->assertNotNull($newId);

        $newRecord = $this->dd->find($newId);
        $this->assertSame('scheduled', $newRecord['status']);
        $this->assertNull($newRecord['from_account_id']);
    }

    public function testRetryNonRetryableReturnsNull(): void
    {
        $id = $this->dd->createDirectDebit('MND-004', '2026-04-01 08:00:00', 100.0, 1, 2, 'Test', 1);
        // status = scheduled → pas retryable
        $this->assertNull($this->dd->retry($id));
    }

    public function testRetryNonExistentReturnsNull(): void
    {
        $this->assertNull($this->dd->retry(99999));
    }

    /* ------------------------------------------------------------------
     *  markSuccess / markFailed / markCancelled / markRejected
     * ----------------------------------------------------------------*/

    public function testMarkSuccess(): void
    {
        $id = $this->dd->createDirectDebit('MND-010', '2026-04-06 10:00:00', 30.0, 1);
        $this->dd->markSuccess($id, 100, 200);
        $record = $this->dd->find($id);
        $this->assertSame('success', $record['status']);
        $this->assertNotNull($record['executed_at']);
    }

    public function testMarkFailed(): void
    {
        $id = $this->dd->createDirectDebit('MND-011', '2026-04-06 10:00:00', 30.0, 1);
        $this->dd->markFailed($id);
        $this->assertSame('failed', $this->dd->find($id)['status']);
    }

    public function testMarkCancelled(): void
    {
        $id = $this->dd->createDirectDebit('MND-012', '2026-04-06 10:00:00', 30.0, 1);
        $this->dd->markCancelled($id);
        $this->assertSame('cancelled', $this->dd->find($id)['status']);
    }

    public function testMarkRejected(): void
    {
        $id = $this->dd->createDirectDebit('MND-013', '2026-04-06 10:00:00', 30.0, 1);
        $this->dd->markSuccess($id, 1, 2);
        $this->dd->markRejected($id);
        $this->assertSame('rejected', $this->dd->find($id)['status']);
    }

    public function testMarkAutoRejected(): void
    {
        $id = $this->dd->createDirectDebit('MND-014', '2026-04-06 10:00:00', 30.0, 1);
        $this->dd->markAutoRejected($id);
        $record = $this->dd->find($id);
        $this->assertSame('rejected', $record['status']);
        $this->assertNotNull($record['executed_at']);
    }

    /* ------------------------------------------------------------------
     *  getDue
     * ----------------------------------------------------------------*/

    public function testGetDueReturnsPastScheduled(): void
    {
        $pastId = $this->dd->createDirectDebit('MND-020', date('Y-m-d H:i:s', time() - 60), 10.0, 1);
        $futureId = $this->dd->createDirectDebit('MND-021', date('Y-m-d H:i:s', time() + 3600), 10.0, 1);

        $due = $this->dd->getDue();
        $dueIds = array_column($due, 'id');
        $this->assertContains($pastId, $dueIds);
        $this->assertNotContains($futureId, $dueIds);
    }

    /* ------------------------------------------------------------------
     *  Constants
     * ----------------------------------------------------------------*/

    public function testStatusConstants(): void
    {
        $this->assertSame('scheduled', DirectDebit::STATUS_SCHEDULED);
        $this->assertSame('success', DirectDebit::STATUS_SUCCESS);
        $this->assertSame('failed', DirectDebit::STATUS_FAILED);
        $this->assertSame('cancelled', DirectDebit::STATUS_CANCELLED);
        $this->assertSame('rejected', DirectDebit::STATUS_REJECTED);
    }
}
