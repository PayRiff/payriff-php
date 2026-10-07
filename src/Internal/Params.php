<?php

declare(strict_types=1);

namespace Payriff\Internal;

final class Params
{
    public const MAX_PAGE_SIZE = 20;

    public static function required(array $params, string $name): mixed
    {
        if (!isset($params[$name])) {
            throw new \InvalidArgumentException("$name is required");
        }
        return $params[$name];
    }

    public static function amount(mixed $value, string $name): int|float|null
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }
        throw new \InvalidArgumentException("$name must be a number");
    }

    public static function segment(mixed $value, string $name): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("$name must not be blank");
        }
        return rawurlencode($value);
    }

    public static function compact(array $values): array
    {
        return array_filter($values, static fn ($v) => $v !== null && $v !== []);
    }

    public static function dateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s');
        }
        return $value === null ? null : (string) $value;
    }

    public static function filterDate(mixed $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d.m.Y');
        }
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return "$m[3].$m[2].$m[1]";
        }
        throw new \InvalidArgumentException("$name must be a DateTimeInterface or a YYYY-MM-DD string");
    }

    public static function page(array $params): array
    {
        $page = $params['page'] ?? 0;
        $size = $params['size'] ?? 10;
        if (!is_int($page) || $page < 0) {
            throw new \InvalidArgumentException('page must be >= 0');
        }
        if (!is_int($size) || $size < 1 || $size > self::MAX_PAGE_SIZE) {
            throw new \InvalidArgumentException('size must be 1-' . self::MAX_PAGE_SIZE);
        }
        return ['page' => $page, 'offset' => $size];
    }

    public static function rename(?array $data, array $map): ?array
    {
        if ($data === null) {
            return null;
        }
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $data)) {
                $data[$to] ??= $data[$from];
                unset($data[$from]);
            }
        }
        return $data;
    }
}