<?php

declare(strict_types=1);

namespace Payriff\Service;

use Payriff\Internal\Params;
use Payriff\Internal\Transport;

final class CardsService
{
    public function __construct(private readonly Transport $transport)
    {
    }

    public function save(array $params): array
    {
        return $this->transport->execute('POST', '/api/v3/cards/save', [
            'headers' => ['X-Idempotency-Key' => $params['idempotencyKey'] ?? null],
            'body' => Params::compact([
                'customerRef' => Params::required($params, 'customerRef'),
                'callbackUrl' => Params::required($params, 'callbackUrl'),
                'description' => $params['description'] ?? null,
                'language' => $params['language'] ?? null,
                'metadata' => $params['metadata'] ?? null,
            ]),
        ]) ?? [];
    }

    public function getSave(string $cardSaveId): array
    {
        return $this->transport->execute('GET', '/api/v3/cards/save/' . Params::segment($cardSaveId, 'cardSaveId')) ?? [];
    }

    public function list(string $customerRef): array
    {
        Params::segment($customerRef, 'customerRef');
        return $this->transport->execute('GET', '/api/v3/cards/save', ['query' => ['customerRef' => $customerRef]]) ?? [];
    }

    public function delete(string $cardUuid): void
    {
        $this->transport->execute('DELETE', '/api/v3/cards/' . Params::segment($cardUuid, 'cardUuid'));
    }
}