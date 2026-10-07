<?php

declare(strict_types=1);

namespace Payriff\Internal;

use Payriff\Exception\ApiException;
use Payriff\Exception\AuthenticationException;
use Payriff\Exception\InsufficientBalanceException;
use Payriff\Exception\PayoutLimitException;
use Payriff\Exception\RequestRejectedException;
use Payriff\Exception\ValidationException;

final class ResultCodes
{
    public const SUCCESS = '00000';

    private const AUTH = ['14010', '14013', '14014', '14015'];
    private const PAYOUT_LIMIT = ['01300', '01400', '01500'];
    private const BY_CODE = [
        '01200' => InsufficientBalanceException::class,
        '01000' => RequestRejectedException::class,
        '15400' => ValidationException::class,
    ];

    public static function toException(?string $message, int $httpStatus, ?string $code, ?string $responseId): ApiException
    {
        $msg = $message !== null && $message !== '' ? $message : "Payriff request failed (HTTP $httpStatus)";
        $class = ApiException::class;
        if ($code !== null && in_array($code, self::AUTH, true)) {
            $class = AuthenticationException::class;
        } elseif ($code !== null && in_array($code, self::PAYOUT_LIMIT, true)) {
            $class = PayoutLimitException::class;
        } elseif ($code !== null && isset(self::BY_CODE[$code])) {
            $class = self::BY_CODE[$code];
        } elseif ($httpStatus === 401 || $httpStatus === 403) {
            $class = AuthenticationException::class;
        } elseif ($httpStatus === 400) {
            $class = ValidationException::class;
        }
        return new $class($msg, $httpStatus, $code, $responseId);
    }
}