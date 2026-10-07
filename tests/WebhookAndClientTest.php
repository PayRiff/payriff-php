<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Payriff;
use Payriff\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookAndClientTest extends TestCase
{
    private const CALLBACK = '{"payload":{"orderId":"ORD-1","invoiceUuid":"inv-1","amount":10.5,'
        . '"currencyType":"AZN","paymentStatus":"APPROVED","operationType":"PURCHASE","auto":false,'
        . '"createdDate":"2026-10-01T14:05:09.123","transactions":[{"status":"APPROVED"}]},'
        . '"code":"00000","message":"Operation performed successfully","route":"/dashboard","responseId":"http-nio-1"}';

    public static function callbacks(): array
    {
        return [[self::CALLBACK], [json_decode(self::CALLBACK, true)]];
    }

    #[DataProvider('callbacks')]
    public function testParsesCallback(string|array $body): void
    {
        $order = Webhook::parseOrderCallback($body);

        self::assertSame('ORD-1', $order['orderId']);
        self::assertSame('APPROVED', $order['paymentStatus']);
        self::assertSame('2026-10-01T14:05:09.123', $order['createdDate']);
        self::assertCount(1, $order['transactions']);
    }

    public static function notCallbacks(): array
    {
        return [[''], ['not json'], ['{}'], ['{"payload":null}'], ['{"payload":{"amount":1}}'], ['[]'], ['{"payload":[]}']];
    }

    #[DataProvider('notCallbacks')]
    public function testRejectsNonCallbackBodies(string $body): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not a Payriff order callback');

        Webhook::parseOrderCallback($body);
    }

    public function testAppKeyIsRequired(): void
    {
        $this->expectExceptionMessage('appKey is required');

        new Payriff('  ');
    }

    public function testExposesAllServices(): void
    {
        $payriff = new Payriff('k');

        foreach (['orders', 'payments', 'cards', 'transactions', 'payouts', 'invoices'] as $service) {
            self::assertIsObject($payriff->{$service});
        }
    }
}