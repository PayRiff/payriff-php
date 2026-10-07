<?php

declare(strict_types=1);

namespace Payriff;

final class Webhook
{
    private const NOT_A_CALLBACK = 'Not a Payriff order callback';

    public static function parseOrderCallback(string|array $body): array
    {
        $root = $body;
        if (is_string($body)) {
            try {
                $root = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException(self::NOT_A_CALLBACK, 0, $e);
            }
        }
        $payload = is_array($root) ? ($root['payload'] ?? null) : null;
        if (!is_array($payload) || array_is_list($payload) || !isset($payload['orderId'])) {
            throw new \InvalidArgumentException(self::NOT_A_CALLBACK);
        }
        return $payload;
    }
}