<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests des fonctions helper.
 */
class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION = [];
        $_SERVER = array_merge($_SERVER, [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─── e() ──────────────────────────────────────────────────

    public function testEscapeHtml(): void
    {
        $this->assertSame('&lt;script&gt;', e('<script>'));
        $this->assertSame('&amp;', e('&'));
        $this->assertSame('Hello', e('Hello'));
        $this->assertSame('', e(''));
    }

    public function testEscapeNull(): void
    {
        $this->assertSame('', e(null));
    }

    public function testEscapeQuotes(): void
    {
        $this->assertSame('&quot;hello&quot;', e('"hello"'));
        $this->assertSame('&#039;hello&#039;', e("'hello'"));
    }

    // ─── get_client_ip ───────────────────────────────────────

    public function testGetClientIpFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $this->assertSame('192.168.1.100', get_client_ip());
    }

    public function testGetClientIpFromXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 172.16.0.1';
        $this->assertSame('10.0.0.1', get_client_ip());
    }

    public function testGetClientIpFromXRealIp(): void
    {
        $_SERVER['HTTP_X_REAL_IP'] = '10.0.0.2';
        $this->assertSame('10.0.0.2', get_client_ip());
    }

    public function testGetClientIpDockerGateway(): void
    {
        $_SERVER['REMOTE_ADDR'] = '172.18.0.1';
        $this->assertSame('127.0.0.1', get_client_ip());
    }

    public function testGetClientIpFallback(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        $this->assertSame('127.0.0.1', get_client_ip());
    }

    // ─── url() ───────────────────────────────────────────────

    public function testUrlBase(): void
    {
        putenv('APP_URL=http://localhost:8080');
        $this->assertSame('http://localhost:8080/', url());
    }

    public function testUrlWithPath(): void
    {
        putenv('APP_URL=http://localhost:8080');
        $this->assertSame('http://localhost:8080/login', url('login'));
        $this->assertSame('http://localhost:8080/login', url('/login'));
    }

    // ─── format_date ─────────────────────────────────────────

    public function testFormatDate(): void
    {
        $this->assertSame('01/01/2024 12:00', format_date('2024-01-01 12:00:00'));
    }

    public function testFormatDateCustomFormat(): void
    {
        $this->assertSame('2024-01-01', format_date('2024-01-01 12:00:00', 'Y-m-d'));
    }

    public function testFormatDateNull(): void
    {
        $this->assertSame('-', format_date(null));
        $this->assertSame('-', format_date(''));
    }

    // ─── time_ago ────────────────────────────────────────────

    public function testTimeAgoJustNow(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->assertStringContainsString('instant', time_ago($now));
    }

    public function testTimeAgoMinutes(): void
    {
        $date = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $this->assertStringContainsString('5 minute', time_ago($date));
    }

    public function testTimeAgoHours(): void
    {
        $date = date('Y-m-d H:i:s', strtotime('-3 hours'));
        $this->assertStringContainsString('3 heure', time_ago($date));
    }

    public function testTimeAgoDays(): void
    {
        $date = date('Y-m-d H:i:s', strtotime('-2 days'));
        $this->assertStringContainsString('2 jour', time_ago($date));
    }

    public function testTimeAgoNull(): void
    {
        $this->assertSame('-', time_ago(null));
        $this->assertSame('-', time_ago(''));
    }

    // ─── is_authenticated / current_user_id / current_username / current_avatar / current_global_role ──

    public function testIsAuthenticatedFalse(): void
    {
        $this->assertFalse(is_authenticated());
    }

    public function testIsAuthenticatedTrue(): void
    {
        $_SESSION['user_id'] = 42;
        $this->assertTrue(is_authenticated());
    }

    public function testCurrentUserId(): void
    {
        $this->assertNull(current_user_id());
        $_SESSION['user_id'] = 42;
        $this->assertSame(42, current_user_id());
    }

    public function testCurrentUsername(): void
    {
        $this->assertSame('', current_username());
        $_SESSION['username'] = 'alice';
        $this->assertSame('alice', current_username());
    }

    public function testCurrentAvatar(): void
    {
        $this->assertSame('', current_avatar());
        $_SESSION['avatar'] = '/img/avatar.png';
        $this->assertSame('/img/avatar.png', current_avatar());
    }

    public function testCurrentGlobalRole(): void
    {
        $this->assertSame('user', current_global_role());
        $_SESSION['global_role'] = 'admin';
        $this->assertSame('admin', current_global_role());
    }
}
