<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Fixed-precision decimal arithmetic. Never use native floats for quantities or money.
 */
final class Decimal
{
    public const QUANTITY_SCALE = 4;

    public const MONEY_SCALE = 2;

    public const COST_SCALE = 6;

    public static function normalize(string|int $value, int $scale = self::QUANTITY_SCALE): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Value [{$value}] is not numeric.");
        }

        return bcadd((string) $value, '0', $scale);
    }

    public static function add(string|int $a, string|int $b, int $scale = self::QUANTITY_SCALE): string
    {
        return bcadd((string) $a, (string) $b, $scale);
    }

    public static function subtract(string|int $a, string|int $b, int $scale = self::QUANTITY_SCALE): string
    {
        return bcsub((string) $a, (string) $b, $scale);
    }

    public static function multiply(string|int $a, string|int $b, int $scale = self::QUANTITY_SCALE): string
    {
        return bcmul((string) $a, (string) $b, $scale);
    }

    public static function divide(string|int $a, string|int $b, int $scale = self::COST_SCALE): string
    {
        if (bccomp((string) $b, '0', $scale) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv((string) $a, (string) $b, $scale);
    }

    public static function compare(string|int $a, string|int $b, int $scale = self::QUANTITY_SCALE): int
    {
        return bccomp((string) $a, (string) $b, $scale);
    }

    public static function isPositive(string|int $value, int $scale = self::QUANTITY_SCALE): bool
    {
        return self::compare($value, '0', $scale) === 1;
    }

    public static function isZero(string|int $value, int $scale = self::QUANTITY_SCALE): bool
    {
        return self::compare($value, '0', $scale) === 0;
    }

    public static function isNegative(string|int $value, int $scale = self::QUANTITY_SCALE): bool
    {
        return self::compare($value, '0', $scale) === -1;
    }
}
