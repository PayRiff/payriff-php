<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\CardData;
use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\TestCase;

final class PaymentsTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testDirectPayEncryptsCardAndSendsSecretKey(): void
    {
        $this->server->ok('POST', '/api/v3/directPay', [
            'orderId' => 'ORD-1', 'threeDS' => true, 'redirect' => true, 'redirectUrl' => 'https://acs.bank/3ds',
            'transactionResponse' => ['status' => 'CREATED', 'requestRrn' => 'req-1'],
        ]);

        $response = $this->server->client(cardEncryptionKey: CardTest::publicKeyBase64())->payments->directPay([
            'amount' => 1,
            'description' => 'Order #1',
            'callbackUrl' => 'https://shop.az/cb',
            'cardSave' => true,
            'requestRrn' => 'rrn-1',
            'card' => new CardData(pan: '4169741330151979', cardHolder: 'JOHN DOE', expiryMonth: '11', expiryYear: '2027', cvv: '123'),
        ]);

        $sent = $this->server->last();
        self::assertSame('rrn-1', $sent['headers']['x-request-rrn']);
        self::assertStringNotContainsString('4169741330151979', $sent['body']);
        self::assertStringNotContainsString('JOHN DOE', $sent['body']);
        $body = json_decode($sent['body'], true);
        $paymentData = $body['paymentData'];
        unset($body['paymentData']);
        self::assertSame(['amount' => 1, 'operation' => 'PURCHASE', 'currency' => 'AZN', 'description' => 'Order #1',
            'callbackUrl' => 'https://shop.az/cb'], $body);
        self::assertSame('DIRECT', $paymentData['paymentWay']);
        self::assertTrue($paymentData['cardSave']);
        self::assertSame(
            '{"pan":"4169741330151979","cardHolder":"JOHN DOE","expiryYear":"2027","expiryMonth":"11","cvv":"123"}',
            CardTest::decrypt($sent['headers']['x-secret-key'], $paymentData['encryptedMessage']),
        );
        self::assertSame('ORD-1', $response['orderId']);
        self::assertTrue($response['redirect']);
        self::assertSame('CREATED', $response['transactionResponse']['status']);
    }

    public function testDirectPayRequiresCardData(): void
    {
        $this->expectExceptionMessage('card must be a Payriff\CardData');

        $this->server->client()->payments->directPay(['amount' => 1, 'description' => 'x', 'card' => ['pan' => '4169741330151979']]);
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