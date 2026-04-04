<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Middleware;

/**
 * Tests du Middleware d'accès.
 */
class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─── isSuperAdmin ────────────────────────────────────────

    public function testIsSuperAdminReturnsTrueForSuperadmin(): void
    {
        $_SESSION['global_role'] = 'superadmin';
        $this->assertTrue(Middleware::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsFalseForAdmin(): void
    {
        $_SESSION['global_role'] = 'admin';
        $this->assertFalse(Middleware::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsFalseForUser(): void
    {
        $_SESSION['global_role'] = 'user';
        $this->assertFalse(Middleware::isSuperAdmin());
    }

    public function testIsSuperAdminReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(Middleware::isSuperAdmin());
    }

    // ─── isGlobalStaff ───────────────────────────────────────

    public function testIsGlobalStaffReturnsTrueForSuperadmin(): void
    {
        $_SESSION['global_role'] = 'superadmin';
        $this->assertTrue(Middleware::isGlobalStaff());
    }

    public function testIsGlobalStaffReturnsTrueForAdmin(): void
    {
        $_SESSION['global_role'] = 'admin';
        $this->assertTrue(Middleware::isGlobalStaff());
    }

    public function testIsGlobalStaffReturnsTrueForModerator(): void
    {
        $_SESSION['global_role'] = 'moderator';
        $this->assertTrue(Middleware::isGlobalStaff());
    }

    public function testIsGlobalStaffReturnsFalseForUser(): void
    {
        $_SESSION['global_role'] = 'user';
        $this->assertFalse(Middleware::isGlobalStaff());
    }

    public function testIsGlobalStaffReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(Middleware::isGlobalStaff());
    }

    // ─── isAuthenticated ─────────────────────────────────────

    public function testIsAuthenticatedReturnsFalseWhenNotSet(): void
    {
        $this->assertFalse(Middleware::isAuthenticated());
    }

    public function testIsAuthenticatedReturnsTrueWhenSet(): void
    {
        $_SESSION['user_id'] = 1;
        $this->assertTrue(Middleware::isAuthenticated());
    }

    // ─── hasGlobalRole ───────────────────────────────────────

    public function testHasGlobalRoleReturnsTrueForMatchingRole(): void
    {
        $_SESSION['global_role'] = 'admin';
        $this->assertTrue(Middleware::hasGlobalRole(['admin', 'superadmin']));
    }

    public function testHasGlobalRoleReturnsFalseForNonMatchingRole(): void
    {
        $_SESSION['global_role'] = 'user';
        $this->assertFalse(Middleware::hasGlobalRole(['admin', 'superadmin']));
    }

    // ─── requireRole ─────────────────────────────────────────

    public function testRequireRoleReturnsFalseWhenNotAuthenticated(): void
    {
        $this->assertFalse(Middleware::requireRole(['admin']));
    }

    public function testRequireRoleReturnsTrueWhenAuthenticatedWithRole(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['global_role'] = 'admin';
        $this->assertTrue(Middleware::requireRole(['admin']));
    }

    public function testRequireRoleReturnsFalseWhenAuthenticatedWithWrongRole(): void
    {
        $_SESSION['user_id'] = 1;
        $_SESSION['global_role'] = 'user';
        $this->assertFalse(Middleware::requireRole(['admin']));
    }
}
