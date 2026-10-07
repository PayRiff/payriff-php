<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransactionsTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testListSendsFilterAndRenamesSnakeCaseFields(): void
    {
        $this->server->ok('GET', '/api/v3/transactions?status=APPROVED&from=01.09.2026&to=30.09.2026&page=1&offset=20', [
            'content' => [[
                'id' => 5, 'orderId' => 'ORD-1', 'amount' => 10, 'paymentStatus' => 'APPROVED',
                'card_brand' => 'VISA', 'payment_way' => 'DIRECT', 'extra_payment' => 0.5,
            ]],
            'totalElements' => 41, 'totalPages' => 3, 'number' => 1, 'size' => 20, 'first' => false, 'last' => false,
        ]);

        $page = $this->server->client()->transactions->list([
            'status' => 'APPROVED',
            'from' => new \DateTimeImmutable('2026-09-01'),
            'to' => '2026-09-30',
            'page' => 1,
            'size' => 20,
        ]);

        self::assertSame(41, $page['totalElements']);
        self::assertEquals([
            'id' => 5, 'orderId' => 'ORD-1', 'amount' => 10, 'paymentStatus' => 'APPROVED',
            'cardBrand' => 'VISA', 'paymentWay' => 'DIRECT', 'extraPayment' => 0.5,
        ], $page['content'][0]);
    }

    public function testDefaultFilterRequestsFirstPage(): void
    {
        $this->server->ok('GET', '/api/v3/transactions', ['content' => [], 'totalElements' => 0]);

        $this->server->client()->transactions->list();

        self::assertSame('/api/v3/transactions?page=0&offset=10', $this->server->last()['url']);
    }

    public static function badSizes(): array
    {
        return [[0], [21], [-1], ['5']];
    }

    #[DataProvider('badSizes')]
    public function testSizeMustBeWithinServerCap(mixed $size): void
    {
        $this->expectExceptionMessage('size must be 1-20');

        $this->server->client()->transactions->list(['size' => $size]);
    }

    public function testInvalidDateStringIsRejected(): void
    {
        $this->expectExceptionMessage('from must be a DateTimeInterface or a YYYY-MM-DD string');

        $this->server->client()->transactions->list(['from' => '01.09.2026']);
    }
}