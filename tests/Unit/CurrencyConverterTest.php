<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Models\ExchangeRate;
use App\Services\CurrencyConverter;
use PHPUnit\Framework\TestCase;
use Tests\TestDatabase;

class CurrencyConverterTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::make();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testIdentityConversionReturnsSameAmount(): void
    {
        $conv = new CurrencyConverter(new ExchangeRate(), function () {
            $this->fail('L\'API ne doit pas être appelée pour une conversion identique.');
        });
        $r = $conv->convert(100.0, 'EUR', 'EUR');
        $this->assertSame(100.0, $r['amount']);
        $this->assertSame(1.0, $r['rate']);
    }

    public function testFetchAndCacheRate(): void
    {
        $calls = 0;
        $fetcher = function (string $url) use (&$calls) {
            $calls++;
            $this->assertStringContainsString('/EUR', $url);
            return [
                'result'    => 'success',
                'base_code' => 'EUR',
                'rates'     => ['USD' => 1.10, 'GBP' => 0.85],
            ];
        };
        $rateModel = new ExchangeRate();
        $conv = new CurrencyConverter($rateModel, $fetcher);

        $r = $conv->convert(100.0, 'EUR', 'USD');
        $this->assertSame(110.0, $r['amount']);
        $this->assertSame(1.10, $r['rate']);
        $this->assertSame(1, $calls);

        // 2e appel : doit lire le cache, sans appeler l'API
        $r2 = $conv->convert(50.0, 'EUR', 'USD');
        $this->assertSame(55.0, $r2['amount']);
        $this->assertSame(1, $calls, 'Le cache doit éviter un second appel API.');

        $cached = $rateModel->findPair('EUR', 'USD');
        $this->assertNotNull($cached);
        $this->assertEqualsWithDelta(1.10, (float) $cached['rate'], 0.0001);
    }

    public function testFallbackOnApiFailureUsesStaleCache(): void
    {
        $rateModel = new ExchangeRate();
        // Ajout d'un cache périmé
        $rateModel->upsertPair('EUR', 'USD', 1.05, date('Y-m-d H:i:s', time() - 7 * 24 * 3600));

        $conv = new CurrencyConverter($rateModel, function () {
            throw new \RuntimeException('réseau down');
        });
        $r = $conv->convert(200.0, 'EUR', 'USD');
        $this->assertSame(210.0, $r['amount']);
        $this->assertSame(1.05, $r['rate']);
    }

    public function testThrowsWhenNoCacheAndApiFails(): void
    {
        $conv = new CurrencyConverter(new ExchangeRate(), function () {
            throw new \RuntimeException('down');
        });
        $this->expectException(\RuntimeException::class);
        $conv->convert(10.0, 'EUR', 'JPY');
    }

    public function testRejectsUnsupportedTargetCurrency(): void
    {
        $conv = new CurrencyConverter(new ExchangeRate(), function () {
            return [
                'result' => 'success',
                'rates'  => ['USD' => 1.10],
            ];
        });
        $this->expectException(\RuntimeException::class);
        $conv->convert(10.0, 'EUR', 'XYZ');
    }
}
