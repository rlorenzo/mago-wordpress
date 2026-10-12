<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function strlen;
use function strpos;
use function substr;

/**
 * Where a string, heredoc or comment that starts at an offset of PHP source text ends.
 *
 * @internal
 */
final class PhpLiterals
{
    private function __construct() {}

    /** The offset after the string or comment starting at $at, or $at when none does. */
    public static function end(string $text, int $at): int
    {
        $char = $text[$at];
        if ($char === "'" || $char === '"') {
            for ($i = $at + 1, $length = strlen($text); $i < $length; ++$i) {
                if ($text[$i] === '\\') {
                    ++$i;
                } elseif ($text[$i] === $char) {
                    return $i + 1;
                }
            }

            return $length;
        }

        if ($char === '<' && substr($text, $at, 3) === '<<<') {
            return Heredoc::end($text, $at);
        }

        $two = substr($text, $at, 2);
        if ($two === '/*') {
            $close = strpos($text, '*/', $at + 2);

            return $close === false ? strlen($text) : $close + 2;
        }

        if ($two === '//' || $char === '#' && $two !== '#[') {
            $newline = strpos($text, "\n", $at);

            return $newline === false ? strlen($text) : $newline;
        }

        return $at;
    }
}
