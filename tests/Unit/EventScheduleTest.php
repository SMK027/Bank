<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\EventSchedule;
use PHPUnit\Framework\TestCase;
use Tests\TestDatabase;

class EventScheduleTest extends TestCase
{
    private EventSchedule $model;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->model = new EventSchedule();
    }

    public function testUpdateEventChangesTitleAndWindow(): void
    {
        $id = $this->model->createEvent('Festival', '2026-08-01 10:00:00', '2026-08-01 12:00:00', 7);

        $updated = $this->model->updateEvent($id, 'Festival été', '2026-08-01 09:30:00', '2026-08-01 13:30:00');

        $this->assertTrue($updated);

        $event = $this->model->find($id);
        $this->assertSame('Festival été', $event['title']);
        $this->assertSame('2026-08-01 09:30:00', $event['start_at']);
        $this->assertSame('2026-08-01 13:30:00', $event['end_at']);
    }
}
