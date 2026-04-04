<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\JWT;

/**
 * Tests de la classe JWT.
 */
class JWTTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_KEY=test_secret_key_for_jwt');
    }

    public function testEncodeReturnsString(): void
    {
        $token = JWT::encode(['user_id' => 1, 'username' => 'alice']);
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testEncodeReturnsThreeParts(): void
    {
        $token = JWT::encode(['user_id' => 1]);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testDecodeReturnsPayload(): void
    {
        $token = JWT::encode(['user_id' => 42, 'username' => 'bob']);
        $payload = JWT::decode($token);

        $this->assertIsArray($payload);
        $this->assertSame(42, $payload['user_id']);
        $this->assertSame('bob', $payload['username']);
    }

    public function testDecodeReturnsNullForInvalidToken(): void
    {
        $this->assertNull(JWT::decode('invalid.token.here'));
    }

    public function testDecodeReturnsNullForTamperedToken(): void
    {
        $token = JWT::encode(['user_id' => 1]);
        // Modifier la signature
        $parts = explode('.', $token);
        $parts[2] = 'tampered_signature';
        $tampered = implode('.', $parts);

        $this->assertNull(JWT::decode($tampered));
    }

    public function testDecodeReturnsNullForExpiredToken(): void
    {
        // TTL de -1 seconde = expiré immédiatement
        $token = JWT::encode(['user_id' => 1], -1);
        $this->assertNull(JWT::decode($token));
    }

    public function testDecodeReturnsNullForMalformedString(): void
    {
        $this->assertNull(JWT::decode('not-a-jwt'));
    }

    public function testPayloadContainsIatAndExp(): void
    {
        $token = JWT::encode(['user_id' => 1], 3600);
        $payload = JWT::decode($token);

        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertSame($payload['iat'] + 3600, $payload['exp']);
    }
}
