<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function strlen;
use function substr;

/**
 * Skipping over attributes in PHP source text.
 *
 * @internal
 */
final class PhpAttributes
{
    private function __construct() {}

    /** The offset of the first token after any attributes (and whitespace) from $offset. */
    public static function skip(string $text, int $offset): int
    {
        $offset = PhpText::skipSpace($text, $offset);
        while (substr($text, $offset, 2) === '#[') {
            $offset = PhpText::skipSpace($text, self::closing($text, $offset + 1) + 1);
        }

        return $offset;
    }

    /** The offset of the `]` that closes the `[` at $open, past strings and comments inside. */
    private static function closing(string $text, int $open): int
    {
        $depth = 0;
        for ($i = $open, $length = strlen($text); $i < $length; ++$i) {
            $skipped = PhpLiterals::end($text, $i);
            if ($skipped !== $i) {
                $i = $skipped - 1;

                continue;
            }

            $depth += match ($text[$i]) {
                '[' => 1,
                ']' => -1,
                default => 0,
            };
            if ($depth === 0) {
                return $i;
            }
        }

        return $length;
    }
}
