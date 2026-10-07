<?php

declare(strict_types=1);

namespace Payriff\Tests;

use Payriff\Tests\Support\MockServer;
use PHPUnit\Framework\TestCase;

final class InvoicesTest extends TestCase
{
    private MockServer $server;

    protected function setUp(): void
    {
        $this->server = MockServer::get();
    }

    public function testCreateSendsMerchantEnvelope(): void
    {
        $this->server->ok('POST', '/api/v2/invoices', ['invoiceUuid' => 'inv-uuid-1', 'invoiceStatus' => 'PENDING']);

        $invoice = $this->server->client()->invoices->create([
            'amount' => 15,
            'language' => 'AZ',
            'fullName' => 'JOHN DOE',
            'phoneNumber' => '+994501234567',
            'description' => 'Consultation',
            'expireDate' => new \DateTimeImmutable('2026-10-08 23:59'),
            'approveUrl' => 'https://shop.az/ok',
            'sendSms' => false,
            'metadata' => ['bookingRef' => 'B-77'],
        ]);

        self::assertSame([
            'merchant' => 'ES1000000',
            'body' => [
                'amount' => 15, 'currencyType' => 'AZN', 'languageType' => 'AZ', 'fullName' => 'JOHN DOE',
                'phoneNumber' => '+994501234567', 'description' => 'Consultation', 'expireDate' => '2026-10-08T23:59:00',
                'approveURL' => 'https://shop.az/ok', 'sendSms' => false, 'metadata' => ['bookingRef' => 'B-77'],
            ],
        ], $this->server->lastJson());
        self::assertSame('PENDING', $invoice['invoiceStatus']);
    }

    public function testAmountRequiredUnlessDynamic(): void
    {
        $this->server->ok('POST', '/api/v2/invoices', ['invoiceUuid' => 'inv-2']);
        try {
            $this->server->client()->invoices->create([]);
            self::fail('expected exception');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('amount is required', $e->getMessage());
        }

        $this->server->client()->invoices->create(['amountDynamic' => true]);

        self::assertSame(['amountDynamic' => true, 'currencyType' => 'AZN'], $this->server->lastJson()['body']);
    }

    public function testGetSendsInvoiceUuidInEnvelope(): void
    {
        $this->server->ok('POST', '/api/v2/get-invoice', ['invoiceUuid' => 'inv-uuid-1', 'invoiceStatus' => 'COMPLETE']);

        $details = $this->server->client()->invoices->get('inv-uuid-1');

        self::assertSame(['merchant' => 'ES1000000', 'body' => ['uuid' => 'inv-uuid-1']], $this->server->lastJson());
        self::assertSame('COMPLETE', $details['invoiceStatus']);
    }
}