<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\User;

class UserTest extends TestCase
{
    private string $tmpDir;
    private User $user;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bankapp_user_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->user = new User($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*'));
        rmdir($this->tmpDir);
    }

    public function testRegisterCreatesUser(): void
    {
        $id = $this->user->register('alice', 'alice@test.com', 'password123');
        $this->assertGreaterThan(0, $id);

        $found = $this->user->find($id);
        $this->assertSame('alice', $found['username']);
        $this->assertSame('alice@test.com', $found['email']);
        $this->assertSame('user', $found['global_role']);
    }

    public function testPasswordIsHashed(): void
    {
        $id = $this->user->register('bob', 'bob@test.com', 'mypassword');
        $found = $this->user->find($id);

        $this->assertNotSame('mypassword', $found['password']);
        $this->assertTrue(password_verify('mypassword', $found['password']));
    }

    public function testAuthenticateSuccess(): void
    {
        $this->user->register('charlie', 'charlie@test.com', 'secret123');
        $result = $this->user->authenticate('charlie@test.com', 'secret123');

        $this->assertNotNull($result);
        $this->assertSame('charlie', $result['username']);
    }

    public function testAuthenticateFailsWithWrongPassword(): void
    {
        $this->user->register('dave', 'dave@test.com', 'correctpassword');
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
        $this->user->register('eve', 'eve@test.com', 'password123');
        $found = $this->user->findByUsername('eve');

        $this->assertNotNull($found);
        $this->assertSame('eve@test.com', $found['email']);
    }

    public function testFindByEmail(): void
    {
        $this->user->register('frank', 'frank@test.com', 'password123');
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
        $id = $this->user->register('grace', 'grace@test.com', 'oldpassword');
        $this->user->updatePassword($id, 'newpassword');

        $result = $this->user->authenticate('grace@test.com', 'newpassword');
        $this->assertNotNull($result);

        $resultOld = $this->user->authenticate('grace@test.com', 'oldpassword');
        $this->assertNull($resultOld);
    }

    public function testUpdateRoleToModerator(): void
    {
        $id = $this->user->register('henry', 'henry@test.com', 'password123');
        $found = $this->user->find($id);
        $this->assertSame('user', $found['global_role']);

        $result = $this->user->updateRole($id, 'moderator');
        $this->assertTrue($result);

        $updated = $this->user->find($id);
        $this->assertSame('moderator', $updated['global_role']);
    }

    public function testUpdateRoleRejectsInvalidRole(): void
    {
        $id = $this->user->register('irene', 'irene@test.com', 'password123');
        $result = $this->user->updateRole($id, 'superadmin');
        $this->assertFalse($result);

        $found = $this->user->find($id);
        $this->assertSame('user', $found['global_role']);
    }

    public function testUpdateRoleBackToUser(): void
    {
        $id = $this->user->register('jack', 'jack@test.com', 'password123');
        $this->user->updateRole($id, 'moderator');
        $this->user->updateRole($id, 'user');

        $found = $this->user->find($id);
        $this->assertSame('user', $found['global_role']);
    }
}
