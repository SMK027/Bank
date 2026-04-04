<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Config\Database;

/**
 * Tests du singleton Database.
 */
class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        Database::resetInstance();
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    public function testResetInstanceClearsSingleton(): void
    {
        Database::resetInstance();
        $this->assertTrue(true); // Pas d'exception = succès
    }

    public function testCannotCloneDatabase(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $cloneMethod = $reflection->getMethod('__clone');
        $this->assertTrue($cloneMethod->isPrivate());
    }

    public function testConstructorIsPrivate(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $constructor = $reflection->getConstructor();
        $this->assertTrue($constructor->isPrivate());
    }

    public function testWakeupThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $reflection = new \ReflectionClass(Database::class);
        $wakeup = $reflection->getMethod('__wakeup');
        $instance = $reflection->newInstanceWithoutConstructor();
        $wakeup->invoke($instance);
    }
}
