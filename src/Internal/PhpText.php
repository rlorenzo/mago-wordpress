<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function in_array;
use function strlen;
use function strpos;
use function substr;

/**
 * Scanning over PHP source text without tokenizing it.
 *
 * @internal
 */
final class PhpText
{
    private function __construct() {}

    public static function skipSpace(string $text, int $offset): int
    {
        $length = strlen($text);
        while ($offset < $length && in_array($text[$offset], [' ', "\t", "\n", "\r"], strict: true)) {
            ++$offset;
        }

        return $offset;
    }

    /** The offset of the first token after any attributes (and whitespace) from $offset. */
    public static function skipAttributes(string $text, int $offset): int
    {
        return PhpAttributes::skip($text, $offset);
    }

    /**
     * Where the docblock opening at $start ends: the offset of its closing tag (which takes the
     * stars in front of the slash, as in `**\/`) and the offset after it. NULL when it never closes.
     *
     * @return null|array{int, int}
     */
    public static function docblockCloser(string $text, int $start): ?array
    {
        $close = strpos($text, '*/', $start + 3);
        if ($close === false) {
            return null;
        }

        $end = $close + 2;
        while ($close > ($start + 3) && $text[$close - 1] === '*') {
            --$close;
        }

        return [$close, $end];
    }
}
