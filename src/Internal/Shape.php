<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Narrows a `mixed` value to the shape that the caller expects.
 *
 * A nullable lookup, a parsed document or a loosely typed array returns
 * `mixed`. These helpers take that value as an argument and return a typed
 * one, so a caller never holds an untyped variable.
 *
 * @internal
 */
final class Shape
{
    private function __construct() {}

    public static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public static function array(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * Returns the array at the path that the keys make: NULL when a key on the path is
     * missing, an empty array when the last key is present but not an array.
     *
     * @return array<array-key, mixed>|null
     */
    public static function arrayAt(mixed $value, string $key, string ...$rest): ?array
    {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return null;
        }

        return $rest === [] ? self::array($value[$key]) ?? [] : self::arrayAt($value[$key], ...$rest);
    }
}
