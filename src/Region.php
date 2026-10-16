<?php

declare(strict_types=1);

namespace Payriff;

enum Region: string
{
    case AZERBAIJAN = 'azerbaijan';
    case SAUDI = 'saudi';

    public function baseUrl(): string
    {
        return match ($this) {
            self::AZERBAIJAN => 'https://api.payriff.com',
            self::SAUDI => 'https://api.gccpay.sa',
        };
    }

    public function currency(): string
    {
        return match ($this) {
            self::AZERBAIJAN => 'AZN',
            self::SAUDI => 'SAR',
        };
    }
}