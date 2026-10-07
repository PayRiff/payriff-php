<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Exception\ApiException;
use Payriff\Exception\AuthenticationException;
use Payriff\Exception\InsufficientBalanceException;
use Payriff\Exception\PayoutLimitException;
use Payriff\Exception\PayriffConnectionException;
use Payriff\Exception\RequestRejectedException;
use Payriff\Exception\ValidationException;
use Payriff\Payriff;
use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testUnwrapsPayloadOnSuccess(): void
    {
        $this->server->ok('GET', '/api/v3/orders/ORD-1', ['orderId' => 'ORD-1', 'paymentStatus' => 'APPROVED']);

        $order = $this->server->client()->orders->get('ORD-1');

        self::assertSame(['orderId' => 'ORD-1', 'paymentStatus' => 'APPROVED'], $order);
    }

    public function testSendsAuthAndClientHeaders(): void
    {
        $this->server->ok('GET', '/api/v3/orders/ORD-1', ['orderId' => 'ORD-1']);

        $this->server->client()->orders->get('ORD-1');

        $headers = $this->server->last()['headers'];
        self::assertSame('app-key', $headers['authorization']);
        self::assertSame('payriff-php/' . Payriff::VERSION, $headers['user-agent']);
        self::assertSame('application/json', $headers['accept']);
        self::assertArrayNotHasKey('content-type', $headers);
    }

    public function testEncodesPathSegmentsAndQuery(): void
    {
        $this->server->ok('PATCH', '/api/v3/expire-status', null);

        $this->server->client()->orders->expire('ORD 1/2');

        self::assertSame('PATCH', $this->server->last()['method']);
        self::assertSame('/api/v3/expire-status?orderId=ORD%201%2F2', $this->server->last()['url']);
    }

    public function testRejectsBlankPathSegmentBeforeSending(): void
    {
        try {
            $this->server->client()->orders->get(' ');
            self::fail('expected exception');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('orderId must not be blank', $e->getMessage());
        }
        self::assertSame([], $this->server->requests());
    }

    public function testMerchantEnvelopeRequiresMerchantId(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('merchantId must be configured on Payriff for this operation');

        $this->server->client(merchantId: null)->invoices->get('inv-1');
    }

    public static function failures(): array
    {
        return [
            [200, '15000', ApiException::class],
            [401, '14010', AuthenticationException::class],
            [401, '14013', AuthenticationException::class],
            [200, '14014', AuthenticationException::class],
            [401, '14015', AuthenticationException::class],
            [400, '15400', ValidationException::class],
            [400, '99999', ValidationException::class],
            [403, '99999', AuthenticationException::class],
            [402, '01000', RequestRejectedException::class],
            [402, '01200', InsufficientBalanceException::class],
            [402, '01300', PayoutLimitException::class],
            [402, '01400', PayoutLimitException::class],
            [402, '01500', PayoutLimitException::class],
            [500, '15000', ApiException::class],
            [503, '15000', ApiException::class],
        ];
    }

    #[DataProvider('failures')]
    public function testMapsFailuresToTypedExceptions(int $status, string $code, string $class): void
    {
        $this->server->stub('GET', '/api/v3/orders/ORD-1', $status, body: [
            'code' => $code, 'message' => 'Failure reason', 'responseId' => 'resp-1',
        ]);

        try {
            $this->server->client()->orders->get('ORD-1');
            self::fail('expected exception');
        } catch (ApiException $e) {
            self::assertSame($class, $e::class);
            self::assertSame('Failure reason', $e->getMessage());
            self::assertSame($status, $e->getHttpStatus());
            self::assertSame($code, $e->getResultCode());
            self::assertSame('resp-1', $e->getResponseId());
        }
    }

    public function testMissingMessageFallsBackToHttpStatus(): void
    {
        $this->server->stub('GET', '/api/v3/orders/ORD-1', 500, body: ['code' => '15000']);

        $this->expectExceptionMessage('Payriff request failed (HTTP 500)');

        $this->server->client()->orders->get('ORD-1');
    }

    public function testNonJsonErrorBodyKeepsStatusAndTruncates(): void
    {
        $this->server->stub('GET', '/api/v3/orders/ORD-1', 502, ['Content-Type' => 'text/html'], str_repeat('x', 600));

        try {
            $this->server->client()->orders->get('ORD-1');
            self::fail('expected exception');
        } catch (ApiException $e) {
            self::assertSame(502, $e->getHttpStatus());
            self::assertNull($e->getResultCode());
            self::assertSame(str_repeat('x', 500) . '...', $e->getMessage());
        }
    }

    public function testSuccessStatusWithoutEnvelopeIsRejected(): void
    {
        $this->server->stub('GET', '/api/v3/orders/ORD-1', body: ['orderId' => 'ORD-1']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unexpected response from Payriff');

        $this->server->client()->orders->get('ORD-1');
    }

    public function testRedirectsAreNotFollowed(): void
    {
        $this->server->stub('GET', '/api/v3/orders/ORD-1', 302, ['Location' => '/api/v3/orders/OTHER'], [
            'code' => '15000', 'message' => 'Redirect',
        ]);

        try {
            $this->server->client()->orders->get('ORD-1');
            self::fail('expected exception');
        } catch (ApiException $e) {
            self::assertSame('Redirect', $e->getMessage());
        }
        self::assertCount(1, $this->server->requests());
    }

    public function testDownloadReturnsBytes(): void
    {
        $this->server->stub('GET', '/api/v3/acquiring/receipt/ORD-1', headers: ['Content-Type' => 'application/pdf'], body: '%PDF-1.7');

        self::assertSame('%PDF-1.7', $this->server->client()->orders->downloadReceipt('ORD-1'));
        self::assertSame('application/pdf, application/json', $this->server->last()['headers']['accept']);
    }

    public function testDownloadMapsEnvelopeError(): void
    {
        $this->server->stub('GET', '/api/v3/acquiring/receipt/ORD-1', 400, body: ['code' => '15400', 'message' => 'Receipt not found']);

        $this->expectException(ValidationException::class);

        $this->server->client()->orders->downloadReceipt('ORD-1');
    }

    public function testConnectionFailureRaisesConnectionException(): void
    {
        try {
            (new Payriff('app-key', baseUrl: 'http://127.0.0.1:1'))->orders->get('ORD-1');
            self::fail('expected exception');
        } catch (PayriffConnectionException $e) {
            self::assertSame(0, $e->getHttpStatus());
        }
    }

    public function testTimeoutRaisesConnectionException(): void
    {
        $this->expectException(PayriffConnectionException::class);

        (new Payriff('app-key', connectTimeout: 0.05, baseUrl: 'http://10.255.255.1'))->orders->get('ORD-1');
    }
}