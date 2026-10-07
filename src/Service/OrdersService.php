<?php

declare(strict_types=1);

namespace Payriff\Service;

use Payriff\Internal\Params;
use Payriff\Internal\Transport;

final class OrdersService
{
    public function __construct(private readonly Transport $transport)
    {
    }

    public function create(array $params): array
    {
        $body = Params::compact([
            'amount' => Params::amount(Params::required($params, 'amount'), 'amount'),
            'currency' => $params['currency'] ?? 'AZN',
            'language' => $params['language'] ?? null,
            'operation' => $params['operation'] ?? 'PURCHASE',
            'description' => $params['description'] ?? null,
            'callbackUrl' => $params['callbackUrl'] ?? null,
            'redirectUrl' => $params['redirectUrl'] ?? null,
            'cardSave' => $params['cardSave'] ?? null,
            'threeDS' => $params['threeDS'] ?? null,
            'autoPaymentType' => $params['autoPaymentType'] ?? null,
            'installment' => $params['installment'] ?? null,
            'fullName' => $params['fullName'] ?? null,
            'phoneNumber' => $params['phoneNumber'] ?? null,
            'metadata' => $params['metadata'] ?? null,
            'fields' => $params['fields'] ?? null,
        ]);
        $payload = $this->transport->execute('POST', '/api/v3/orders', [
            'headers' => ['X-REQUEST-RRN' => $params['requestRrn'] ?? null],
            'body' => $body,
        ]);
        return Params::rename($payload, ['comissionRate' => 'commissionRate']) ?? [];
    }

    public function get(string $orderId): array
    {
        return $this->transport->execute('GET', '/api/v3/orders/' . Params::segment($orderId, 'orderId')) ?? [];
    }

    public function getStatus(string $orderId): array
    {
        return $this->transport->execute('GET', '/api/v3/orders/' . Params::segment($orderId, 'orderId') . '/status') ?? [];
    }

    public function getByRequestRrn(string $requestRrn): array
    {
        return $this->transport->execute('GET', '/api/v3/orders/' . Params::segment($requestRrn, 'requestRrn') . '/rrn') ?? [];
    }

    public function expire(string $orderId): void
    {
        Params::segment($orderId, 'orderId');
        $this->transport->execute('PATCH', '/api/v3/expire-status', ['query' => ['orderId' => $orderId]]);
    }

    public function refund(array $params): void
    {
        $this->transport->execute('POST', '/api/v3/refund', ['body' => Params::compact([
            'orderId' => Params::required($params, 'orderId'),
            'amount' => Params::amount($params['amount'] ?? null, 'amount'),
            'refundReason' => $params['refundReason'] ?? null,
            'callbackUrl' => $params['callbackUrl'] ?? null,
        ])]);
    }

    public function complete(array $params): void
    {
        $this->transport->execute('POST', '/api/v3/complete', ['body' => Params::compact([
            'orderId' => Params::required($params, 'orderId'),
            'amount' => Params::amount($params['amount'] ?? null, 'amount'),
            'callbackUrl' => $params['callbackUrl'] ?? null,
        ])]);
    }

    public function downloadReceipt(string $orderIdOrRrn): string
    {
        return $this->transport->download('GET', '/api/v3/acquiring/receipt/' . Params::segment($orderIdOrRrn, 'orderIdOrRrn'));
    }
}