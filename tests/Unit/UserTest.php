<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\User;
use Tests\TestDatabase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->user = new User();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testRegisterCreatesUser(): void
    {
        $id = $this->user->register('alice', 'alice@test.com', 'password123', '1990-06-15');
        $this->assertGreaterThan(0, $id);

        $found = $this->user->find($id);
        $this->assertSame('alice', $found['username']);
        $this->assertSame('alice@test.com', $found['email']);
        $this->assertSame('user', $found['global_role']);
    }

    public function testPasswordIsHashed(): void
    {
        $id = $this->user->register('bob', 'bob@test.com', 'mypassword', '1985-03-22');
        $found = $this->user->find($id);

        $this->assertNotSame('mypassword', $found['password']);
        $this->assertTrue(password_verify('mypassword', $found['password']));
    }

    public function testAuthenticateSuccess(): void
    {
        $this->user->register('charlie', 'charlie@test.com', 'secret123', '1992-11-01');
        $result = $this->user->authenticate('charlie@test.com', 'secret123');

        $this->assertNotNull($result);
        $this->assertSame('charlie', $result['username']);
    }

    public function testAuthenticateFailsWithWrongPassword(): void
    {
        $this->user->register('dave', 'dave@test.com', 'correctpassword', '1988-07-10');
        $result = $this->user->authenticate('dave@test.com', 'wrongpassword');

        $this->assertNull($result);
    }

    public function testAuthenticateFailsWithUnknownEmail(): void
    {
        $result = $this->user->authenticate('unknown@test.com', 'password');
        $this->assertNull($result);
    }

    public function testFindByUsername(): void
    {
        $this->user->register('eve', 'eve@test.com', 'password123', '1995-01-30');
        $found = $this->user->findByUsername('eve');

        $this->assertNotNull($found);
        $this->assertSame('eve@test.com', $found['email']);
    }

    public function testFindByEmail(): void
    {
        $this->user->register('frank', 'frank@test.com', 'password123', '1983-09-05');
        $found = $this->user->findByEmail('frank@test.com');

        $this->assertNotNull($found);
        $this->assertSame('frank', $found['username']);
    }

    public function testFindByUsernameReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->user->findByUsername('nonexistent'));
    }

    public function testUpdatePassword(): void
    {
        $id = $this->user->register('grace', 'grace@test.com', 'oldpassword', '1991-04-18');
        $this->user->updatePassword($id, 'newpassword');

        $result = $this->user->authenticate('grace@test.com', 'newpassword');
        $this->assertNotNull($result);

        $resultOld = $this->user->authenticate('grace@test.com', 'oldpassword');
        $this->assertNull($resultOld);
    }

    public function testUpdateRoleToModerator(): void
    {
        $id = $this->user->register('henry', 'henry@test.com', 'password123', '1987-12-25');
        $found = $this->user->find($id);
        $this->assertSame('user', $found['global_role']);

        $result = $this->user->updateRole($id, 'moderator');
        $this->assertTrue($result);

        $updated = $this->user->find($id);
        $this->assertSame('moderator', $updated['global_role']);
    }

    public function testUpdateRoleRejectsInvalidRole(): void
    {
        $id = $this->user->register('irene', 'irene@test.com', 'password123', '1993-08-14');
        $result = $this->user->updateRole($id, 'superadmin');
        $this->assertFalse($result);

        $found = $this->user->find($id);
        $this->assertSame('user', $found['global_role']);
    }

    public function testUpdateRoleBackToUser(): void
    {
        $id = $this->user->register('jack', 'jack@test.com', 'password123', '1980-02-28');
        $this->user->updateRole($id, 'moderator');
        $this->user->updateRole($id, 'user');

        $found = $this->user->find($id);
        $this->assertSame('user', $found['global_role']);
    }
}
