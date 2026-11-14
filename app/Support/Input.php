<?php

namespace App\Support;

/**
 * Normalises request/UI input before it reaches a DTO.
 *
 * HTML forms submit empty optional fields as an empty string. Casting that
 * value with (int) yields 0, which is not a valid nullable foreign key.
 */
final class Input
{
    public static function intOrNull(mixed $value): ?int
    {
        return self::isBlank($value) ? null : (int) $value;
    }

    public static function stringOrNull(mixed $value): ?string
    {
        return self::isBlank($value) ? null : (string) $value;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
