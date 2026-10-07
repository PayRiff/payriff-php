<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Exception\InsufficientBalanceException;
use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\TestCase;

final class PayoutsTest extends TestCase
{
    private const PAYOUT = [
        'transferAmount' => 25,
        'description' => 'Refund to customer',
        'fullName' => 'JOHN DOE',
        'finCode' => '1AB2C3D',
        'cardPan' => '4169741330151979',
        'requestRrn' => 'po-1',
    ];

    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testCreateWrapsBodyInMerchantEnvelope(): void
    {
        $this->server->ok('POST', '/api/v3/payout', [
            '_final' => 'true', 'state' => 'SUCCESS', 'currentDepositBalance' => 975, 'walletHistoryId' => 321,
        ]);

        $result = $this->server->client()->payouts->create(self::PAYOUT + ['idempotencyKey' => 'idem-po-1']);

        self::assertSame('idem-po-1', $this->server->last()['headers']['x-idempotency-key']);
        self::assertSame(['merchant' => 'ES1000000', 'body' => self::PAYOUT], $this->server->lastJson());
        self::assertSame(['state' => 'SUCCESS', 'currentDepositBalance' => 975, 'walletHistoryId' => 321, 'finalState' => 'true'], $result);
    }

    public function testCreateMapsInsufficientBalance(): void
    {
        $this->server->stub('POST', '/api/v3/payout', 402, body: ['code' => '01200', 'message' => 'Insufficient wallet balance']);

        $this->expectException(InsufficientBalanceException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $this->server->client()->payouts->create(self::PAYOUT);
    }

    public function testAmountBelowMinimumIsRejected(): void
    {
        try {
            $this->server->client()->payouts->create(['transferAmount' => '0.99'] + self::PAYOUT);
            self::fail('expected exception');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('transferAmount must be at least 1', $e->getMessage());
        }
        self::assertSame([], $this->server->requests());
    }

    public function testGetByRequestRrn(): void
    {
        $this->server->ok('GET', '/api/v3/payout/info/po-1', ['state' => 'IN_PROGRESS', 'formattedDate' => '01.10.2026 10:00:00']);

        self::assertSame('IN_PROGRESS', $this->server->client()->payouts->getByRequestRrn('po-1')['state']);
    }

    public function testCheckCardholderNormalizesPan(): void
    {
        $this->server->ok('POST', '/api/v3/payout/check-cardholder', 'J*** D**');

        self::assertSame('J*** D**', $this->server->client()->payouts->checkCardholder('4169 7413 3015 1979'));
        self::assertSame(['cardPan' => '4169741330151979'], $this->server->lastJson());
    }

    public function testCheckCardholderRejectsShortPan(): void
    {
        $this->expectExceptionMessage('cardPan must be a 16-digit number');

        $this->server->client()->payouts->checkCardholder('4169');
    }

    public function testListSendsFilter(): void
    {
        $this->server->ok('GET', '/api/v3/payouts?status=SUCCESS&page=0&offset=10', [
            'content' => [['id' => 1, 'requestRrn' => 'po-1', 'state' => 'SUCCESS']], 'totalElements' => 1,
        ]);

        $page = $this->server->client()->payouts->list(['status' => 'SUCCESS']);

        self::assertSame(['po-1'], array_column($page['content'], 'requestRrn'));
    }

    public function testDownloadReceiptReturnsPdf(): void
    {
        $this->server->stub('GET', '/api/v3/payout/receipt/po-1', headers: ['Content-Type' => 'application/pdf'], body: '%PDF-');

        self::assertSame('%PDF-', $this->server->client()->payouts->downloadReceipt('po-1'));
    }
}