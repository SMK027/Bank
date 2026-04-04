<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test placeholder — Base de données remplacée par stockage JSON.
 */
class DatabaseTest extends TestCase
{
    public function testDataDirectoryIsWritable(): void
    {
        $tmpDir = sys_get_temp_dir() . '/bankapp_db_test_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $this->assertDirectoryExists($tmpDir);
        $this->assertDirectoryIsWritable($tmpDir);
        rmdir($tmpDir);
    }
}
