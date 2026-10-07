<?php

declare(strict_types=1);

namespace Payriff\Service;

use Payriff\Internal\Params;
use Payriff\Internal\Transport;

final class TransactionsService
{
    private const RENAMES = [
        'extra_payment' => 'extraPayment',
        'card_brand' => 'cardBrand',
        'payment_route' => 'paymentRoute',
        'payment_way' => 'paymentWay',
    ];

    public function __construct(private readonly Transport $transport)
    {
    }

    public function list(array $filter = []): array
    {
        $page = $this->transport->execute('GET', '/api/v3/transactions', ['query' => [
            'orderId' => $filter['orderId'] ?? null,
            'rrn' => $filter['rrn'] ?? null,
            'status' => $filter['status'] ?? null,
            'amount' => $filter['amount'] ?? null,
            'description' => $filter['description'] ?? null,
            'name' => $filter['name'] ?? null,
            'fullName' => $filter['fullName'] ?? null,
            'cardNumber' => $filter['cardNumber'] ?? null,
            'bookingId' => $filter['bookingId'] ?? null,
            'invoiceCode' => $filter['invoiceCode'] ?? null,
            'from' => Params::filterDate($filter['from'] ?? null, 'from'),
            'to' => Params::filterDate($filter['to'] ?? null, 'to'),
        ] + Params::page($filter)]) ?? [];
        $page['content'] = array_map(
            static fn (array $tx): array => Params::rename($tx, self::RENAMES),
            $page['content'] ?? [],
        );
        return $page;
    }
}