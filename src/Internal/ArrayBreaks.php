<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function ctype_space;
use function ltrim;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * The line breaks `WordPress.Arrays.ArrayDeclarationSpacing` asks for in an array literal, as
 * the edits that put each item on a line of its own (what phpcbf and the indentation sniffs do over
 * several passes).
 *
 * @internal
 */
final class ArrayBreaks
{
    private function __construct() {}

    /**
     * The edits that break a single-line array: before the first item, after each comma, and before
     * the closing bracket. A gap that holds a comment other than a block comment is left alone.
     *
     * @param array{open: int, close: int, items: list<array{start: int, end: int, keyed: bool}>} $shape
     *
     * @return list<TextEdit>
     */
    public static function breakAll(string $text, array $shape, string $base, string $eol): array
    {
        $items = $shape['items'];
        $inner = $base . "\t";
        $edits = [];
        $first = self::replaceGap($text, $shape['open'] + 1, $items[0]['start'], $eol . $inner, comment: true);
        if ($first !== null) {
            $edits[] = $first;
        }

        for ($i = 1, $count = count($items); $i < $count; ++$i) {
            $comma = strpos($text, ',', $items[$i - 1]['end']);
            if ($comma !== false && $comma < $items[$i]['start']) {
                $edit = self::replaceGap($text, $comma + 1, $items[$i]['start'], $eol . $inner, comment: true);
                if ($edit !== null) {
                    $edits[] = $edit;
                }
            }
        }

        $last = $items[$count - 1]['end'];
        $comma = strpos($text, ',', $last);
        $from = $comma !== false && $comma < $shape['close'] ? $comma + 1 : $last;
        $edit = self::replaceGap($text, $from, $shape['close'], $eol . $base);
        if ($edit !== null) {
            $edits[] = $edit;
        }

        return $edits;
    }

    /**
     * The edit that puts the item starting at $itemStart on a line of its own, after $previous.
     */
    public static function breakBefore(
        string $text,
        int $previous,
        int $itemStart,
        string $indent,
        string $eol,
    ): ?TextEdit {
        return self::replaceGap($text, $previous + 1, $itemStart, $eol . $indent, comment: true);
    }

    /**
     * The edits that move the item to a line of its own: the break in front of it and the shift of
     * its later lines.
     *
     * @param array{start: int, end: int, keyed: bool} $item
     *
     * @return list<TextEdit>
     */
    public static function moveItem(SourceFile $file, int $previous, array $item, string $indent, string $eol): array
    {
        $text = $file->contents;
        $break = self::breakBefore($text, $previous, $item['start'], $indent, $eol);
        if ($break === null) {
            return [];
        }

        $tokens = PhpcsTokens::of($file);

        return [$break, ...ItemShift::edits($tokens, $text, $item['start'], $item['end'], $indent)];
    }

    /**
     * Replaces the whitespace at the start of the gap $from..$to with $with. A gap that holds only
     * whitespace is replaced whole; with `comment`, whitespace in front of a block comment is the part
     * replaced.
     */
    private static function replaceGap(string $text, int $from, int $to, string $with, bool $comment = false): ?TextEdit
    {
        $gap = substr($text, $from, $to - $from);
        if (trim($gap) === '') {
            return TextEdit::replace(new Span($from, $to), $with);
        }

        if (!$comment || !ctype_space($gap[0]) && $gap[0] !== '/') {
            return null;
        }

        $space = strlen($gap) - strlen(ltrim($gap));
        $rest = substr($gap, $space);
        if (strpos($rest, "\n") !== false || str_starts_with($rest, '//')) {
            return null;
        }

        return TextEdit::replace(new Span($from, $from + $space), $with);
    }
}
