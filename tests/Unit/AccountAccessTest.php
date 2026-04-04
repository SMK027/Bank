<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\AccountAccess;
use Tests\TestDatabase;

class AccountAccessTest extends TestCase
{
    private AccountAccess $access;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->access = new AccountAccess();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testGrantPermanentAccess(): void
    {
        $id = $this->access->grantAccess(1, 2, 'permanent');
        $this->assertGreaterThan(0, $id);

        $found = $this->access->find($id);
        $this->assertSame(1, $found['account_id']);
        $this->assertSame(2, $found['user_id']);
        $this->assertSame('permanent', $found['type']);
        $this->assertNull($found['expires_at']);
    }

    public function testGrantTemporaryAccess(): void
    {
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $id = $this->access->grantAccess(1, 2, 'temporary', $expires);

        $found = $this->access->find($id);
        $this->assertSame('temporary', $found['type']);
        $this->assertSame($expires, $found['expires_at']);
    }

    public function testHasValidAccessPermanent(): void
    {
        $this->access->grantAccess(1, 2, 'permanent');
        $this->assertTrue($this->access->hasValidAccess(1, 2));
    }

    public function testHasValidAccessTemporaryNotExpired(): void
    {
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $this->access->grantAccess(1, 2, 'temporary', $expires);
        $this->assertTrue($this->access->hasValidAccess(1, 2));
    }

    public function testHasValidAccessTemporaryExpired(): void
    {
        $expires = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $this->access->grantAccess(1, 2, 'temporary', $expires);
        $this->assertFalse($this->access->hasValidAccess(1, 2));
    }

    public function testHasNoAccess(): void
    {
        $this->assertFalse($this->access->hasValidAccess(1, 2));
    }

    public function testRevokeAccess(): void
    {
        $this->access->grantAccess(1, 2, 'permanent');
        $this->assertTrue($this->access->hasValidAccess(1, 2));

        $this->access->revokeAccess(1, 2);
        $this->assertFalse($this->access->hasValidAccess(1, 2));
    }

    public function testRevokeNonExistentAccess(): void
    {
        $result = $this->access->revokeAccess(1, 2);
        $this->assertFalse($result);
    }

    public function testGrantAccessReplacesExisting(): void
    {
        $this->access->grantAccess(1, 2, 'permanent');
        $expires = date('Y-m-d H:i:s', strtotime('+1 day'));
        $this->access->grantAccess(1, 2, 'temporary', $expires);

        $accesses = $this->access->getAccessesForAccount(1);
        $this->assertCount(1, $accesses);
        $this->assertSame('temporary', $accesses[0]['type']);
    }

    public function testGetAccessesForAccount(): void
    {
        $this->access->grantAccess(1, 2, 'permanent');
        $this->access->grantAccess(1, 3, 'temporary', date('Y-m-d H:i:s', strtotime('+1 day')));
        $this->access->grantAccess(2, 4, 'permanent');

        $accesses = $this->access->getAccessesForAccount(1);
        $this->assertCount(2, $accesses);

        $accesses2 = $this->access->getAccessesForAccount(2);
        $this->assertCount(1, $accesses2);
    }

    public function testGetValidAccessesForUser(): void
    {
        $this->access->grantAccess(1, 5, 'permanent');
        $this->access->grantAccess(2, 5, 'temporary', date('Y-m-d H:i:s', strtotime('+1 day')));
        $this->access->grantAccess(3, 5, 'temporary', date('Y-m-d H:i:s', strtotime('-1 day')));

        $valid = $this->access->getValidAccessesForUser(5);
        $this->assertCount(2, $valid); // permanent + non-expired temporary
    }

    public function testFindExisting(): void
    {
        $this->access->grantAccess(1, 2, 'permanent');

        $found = $this->access->findExisting(1, 2);
        $this->assertNotNull($found);
        $this->assertSame('permanent', $found['type']);

        $notFound = $this->access->findExisting(1, 3);
        $this->assertNull($notFound);
    }
}
