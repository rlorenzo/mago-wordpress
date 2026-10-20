<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function ord;
use function strlen;
use function strrpos;
use function substr;

/**
 * Indentation and whitespace widths in the text around an array.
 *
 * @internal
 */
final class ArrayText
{
    private const TAB_WIDTH = 4;

    private function __construct() {}

    /** The offset of the last newline before $offset; a negative offset searches backwards without copying the text. */
    private static function lineBreakBefore(string $text, int $offset): int|false
    {
        return strrpos($text, "\n", $offset - strlen($text) - 1);
    }

    /** The leading whitespace of the line holding $offset. */
    public static function lineIndent(string $text, int $offset): string
    {
        $newline = $offset === 0 ? false : self::lineBreakBefore($text, $offset);
        $start = $newline === false ? 0 : $newline + 1;
        $end = $start;
        while ($end < strlen($text) && ($text[$end] === ' ' || $text[$end] === "\t")) {
            ++$end;
        }

        return substr($text, $start, $end - $start);
    }

    /** The display width, tabs to the next multiple of four, of the whitespace between two offsets. */
    public static function gapWidth(string $text, int $from, int $to): int
    {
        $newline = $from === 0 ? false : self::lineBreakBefore($text, $from);
        $column = 0;
        for ($i = $newline === false ? 0 : $newline + 1; $i < $from; ++$i) {
            $column += $text[$i] === "\t"
                ? self::TAB_WIDTH - ($column % self::TAB_WIDTH)
                : (int) ((ord($text[$i]) & 0xC0) !== 0x80);
        }

        $width = 0;
        for ($i = $from; $i < $to; ++$i) {
            // phpcs counts characters, not bytes (iconv_strlen): a UTF-8 continuation byte is no column.
            $step = $text[$i] === "\t"
                ? self::TAB_WIDTH - (($column + $width) % self::TAB_WIDTH)
                : (int) ((ord($text[$i]) & 0xC0) !== 0x80);
            $width += $step;
        }

        return $width;
    }
}
