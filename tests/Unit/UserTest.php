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

    // --- Tests profil professionnel ---

    public function testIsProfessionalDefaultFalse(): void
    {
        $id   = $this->user->register('pro1', 'pro1@test.com', 'password123', '1985-01-01');
        $user = $this->user->find($id);
        $this->assertFalse(User::isProfessional($user));
    }

    public function testSetProfessional(): void
    {
        $id = $this->user->register('pro2', 'pro2@test.com', 'password123', '1985-01-01');
        $this->user->setProfessional($id, 'ACME SAS', '36252187900034');

        $user = $this->user->find($id);
        $this->assertTrue(User::isProfessional($user));
        $this->assertSame('ACME SAS', $user['company_name']);
        $this->assertSame('36252187900034', $user['siret']);
    }

    public function testRemoveProfessional(): void
    {
        $id = $this->user->register('pro3', 'pro3@test.com', 'password123', '1985-01-01');
        $this->user->setProfessional($id, 'My Corp', '12345678901234');
        $this->user->removeProfessional($id);

        $user = $this->user->find($id);
        $this->assertFalse(User::isProfessional($user));
        $this->assertNull($user['company_name']);
        $this->assertNull($user['siret']);
    }

    public function testIsProfessionalNullUser(): void
    {
        $this->assertFalse(User::isProfessional(null));
    }

    // ─── STATUS CONSTANTS ────────────────────────────────────────────────────

    public function testStatusConstants(): void
    {
        $this->assertSame('active',    User::STATUS_ACTIVE);
        $this->assertSame('suspended', User::STATUS_SUSPENDED);
        $this->assertSame('banned',    User::STATUS_BANNED);
    }

    // ─── suspend() ───────────────────────────────────────────────────────────

    public function testSuspendWithoutDate(): void
    {
        $id = $this->user->register('susp1', 'susp1@test.com', 'password123', '1990-01-01');
        $this->user->suspend($id);
        $u = $this->user->find($id);

        $this->assertSame('suspended', $u['status']);
        $this->assertNull($u['suspended_until']);
    }

    public function testSuspendWithDate(): void
    {
        $id    = $this->user->register('susp2', 'susp2@test.com', 'password123', '1990-01-01');
        $until = '2099-12-31 23:59:59';
        $this->user->suspend($id, $until);
        $u = $this->user->find($id);

        $this->assertSame('suspended', $u['status']);
        $this->assertSame($until, $u['suspended_until']);
    }

    // ─── ban() ───────────────────────────────────────────────────────────────

    public function testBanUser(): void
    {
        $id = $this->user->register('ban1', 'ban1@test.com', 'password123', '1990-01-01');
        $this->user->ban($id);
        $u = $this->user->find($id);

        $this->assertSame('banned', $u['status']);
        $this->assertNull($u['suspended_until']);
    }

    public function testBanClearsSuspendedUntil(): void
    {
        $id = $this->user->register('ban2', 'ban2@test.com', 'password123', '1990-01-01');
        $this->user->suspend($id, '2099-01-01 23:59:59');
        $this->user->ban($id);
        $u = $this->user->find($id);

        $this->assertSame('banned', $u['status']);
        $this->assertNull($u['suspended_until']);
    }

    // ─── activate() ──────────────────────────────────────────────────────────

    public function testActivateFromSuspended(): void
    {
        $id = $this->user->register('act1', 'act1@test.com', 'password123', '1990-01-01');
        $this->user->suspend($id, '2099-12-31 23:59:59');
        $this->user->activate($id);
        $u = $this->user->find($id);

        $this->assertSame('active', $u['status']);
        $this->assertNull($u['suspended_until']);
    }

    public function testActivateFromBanned(): void
    {
        $id = $this->user->register('act2', 'act2@test.com', 'password123', '1990-01-01');
        $this->user->ban($id);
        $this->user->activate($id);
        $u = $this->user->find($id);

        $this->assertSame('active', $u['status']);
    }

    // ─── authenticate() — vérification du statut ─────────────────────────────

    public function testAuthenticateActiveUserReturnsUser(): void
    {
        $this->user->register('auth1', 'auth1@test.com', 'password123', '1990-01-01');
        $u = $this->user->authenticate('auth1@test.com', 'password123');

        $this->assertNotNull($u);
        $this->assertSame('auth1', $u['username']);
        $this->assertSame('active', $u['status'] ?? 'active');
    }

    public function testAuthenticateSuspendedUserReturnsSuspendedStatus(): void
    {
        $id = $this->user->register('auth2', 'auth2@test.com', 'password123', '1990-01-01');
        $this->user->suspend($id, '2099-01-01 23:59:59');
        $u = $this->user->authenticate('auth2@test.com', 'password123');

        $this->assertNotNull($u);
        $this->assertSame('suspended', $u['status']);
    }

    public function testAuthenticateBannedUserReturnsBannedStatus(): void
    {
        $id = $this->user->register('auth3', 'auth3@test.com', 'password123', '1990-01-01');
        $this->user->ban($id);
        $u = $this->user->authenticate('auth3@test.com', 'password123');

        $this->assertNotNull($u);
        $this->assertSame('banned', $u['status']);
    }

    public function testAuthenticateAutoLiftsExpiredSuspension(): void
    {
        $id = $this->user->register('auth4', 'auth4@test.com', 'password123', '1990-01-01');
        // Forcer une suspension déjà expirée
        $pdo = Database::getInstance();
        $pdo->prepare(
            "UPDATE users SET status = 'suspended', suspended_until = '2000-01-01 23:59:59' WHERE id = ?"
        )->execute([$id]);

        $u = $this->user->authenticate('auth4@test.com', 'password123');
        $this->assertNotNull($u);
        $this->assertSame('active', $u['status']);

        // La BDD doit être mise à jour
        $fresh = $this->user->find($id);
        $this->assertSame('active', $fresh['status']);
        $this->assertNull($fresh['suspended_until']);
    }
}
