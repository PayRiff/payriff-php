<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\TestCase;

final class CardsTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testSaveSendsBodyAndIdempotencyKey(): void
    {
        $this->server->ok('POST', '/api/v3/cards/save', ['cardSaveId' => 'cs-1', 'status' => 'CREATED', 'amount' => 0.1]);

        $response = $this->server->client()->cards->save([
            'customerRef' => 'cust-1',
            'callbackUrl' => 'https://shop.az/cards/cb',
            'language' => 'AZ',
            'idempotencyKey' => 'idem-1',
        ]);

        self::assertSame('idem-1', $this->server->last()['headers']['x-idempotency-key']);
        self::assertSame(['customerRef' => 'cust-1', 'callbackUrl' => 'https://shop.az/cards/cb', 'language' => 'AZ'], $this->server->lastJson());
        self::assertSame('cs-1', $response['cardSaveId']);
    }

    public function testSaveRequiresCallbackUrl(): void
    {
        $this->expectExceptionMessage('callbackUrl is required');

        $this->server->client()->cards->save(['customerRef' => 'c']);
    }

    public function testGetSave(): void
    {
        $this->server->ok('GET', '/api/v3/cards/save/cs-1', ['cardSaveId' => 'cs-1', 'status' => 'VERIFIED', 'cardUuid' => 'card-1']);

        self::assertSame('card-1', $this->server->client()->cards->getSave('cs-1')['cardUuid']);
    }

    public function testListReturnsCardsForCustomer(): void
    {
        $this->server->ok('GET', '/api/v3/cards/save?customerRef=cust%201', [
            ['cardUuid' => 'card-1', 'cardBrand' => 'VISA'],
            ['cardUuid' => 'card-2', 'cardBrand' => 'MASTERCARD'],
        ]);

        $cards = $this->server->client()->cards->list('cust 1');

        self::assertSame(['card-1', 'card-2'], array_column($cards, 'cardUuid'));
    }

    public function testListReturnsEmptyArrayForNullPayload(): void
    {
        $this->server->ok('GET', '/api/v3/cards/save', null);

        self::assertSame([], $this->server->client()->cards->list('cust-1'));
    }

    public function testDeleteCallsCardEndpoint(): void
    {
        $this->server->ok('DELETE', '/api/v3/cards/card-1', true);

        $this->server->client()->cards->delete('card-1');

        self::assertSame(['DELETE', '/api/v3/cards/card-1'], [$this->server->last()['method'], $this->server->last()['url']]);
    }
}