<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\TestCase;

final class PaymentsTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testAutoPaySendsExplicitCurrencyAndOneClickFlag(): void
    {
        $this->server->ok('POST', '/api/v3/autoPay', [
            'orderId' => 'ORD-2', 'amount' => 3, 'paymentStatus' => 'APPROVED', 'auto' => true,
            'transactionResponseDto' => ['threeDS' => false],
        ]);

        $response = $this->server->client()->payments->autoPay([
            'cardUuid' => 'card-uuid-1',
            'amount' => 3,
            'description' => 'Subscription',
            'oneClickPayment' => true,
            'requestRrn' => 'rrn-2',
        ]);

        self::assertSame('rrn-2', $this->server->last()['headers']['x-request-rrn']);
        self::assertSame(['cardUuid' => 'card-uuid-1', 'amount' => 3, 'operation' => 'PURCHASE', 'currency' => 'AZN',
            'description' => 'Subscription', 'isOneCLickPayment' => true], $this->server->lastJson());
        self::assertSame('APPROVED', $response['paymentStatus']);
        self::assertFalse($response['transactionResponseDto']['threeDS']);
    }
}