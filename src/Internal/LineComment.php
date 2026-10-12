<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function preg_match;
use function rtrim;
use function strlen;
use function strpos;
use function substr;

/**
 * Where a `//` or `#` comment ends: at its line break, or at a closing PHP tag before it.
 *
 * @internal
 */
final class LineComment
{
    private function __construct() {}

    /**
     * @return array{string, int, string} the comment's text without trailing space, where the
     *                                    comment token ends, and the line break to put after the
     *                                    replacement (none when `?>` ends the comment)
     */
    public static function at(string $text, int $start): array
    {
        $newline = strpos($text, "\n", $start);
        $lineEnd = $newline === false ? strlen($text) : $newline;
        $close = strpos(substr($text, 0, $lineEnd), '?>', $start);
        if ($close !== false) {
            $comment = rtrim(substr($text, $start, $close - $start));

            return [$comment, $start + strlen($comment), ''];
        }

        $comment = rtrim(substr($text, $start, $lineEnd - $start));
        $ending = $newline === false ? self::eol($text) : ($text[$newline - 1] === "\r" ? "\r\n" : "\n");

        return [$comment, $newline === false ? $lineEnd : $newline + 1, $ending];
    }

    private static function eol(string $text): string
    {
        $match = [];

        return preg_match('/\r\n?|\n/', $text, $match) === 1 ? $match[0] : "\n";
    }
}
