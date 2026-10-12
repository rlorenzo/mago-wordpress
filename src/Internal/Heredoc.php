<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function preg_match;
use function strlen;

/**
 * Where a heredoc or nowdoc that starts at an offset of PHP source text ends.
 *
 * @internal
 */
final class Heredoc
{
    private function __construct() {}

    /** The offset after the heredoc or nowdoc starting at $at, or $at when `<<<` opens none. */
    public static function end(string $text, int $at): int
    {
        $open = [];
        if (preg_match('~\G<<<[ \t]*(["\']?)([\w\x80-\xff]+)\1\r?\n~', $text, $open, 0, $at) !== 1) {
            return $at;
        }

        $body = $at + strlen($open[0]);
        $close = [];
        if (preg_match('~\G(?s:.*?)^[ \t]*' . $open[2] . '(?![\w\x80-\xff])~m', $text, $close, 0, $body) !== 1) {
            return strlen($text);
        }

        return $body + strlen($close[0]);
    }
}
