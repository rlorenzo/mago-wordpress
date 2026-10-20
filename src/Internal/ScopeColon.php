<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function max;
use function preg_match;
use function strrpos;
use function substr;
use function trim;

/**
 * Whether a `:` is the opener of a scope in phpcs's eyes.
 *
 * @internal
 */
final class ScopeColon
{
    private function __construct() {}

    /** Whether the `:` at $colon opens a `case`, `default` or alternative-syntax scope. */
    public static function at(string $text, int $colon): bool
    {
        if ($text[$colon] !== ':' || ($text[$colon - 1] ?? '') === ':') {
            return false;
        }

        $lineStart = Strings::lastNewlineBefore($text, $colon);
        $line = substr(
            $text,
            $lineStart === false ? 0 : $lineStart + 1,
            $colon - ($lineStart === false ? 0 : $lineStart + 1),
        );

        return (
            preg_match(
                '~^\s*(?:case\b.+|default|else|(?:if|elseif|while|for|foreach|switch|declare)\s*\(.*\))\s*$~is',
                $line,
            ) === 1
            || self::closesControlHead($text, $colon)
        );
    }

    /** Whether the `:` follows the `)` of a control structure head that starts on an earlier line. */
    private static function closesControlHead(string $text, int $colon): bool
    {
        $close = $colon - 1;
        while ($close >= 0 && trim($text[$close]) === '') {
            --$close;
        }

        if ($close < 0 || $text[$close] !== ')') {
            return false;
        }

        $depth = 0;
        for ($i = $close; $i >= 0; --$i) {
            if ($text[$i] === ')') {
                ++$depth;
            } elseif ($text[$i] === '(' && --$depth === 0) {
                $from = max(0, $i - 13);

                return (
                    preg_match(
                        $from === 0
                            ? '~(?:^|[\s;{}:])(?:if|elseif|while|for|foreach|switch|declare)\s*$~i'
                            : '~[\s;{}:](?:if|elseif|while|for|foreach|switch|declare)\s*$~i',
                        substr($text, $from, $i - $from),
                    ) === 1
                );
            }
        }

        return false;
    }
}
