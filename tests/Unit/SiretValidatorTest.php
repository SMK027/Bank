<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\SiretValidator;

class SiretValidatorTest extends TestCase
{
    public function testValidFormatCorrectSiret(): void
    {
        // SIRET de La Poste (valide au sens Luhn)
        $this->assertTrue(SiretValidator::isValidFormat('35600000000048'));
    }

    public function testValidFormatWithSpaces(): void
    {
        $this->assertTrue(SiretValidator::isValidFormat('356 000 000 00048'));
    }

    public function testInvalidFormatTooShort(): void
    {
        $this->assertFalse(SiretValidator::isValidFormat('1234567890'));
    }

    public function testInvalidFormatTooLong(): void
    {
        $this->assertFalse(SiretValidator::isValidFormat('123456789012345'));
    }

    public function testInvalidFormatLetters(): void
    {
        $this->assertFalse(SiretValidator::isValidFormat('ABCDEFGHIJKLMN'));
    }

    public function testInvalidFormatBadLuhn(): void
    {
        // Mauvais dernier chiffre
        $this->assertFalse(SiretValidator::isValidFormat('35600000000049'));
    }

    public function testInvalidFormatEmpty(): void
    {
        $this->assertFalse(SiretValidator::isValidFormat(''));
    }
}
