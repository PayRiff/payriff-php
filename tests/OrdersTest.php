<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrdersTest extends TestCase
{
    private const ORDER_INFO = [
        'orderId' => 'ORD-1',
        'amount' => 10.5,
        'currencyType' => 'AZN',
        'paymentStatus' => 'APPROVED',
        'createdDate' => '2026-10-01T14:05:09.123456',
        'transactions' => [[
            'uuid' => '6f1c2a4e-0b7d-4c3e-9a51-2d8e7f6b1c90',
            'status' => 'APPROVED',
            'cardDetails' => ['maskedPan' => '416974******1979', 'brand' => 'VISA'],
            'installment' => ['type' => 'BIRKART', 'period' => 'PERIOD_3'],
        ]],
    ];

    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testCreateSendsBodyAndRrnHeader(): void
    {
        $this->server->ok('POST', '/api/v3/orders', [
            'orderId' => 'ORD-1', 'paymentUrl' => 'https://pay.payriff.com/ORD-1', 'transactionId' => 77,
            'comissionRate' => 2.5, 'amount' => 10, 'fee' => 0.25, 'totalAmount' => 10.25,
        ]);

        $response = $this->server->client()->orders->create([
            'amount' => '10.00',
            'language' => 'AZ',
            'description' => 'Order #1',
            'callbackUrl' => 'https://shop.az/cb',
            'installment' => ['type' => 'BIRKART', 'period' => 'PERIOD_3'],
            'metadata' => ['cartId' => 'c-9'],
            'requestRrn' => 'rrn-1',
        ]);

        $sent = $this->server->last();
        self::assertSame('app-key', $sent['headers']['authorization']);
        self::assertSame('rrn-1', $sent['headers']['x-request-rrn']);
        self::assertSame('application/json', $sent['headers']['content-type']);
        self::assertSame(
            '{"amount":10.0,"currency":"AZN","language":"AZ","operation":"PURCHASE","description":"Order #1",'
            . '"callbackUrl":"https://shop.az/cb","installment":{"type":"BIRKART","period":"PERIOD_3"},"metadata":{"cartId":"c-9"}}',
            $sent['body'],
        );
        self::assertSame([
            'orderId' => 'ORD-1', 'paymentUrl' => 'https://pay.payriff.com/ORD-1', 'transactionId' => 77,
            'amount' => 10, 'fee' => 0.25, 'totalAmount' => 10.25, 'commissionRate' => 2.5,
        ], $response);
    }

    public function testCreateOmitsRrnHeaderAndEmptyMaps(): void
    {
        $this->server->ok('POST', '/api/v3/orders', ['orderId' => 'ORD-1']);

        $this->server->client()->orders->create(['amount' => 1, 'metadata' => []]);

        self::assertArrayNotHasKey('x-request-rrn', $this->server->last()['headers']);
        self::assertSame(['amount' => 1, 'currency' => 'AZN', 'operation' => 'PURCHASE'], $this->server->lastJson());
    }

    public function testAmountKeepsDecimalPrecision(): void
    {
        $this->server->ok('POST', '/api/v3/orders', ['orderId' => 'ORD-1']);

        $this->server->client()->orders->create(['amount' => '10.10']);

        self::assertStringContainsString('"amount":10.1,', $this->server->last()['body']);
    }

    public function testCreateRejectsMissingOrInvalidAmount(): void
    {
        foreach ([[[], 'amount is required'], [['amount' => 'ten'], 'amount must be a number']] as [$params, $message]) {
            try {
                $this->server->client()->orders->create($params);
                self::fail('expected exception');
            } catch (\InvalidArgumentException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
        self::assertSame([], $this->server->requests());
    }

    public static function lookups(): array
    {
        return [
            ['get', 'ORD-1', '/api/v3/orders/ORD-1'],
            ['getStatus', 'ORD-1', '/api/v3/orders/ORD-1/status'],
            ['getByRequestRrn', 'rrn 1/2', '/api/v3/orders/rrn%201%2F2/rrn'],
        ];
    }

    #[DataProvider('lookups')]
    public function testLookupsReturnOrderInfo(string $method, string $id, string $path): void
    {
        $this->server->ok('GET', $path, self::ORDER_INFO);

        $order = $this->server->client()->orders->{$method}($id);

        self::assertSame($path, $this->server->last()['url']);
        self::assertSame(self::ORDER_INFO, $order);
    }

    public function testExpireSendsOrderIdAsQuery(): void
    {
        $this->server->ok('PATCH', '/api/v3/expire-status', null);

        $this->server->client()->orders->expire('ORD-1');

        self::assertSame('/api/v3/expire-status?orderId=ORD-1', $this->server->last()['url']);
    }

    public function testRefundSendsBody(): void
    {
        $this->server->ok('POST', '/api/v3/refund', null);

        $this->server->client()->orders->refund(['orderId' => 'ORD-1', 'amount' => 5, 'refundReason' => 'damaged']);

        self::assertSame(['orderId' => 'ORD-1', 'amount' => 5, 'refundReason' => 'damaged'], $this->server->lastJson());
    }

    public function testCompleteSendsBody(): void
    {
        $this->server->ok('POST', '/api/v3/complete', null);

        $this->server->client()->orders->complete(['orderId' => 'ORD-1', 'amount' => 7.5]);

        self::assertSame(['orderId' => 'ORD-1', 'amount' => 7.5], $this->server->lastJson());
    }

    public function testRefundRequiresOrderId(): void
    {
        $this->expectExceptionMessage('orderId is required');

        $this->server->client()->orders->refund([]);
    }
}