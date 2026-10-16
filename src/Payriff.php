<?php

declare(strict_types=1);

namespace Payriff;

use Payriff\Internal\Transport;
use Payriff\Service\CardsService;
use Payriff\Service\InvoicesService;
use Payriff\Service\OrdersService;
use Payriff\Service\PaymentsService;
use Payriff\Service\PayoutsService;
use Payriff\Service\TransactionsService;

final class Payriff
{
    public const VERSION = '0.2.0';

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
        ?string $baseUrl = null,
        Region $region = Region::AZERBAIJAN,
    ) {
        if (trim($appKey) === '') {
            throw new \InvalidArgumentException('appKey is required');
        }
        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new \InvalidArgumentException('timeouts must be positive numbers of seconds');
        }
        $transport = new Transport($baseUrl ?? $region->baseUrl(), $appKey, $merchantId, $timeout, $connectTimeout);
        $currency = $region->currency();
        $this->orders = new OrdersService($transport, $currency);
        $this->payments = new PaymentsService($transport, $currency);
        $this->cards = new CardsService($transport);
        $this->transactions = new TransactionsService($transport);
        $this->payouts = new PayoutsService($transport);
        $this->invoices = new InvoicesService($transport, $currency);
    }
}