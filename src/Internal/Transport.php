<?php

declare(strict_types=1);

namespace Payriff\Internal;

use Payriff\Exception\ApiException;
use Payriff\Exception\PayriffConnectionException;
use Payriff\Payriff;

final class Transport
{
    private const MAX_ERROR_BODY = 500;

    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $appKey,
        private readonly ?string $merchantId,
        private readonly float $timeout,
        private readonly float $connectTimeout,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function execute(string $method, string $path, array $options = []): mixed
    {
        [$status, $body] = $this->send($method, $path, $options, 'application/json');
        return $this->unwrap($status, $body);
    }

    public function download(string $method, string $path, array $options = []): string
    {
        [$status, $body] = $this->send($method, $path, $options, 'application/pdf, application/json');
        if ($status >= 200 && $status < 300) {
            return $body;
        }
        $this->unwrap($status, $body);
        throw new ApiException('Unexpected response', $status);
    }

    private function send(string $method, string $path, array $options, string $accept): array
    {
        $headers = [
            'Accept: ' . $accept,
            'User-Agent: payriff-php/' . Payriff::VERSION,
            'Authorization: ' . $this->appKey,
        ];
        foreach ($options['headers'] ?? [] as $name => $value) {
            if ($value !== null) {
                $headers[] = $name . ': ' . $value;
            }
        }
        $body = $this->requestBody($options);
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init($this->url($path, $options['query'] ?? []));
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->connectTimeout * 1000),
            CURLOPT_TIMEOUT_MS => (int) ($this->timeout * 1000),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            throw new PayriffConnectionException('Payriff request failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return [$status, (string) $response];
    }

    private function url(string $path, array $query): string
    {
        $params = [];
        foreach ($query as $name => $value) {
            if ($value !== null) {
                $params[$name] = (string) $value;
            }
        }
        $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return $this->baseUrl . $path . ($qs !== '' ? '?' . $qs : '');
    }

    private function requestBody(array $options): ?string
    {
        if (!empty($options['merchantEnvelope'])) {
            if ($this->merchantId === null || $this->merchantId === '') {
                throw new \LogicException('merchantId must be configured on Payriff for this operation');
            }
            return Json::encode(['merchant' => $this->merchantId, 'body' => $options['body'] ?? new \stdClass()]);
        }
        return array_key_exists('body', $options) && $options['body'] !== null ? Json::encode($options['body']) : null;
    }

    private function unwrap(int $status, string $body): mixed
    {
        try {
            $root = $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $root = null;
        }
        $ok = $status >= 200 && $status < 300;
        if (!is_array($root) || array_is_list($root) || !array_key_exists('code', $root)) {
            throw new ApiException($ok ? 'Unexpected response from Payriff' : self::truncate($body, $status), $status);
        }
        $code = self::str($root['code']);
        $responseId = self::str($root['responseId'] ?? null);
        if (!$ok || $code !== ResultCodes::SUCCESS) {
            throw ResultCodes::toException(self::str($root['message'] ?? null), $status, $code, $responseId);
        }
        return $root['payload'] ?? null;
    }

    private static function str(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function truncate(string $body, int $status): string
    {
        if ($body === '') {
            return "Payriff request failed (HTTP $status)";
        }
        return strlen($body) > self::MAX_ERROR_BODY ? substr($body, 0, self::MAX_ERROR_BODY) . '...' : $body;
    }
}