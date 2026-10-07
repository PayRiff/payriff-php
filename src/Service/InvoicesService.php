<?php

declare(strict_types=1);

namespace Payriff\Service;

use Payriff\Internal\Params;
use Payriff\Internal\Transport;

final class InvoicesService
{
    public function __construct(private readonly Transport $transport)
    {
    }

    public function create(array $params): array
    {
        if (($params['amountDynamic'] ?? null) !== true && !isset($params['amount'])) {
            throw new \InvalidArgumentException('amount is required');
        }
        return $this->transport->execute('POST', '/api/v2/invoices', [
            'merchantEnvelope' => true,
            'body' => Params::compact([
                'amount' => Params::amount($params['amount'] ?? null, 'amount'),
                'amountDynamic' => $params['amountDynamic'] ?? null,
                'currencyType' => $params['currency'] ?? 'AZN',
                'languageType' => $params['language'] ?? null,
                'fullName' => $params['fullName'] ?? null,
                'email' => $params['email'] ?? null,
                'phoneNumber' => $params['phoneNumber'] ?? null,
                'description' => $params['description'] ?? null,
                'customMessage' => $params['customMessage'] ?? null,
                'expireDate' => Params::dateTime($params['expireDate'] ?? null),
                'approveURL' => $params['approveUrl'] ?? null,
                'cancelURL' => $params['cancelUrl'] ?? null,
                'declineURL' => $params['declineUrl'] ?? null,
                'redirectURL' => $params['redirectUrl'] ?? null,
                'installmentProductType' => $params['installmentProductType'] ?? null,
                'installmentPeriod' => $params['installmentPeriod'] ?? null,
                'directPay' => $params['directPay'] ?? null,
                'sendSms' => $params['sendSms'] ?? null,
                'sendWhatsapp' => $params['sendWhatsapp'] ?? null,
                'sendEmail' => $params['sendEmail'] ?? null,
                'metadata' => $params['metadata'] ?? null,
                'externalTransactionId' => $params['externalTransactionId'] ?? null,
            ]),
        ]) ?? [];
    }

    public function get(string $invoiceUuid): array
    {
        Params::segment($invoiceUuid, 'invoiceUuid');
        return $this->transport->execute('POST', '/api/v2/get-invoice', [
            'merchantEnvelope' => true,
            'body' => ['uuid' => $invoiceUuid],
        ]) ?? [];
    }
}