<?php

declare(strict_types=1);

namespace Payriff;

use Payriff\Internal\CardEncryptor;
use Payriff\Internal\Transport;
use Payriff\Service\CardsService;
use Payriff\Service\InvoicesService;
use Payriff\Service\OrdersService;
use Payriff\Service\PaymentsService;
use Payriff\Service\PayoutsService;
use Payriff\Service\TransactionsService;

final class Payriff
{
    public const VERSION = '0.1.0';
    public const PRODUCTION_URL = 'https://api.payriff.com';

    public readonly OrdersService $orders;
    public readonly PaymentsService $payments;
    public readonly CardsService $cards;
    public readonly TransactionsService $transactions;
    public readonly PayoutsService $payouts;
    public readonly InvoicesService $invoices;

    public function __construct(
        string $appKey,
        ?string $merchantId = null,
        float $timeout = 60.0,
        float $connectTimeout = 10.0,
        ?string $cardEncryptionKey = null,
        string $baseUrl = self::PRODUCTION_URL,
    ) {
        if (trim($appKey) === '') {
            throw new \InvalidArgumentException('appKey is required');
        }
        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new \InvalidArgumentException('timeouts must be positive numbers of seconds');
        }
        $transport = new Transport($baseUrl, $appKey, $merchantId, $timeout, $connectTimeout);
        $encryptor = new CardEncryptor($cardEncryptionKey ?? CardEncryptor::PRODUCTION_CARD_ENCRYPTION_KEY);
        $this->orders = new OrdersService($transport);
        $this->payments = new PaymentsService($transport, $encryptor);
        $this->cards = new CardsService($transport);
        $this->transactions = new TransactionsService($transport);
        $this->payouts = new PayoutsService($transport);
        $this->invoices = new InvoicesService($transport);
    }
}