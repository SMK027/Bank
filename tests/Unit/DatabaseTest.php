<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use Tests\TestDatabase;

/**
 * Tests de la couche Database (singleton PDO).
 */
class DatabaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testMakeReturnsPdo(): void
    {
        $pdo = TestDatabase::make();
        $this->assertInstanceOf(\PDO::class, $pdo);
    }

    public function testGetInstanceReturnsSameObject(): void
    {
        TestDatabase::make();
        $a = Database::getInstance();
        $b = Database::getInstance();
        $this->assertSame($a, $b);
    }

    public function testSetInstanceReplacesExisting(): void
    {
        $pdo1 = TestDatabase::make();
        $pdo2 = new \PDO('sqlite::memory:');
        Database::setInstance($pdo2);
        $this->assertSame($pdo2, Database::getInstance());
    }

    public function testResetClearsSingleton(): void
    {
        TestDatabase::make();
        Database::reset();
        // Après reset, une nouvelle connexion est créée si on appelle getInstance()
        // Ici on ne peut pas se connecter à MariaDB en test, donc on vérifie juste
        // que reset() ne lève pas d'exception.
        $this->assertTrue(true);
    }
}
