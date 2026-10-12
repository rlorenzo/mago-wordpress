<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;

use function preg_match;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * The space after a language construct, as `Generic.WhiteSpace.LanguageConstructSpacing` checks it.
 *
 * @internal
 */
final class ConstructSpacing
{
    private const YIELD_FROM = '~\Gyield\s+from~i';

    private function __construct() {}

    /** The length of the `yield from` token that starts at $start. */
    public static function yieldFromLength(string $text, int $start): int
    {
        return preg_match(self::YIELD_FROM, $text, $match, 0, $start) === 1 ? strlen($match[0]) : 0;
    }

    /**
     * @return null|array{string, string, Span, string}
     */
    public static function after(string $text, int $end, string $keyword): ?array
    {
        $next = PhpText::skipSpace($text, $end);
        if ($next >= strlen($text) || ($text[$end] ?? '') === ';') {
            return null;
        }

        $gap = substr($text, $end, $next - $end);
        if ($gap === ' ') {
            return null;
        }

        if ($gap !== '') {
            // phpcs splits whitespace after each line break and reports the first piece.
            $newline = strpos($gap, "\n");
            $first = $newline === false ? $gap : substr($gap, 0, $newline + 1);

            return [
                sprintf(
                    'Language constructs must be followed by a single space; expected 1 space but found "%s"',
                    self::show($first),
                ),
                'IncorrectSingle',
                new Span($end, $next),
                ' ',
            ];
        }

        if ($text[$end] === '(') {
            return null;
        }

        $token = self::tokenAt($text, $end);

        return [
            sprintf(
                'Language constructs must be followed by a single space; expected "%s" but found "%s"',
                $keyword . ' ' . $token,
                $keyword . $token,
            ),
            'Incorrect',
            new Span($end, $end),
            ' ',
        ];
    }

    /**
     * @return null|array{string, string, Span, string}
     */
    public static function yieldFrom(string $text, int $start): ?array
    {
        if (preg_match(self::YIELD_FROM, $text, $match, 0, $start) !== 1 || strtolower($match[0]) === 'yield from') {
            return null;
        }

        $found = $match[0];
        $from = substr($found, -4);

        return [
            sprintf(
                'Language constructs must be followed by a single space; expected 1 space between YIELD FROM found "%s"',
                self::show($found),
            ),
            'IncorrectYieldFrom',
            new Span($start, $start + strlen($found)),
            substr($found, 0, 5) . ' ' . $from,
        ];
    }

    /** The first token after the position, as far as a message needs it. */
    private static function tokenAt(string $text, int $at): string
    {
        return preg_match(
            '~^(?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|[A-Za-z_\x80-\xff][\w\x80-\xff]*|\$\w+|\d+|.)~s',
            substr($text, $at, 64),
            $match,
        ) === 1
            ? $match[0]
            : '';
    }

    private static function show(string $content): string
    {
        return str_replace(["\r", "\n", "\t", ' '], ['\r', '\n', '···', '·'], $content);
    }
}
