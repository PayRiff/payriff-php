<?php

declare(strict_types=1);

namespace Payriff;

use Payriff\Internal\Json;

final class CardData
{
    private static ?\WeakMap $values = null;

    public function __construct(
        string $pan,
        string $cardHolder,
        string|int $expiryMonth,
        string|int $expiryYear,
        string $cvv,
    ) {
        $pan = self::digits($pan, 'pan');
        if (strlen($pan) < 12 || strlen($pan) > 19) {
            throw new \InvalidArgumentException('pan must contain 12-19 digits');
        }
        $month = (int) self::digits($expiryMonth, 'expiryMonth');
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('expiryMonth must be 1-12');
        }
        $year = self::digits($expiryYear, 'expiryYear');
        if (strlen($year) === 2) {
            $year = '20' . $year;
        } elseif (strlen($year) !== 4) {
            throw new \InvalidArgumentException('expiryYear must have 2 or 4 digits');
        }
        $cvv = self::digits($cvv, 'cvv');
        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            throw new \InvalidArgumentException('cvv must contain 3-4 digits');
        }
        self::$values ??= new \WeakMap();
        self::$values[$this] = [
            'pan' => $pan,
            'cardHolder' => trim($cardHolder),
            'expiryYear' => $year,
            'expiryMonth' => sprintf('%02d', $month),
            'cvv' => $cvv,
        ];
    }

    public function toJson(): string
    {
        return Json::encode(self::$values[$this]);
    }

    public function __toString(): string
    {
        $v = self::$values[$this];
        return 'CardData{pan=' . substr($v['pan'], 0, 6) . '******' . substr($v['pan'], -4)
            . ', expiry=' . $v['expiryMonth'] . '/' . $v['expiryYear'] . '}';
    }

    public function __debugInfo(): array
    {
        return ['card' => (string) $this];
    }

    public function __serialize(): array
    {
        throw new \LogicException('CardData cannot be serialized');
    }

    public function __clone()
    {
        throw new \LogicException('CardData cannot be cloned');
    }

    private static function digits(string|int $value, string $field): string
    {
        $v = preg_replace('/[\s-]/', '', (string) $value);
        if ($v === '' || !ctype_digit($v)) {
            throw new \InvalidArgumentException("$field must contain digits only");
        }
        return $v;
    }
}