<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Models\ApiClient;
use App\Models\PaymentCard;
use Tests\TestDatabase;

class PaymentCardTest extends TestCase
{
    private PaymentCard $cardModel;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->cardModel = new PaymentCard();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testGenerateCardNumberRespectsLast4AndLuhn(): void
    {
        $number = $this->cardModel->generateCardNumber('1234');
        $this->assertSame(16, strlen($number));
        $this->assertSame('1234', substr($number, -4));
        $this->assertTrue(PaymentCard::isValidLuhn($number));
    }

    public function testInvalidLast4Throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->cardModel->generateCardNumber('12a4');
    }

    public function testMaskHidesMiddle(): void
    {
        $masked = PaymentCard::mask('4242123456781234');
        $this->assertSame('4242 **** **** 1234', $masked);
    }

    public function testNormalizeStripsSpacesAndDashes(): void
    {
        $this->assertSame('4242123456781234', PaymentCard::normalize('4242 1234-5678 1234'));
    }

    public function testFindByNumber(): void
    {
        $number = $this->cardModel->generateCardNumber('5555');
        $id = $this->cardModel->create([
            'user_id'     => 1,
            'account_id'  => 1,
            'card_number' => $number,
            'last4'       => '5555',
            'label'       => 'Test',
            'status'      => 'active',
        ]);

        $found = $this->cardModel->findByNumber($number);
        $this->assertNotNull($found);
        $this->assertSame($id, (int) $found['id']);
    }

    public function testUserHasLast4(): void
    {
        $number = $this->cardModel->generateCardNumber('4321');
        $this->cardModel->create([
            'user_id'     => 7,
            'account_id'  => 1,
            'card_number' => $number,
            'last4'       => '4321',
            'label'       => '',
            'status'      => 'active',
        ]);

        $this->assertTrue($this->cardModel->userHasLast4(7, '4321'));
        $this->assertFalse($this->cardModel->userHasLast4(7, '0000'));
        $this->assertFalse($this->cardModel->userHasLast4(8, '4321'));
    }
}

class ApiClientTest extends TestCase
{
    private ApiClient $clientModel;

    protected function setUp(): void
    {
        TestDatabase::make();
        $this->clientModel = new ApiClient();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testProvisionCreatesClientWithSecret(): void
    {
        $creds = $this->clientModel->provision('Boutique X');

        $this->assertGreaterThan(0, $creds['id']);
        $this->assertStringStartsWith('pk_', $creds['api_key']);
        $this->assertStringStartsWith('sk_', $creds['api_secret']);

        $client = $this->clientModel->find($creds['id']);
        $this->assertSame('active', $client['status']);
        // Le secret n'est jamais stocké en clair
        $this->assertNotSame($creds['api_secret'], $client['api_secret_hash']);
    }

    public function testAuthenticateAcceptsValidCredentials(): void
    {
        $creds = $this->clientModel->provision('Boutique Y');

        $client = $this->clientModel->authenticate($creds['api_key'], $creds['api_secret']);
        $this->assertNotNull($client);
        $this->assertSame('Boutique Y', $client['name']);
    }

    public function testAuthenticateRejectsBadSecret(): void
    {
        $creds = $this->clientModel->provision('Boutique Z');

        $client = $this->clientModel->authenticate($creds['api_key'], 'sk_wrong');
        $this->assertNull($client);
    }

    public function testAuthenticateRejectsRevokedClient(): void
    {
        $creds = $this->clientModel->provision('Boutique W');
        $this->clientModel->revoke($creds['id']);

        $client = $this->clientModel->authenticate($creds['api_key'], $creds['api_secret']);
        $this->assertNull($client);
    }
}
