<?php

declare(strict_types=1);

namespace Payriff\Service;

use Payriff\CardData;
use Payriff\Internal\CardEncryptor;
use Payriff\Internal\Params;
use Payriff\Internal\Transport;

final class PaymentsService
{
    public function __construct(private readonly Transport $transport, private readonly CardEncryptor $cardEncryptor)
    {
    }

    public function directPay(array $params): array
    {
        $body = Params::compact([
            'amount' => Params::amount(Params::required($params, 'amount'), 'amount'),
            'operation' => $params['operation'] ?? 'PURCHASE',
            'currency' => $params['currency'] ?? 'AZN',
            'description' => Params::required($params, 'description'),
            'callbackUrl' => $params['callbackUrl'] ?? null,
            'threeDS' => $params['threeDS'] ?? null,
            'customFields' => $params['customFields'] ?? null,
        ]);
        $card = Params::required($params, 'card');
        if (!$card instanceof CardData) {
            throw new \InvalidArgumentException('card must be a ' . CardData::class);
        }
        $encrypted = $this->cardEncryptor->encrypt($card);
        $body['paymentData'] = [
            'paymentWay' => 'DIRECT',
            'encryptedMessage' => $encrypted['encryptedMessage'],
            'cardSave' => (bool) ($params['cardSave'] ?? false),
        ];
        return $this->transport->execute('POST', '/api/v3/directPay', [
            'headers' => ['X-REQUEST-RRN' => $params['requestRrn'] ?? null, 'x-secret-key' => $encrypted['secretKey']],
            'body' => $body,
        ]) ?? [];
    }

    public function autoPay(array $params): array
    {
        return $this->transport->execute('POST', '/api/v3/autoPay', [
            'headers' => ['X-REQUEST-RRN' => $params['requestRrn'] ?? null],
            'body' => Params::compact([
                'cardUuid' => Params::required($params, 'cardUuid'),
                'amount' => Params::amount(Params::required($params, 'amount'), 'amount'),
                'operation' => $params['operation'] ?? 'PURCHASE',
                'currency' => $params['currency'] ?? 'AZN',
                'description' => Params::required($params, 'description'),
                'callbackUrl' => $params['callbackUrl'] ?? null,
                'threeDS' => $params['threeDS'] ?? null,
                'isOneCLickPayment' => $params['oneClickPayment'] ?? null,
            ]),
        ]) ?? [];
    }
}