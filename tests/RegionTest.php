<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Region;
use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\TestCase;

final class RegionTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testRegionsMapToBaseUrlAndCurrency(): void
    {
        self::assertSame(['https://api.payriff.com', 'AZN'], [Region::AZERBAIJAN->baseUrl(), Region::AZERBAIJAN->currency()]);
        self::assertSame(['https://api.gccpay.sa', 'SAR'], [Region::SAUDI->baseUrl(), Region::SAUDI->currency()]);
    }

    public function testSaudiDefaultsToSar(): void
    {
        $payriff = $this->server->client(region: Region::SAUDI);

        $this->server->ok('POST', '/api/v3/orders', ['orderId' => 'ORD-1']);
        $payriff->orders->create(['amount' => 1]);
        self::assertSame('SAR', $this->server->lastJson()['currency']);

        $this->server->ok('POST', '/api/v3/autoPay', ['orderId' => 'ORD-2']);
        $payriff->payments->autoPay(['cardUuid' => 'card-1', 'amount' => 1, 'description' => 'x']);
        self::assertSame('SAR', $this->server->lastJson()['currency']);

        $this->server->ok('POST', '/api/v2/invoices', ['invoiceUuid' => 'inv-1']);
        $payriff->invoices->create(['amount' => 1]);
        self::assertSame('SAR', $this->server->lastJson()['body']['currencyType']);
    }

    public function testExplicitCurrencyWinsOverRegion(): void
    {
        $this->server->ok('POST', '/api/v3/orders', ['orderId' => 'ORD-1']);

        $this->server->client(region: Region::SAUDI)->orders->create(['amount' => 1, 'currency' => 'USD']);

        self::assertSame('USD', $this->server->lastJson()['currency']);
    }
}