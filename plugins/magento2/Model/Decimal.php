<?php

declare(strict_types=1);

namespace PayXCommerce\Payment\Model;

final class Decimal
{
    public static function compare(mixed $left, mixed $right): ?int
    {
        $leftParts = self::parts((string) $left);
        $rightParts = self::parts((string) $right);
        if ($leftParts === null || $rightParts === null) {
            return null;
        }

        if ($leftParts['sign'] !== $rightParts['sign']) {
            return $leftParts['sign'] <=> $rightParts['sign'];
        }
        if ($leftParts['sign'] === 0) {
            return 0;
        }

        $magnitude = strlen($leftParts['integer']) <=> strlen($rightParts['integer']);
        if ($magnitude === 0) {
            $magnitude = strcmp($leftParts['integer'], $rightParts['integer']) <=> 0;
        }
        if ($magnitude === 0) {
            $scale = max(strlen($leftParts['fraction']), strlen($rightParts['fraction']));
            $magnitude = strcmp(str_pad($leftParts['fraction'], $scale, '0'), str_pad($rightParts['fraction'], $scale, '0')) <=> 0;
        }

        return $leftParts['sign'] * $magnitude;
    }

    public static function isValid(mixed $value): bool
    {
        return self::parts((string) $value) !== null;
    }

    public static function isPositive(mixed $value): bool
    {
        return self::compare($value, '0') === 1;
    }

    private static function parts(string $value): ?array
    {
        $value = trim($value);
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return null;
        }

        $integer = ltrim($matches[2], '0');
        $fraction = rtrim($matches[3] ?? '', '0');
        $integer = $integer === '' ? '0' : $integer;
        $zero = $integer === '0' && $fraction === '';

        return [
            'sign' => $zero ? 0 : ($matches[1] === '-' ? -1 : 1),
            'integer' => $integer,
            'fraction' => $fraction,
        ];
    }
}
