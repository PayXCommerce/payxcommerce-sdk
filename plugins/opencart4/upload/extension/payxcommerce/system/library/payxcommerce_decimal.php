<?php
namespace Opencart\System\Library;

final class PayxcommerceDecimal
{
    public static function compare(mixed $left, mixed $right): ?int
    {
        $left_parts = self::parts((string) $left);
        $right_parts = self::parts((string) $right);
        if ($left_parts === null || $right_parts === null) {
            return null;
        }

        if ($left_parts['sign'] !== $right_parts['sign']) {
            return $left_parts['sign'] <=> $right_parts['sign'];
        }
        if ($left_parts['sign'] === 0) {
            return 0;
        }

        $magnitude = strlen($left_parts['integer']) <=> strlen($right_parts['integer']);
        if ($magnitude === 0) {
            $magnitude = strcmp($left_parts['integer'], $right_parts['integer']) <=> 0;
        }
        if ($magnitude === 0) {
            $scale = max(strlen($left_parts['fraction']), strlen($right_parts['fraction']));
            $magnitude = strcmp(str_pad($left_parts['fraction'], $scale, '0'), str_pad($right_parts['fraction'], $scale, '0')) <=> 0;
        }

        return $left_parts['sign'] * $magnitude;
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
