<?php

declare(strict_types=1);

namespace Payriff\Service;

use Payriff\Internal\Params;
use Payriff\Internal\Transport;

final class PaymentsService
{
    public function __construct(private readonly Transport $transport, private readonly string $defaultCurrency)
    {
    }

    public function autoPay(array $params): array
    {
        return $this->transport->execute('POST', '/api/v3/autoPay', [
            'headers' => ['X-REQUEST-RRN' => $params['requestRrn'] ?? null],
            'body' => Params::compact([
                'cardUuid' => Params::required($params, 'cardUuid'),
                'amount' => Params::amount(Params::required($params, 'amount'), 'amount'),
                'operation' => $params['operation'] ?? 'PURCHASE',
                'currency' => $params['currency'] ?? $this->defaultCurrency,
                'description' => Params::required($params, 'description'),
                'callbackUrl' => $params['callbackUrl'] ?? null,
                'threeDS' => $params['threeDS'] ?? null,
                'isOneCLickPayment' => $params['oneClickPayment'] ?? null,
            ]),
        ]) ?? [];
    }
}