<?php

declare(strict_types=1);

namespace Payriff\Service;

use Payriff\Internal\Params;
use Payriff\Internal\Transport;

final class PayoutsService
{
    public function __construct(private readonly Transport $transport)
    {
    }

    public function create(array $params): array
    {
        $amount = Params::amount(Params::required($params, 'transferAmount'), 'transferAmount');
        if ($amount < 1) {
            throw new \InvalidArgumentException('transferAmount must be at least 1');
        }
        $payload = $this->transport->execute('POST', '/api/v3/payout', [
            'headers' => ['X-IDEMPOTENCY-KEY' => $params['idempotencyKey'] ?? null],
            'merchantEnvelope' => true,
            'body' => Params::compact([
                'transferAmount' => $amount,
                'description' => Params::required($params, 'description'),
                'fullName' => Params::required($params, 'fullName'),
                'finCode' => Params::required($params, 'finCode'),
                'cardPan' => $params['cardPan'] ?? null,
                'bankName' => $params['bankName'] ?? null,
                'cardType' => $params['cardType'] ?? null,
                'requestRrn' => $params['requestRrn'] ?? null,
                'customerCode' => $params['customerCode'] ?? null,
                'voen' => $params['voen'] ?? null,
                'birthDate' => $params['birthDate'] ?? null,
                'callbackUrl' => $params['callbackUrl'] ?? null,
            ]),
        ]);
        return Params::rename($payload, ['_final' => 'finalState']) ?? [];
    }

    public function getByRequestRrn(string $requestRrn): array
    {
        return $this->transport->execute('GET', '/api/v3/payout/info/' . Params::segment($requestRrn, 'requestRrn')) ?? [];
    }

    public function checkCardholder(string $cardPan): string
    {
        $pan = (string) preg_replace('/[\s-]/', '', $cardPan);
        if (!preg_match('/^\d{16}$/', $pan)) {
            throw new \InvalidArgumentException('cardPan must be a 16-digit number');
        }
        return (string) $this->transport->execute('POST', '/api/v3/payout/check-cardholder', ['body' => ['cardPan' => $pan]]);
    }

    public function list(array $filter = []): array
    {
        return $this->transport->execute('GET', '/api/v3/payouts', ['query' => [
            'rrn' => $filter['rrn'] ?? null,
            'status' => $filter['status'] ?? null,
            'amount' => $filter['amount'] ?? null,
            'description' => $filter['description'] ?? null,
            'fullName' => $filter['fullName'] ?? null,
            'finCode' => $filter['finCode'] ?? null,
            'bankSource' => $filter['bankSource'] ?? null,
            'from' => Params::filterDate($filter['from'] ?? null, 'from'),
            'to' => Params::filterDate($filter['to'] ?? null, 'to'),
        ] + Params::page($filter)]) ?? [];
    }

    public function downloadReceipt(string $requestRrn): string
    {
        return $this->transport->download('GET', '/api/v3/payout/receipt/' . Params::segment($requestRrn, 'requestRrn'));
    }
}