<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;
use function trim;

/**
 * The blank lines `Squiz.Commenting.InlineComment` reports in front of the `}` that ends a function.
 *
 * @internal
 */
final class BlankLinesAfterComment
{
    private function __construct() {}

    /**
     * The blank lines (as offsets from, to) between the comment ending at $end and the next token,
     * when that is the `}` of a function; NULL otherwise.
     *
     * @param array<int, true> $closers
     *
     * @return null|array{int, int}
     */
    public static function range(string $text, int $end, string $eol, array $closers): ?array
    {
        $next = $end;
        $length = strlen($text);
        while ($next < $length && trim($text[$next]) === '') {
            ++$next;
        }

        if ($next >= $length || !isset($closers[$next])) {
            return null;
        }

        $lineStart = strrpos(substr($text, 0, $next), "\n");
        $from = $end + (str_starts_with(substr($text, $end, 2), $eol) ? strlen($eol) : 0);
        $to = $lineStart === false ? 0 : $lineStart + 1;

        return $to > $from ? [$from, $to] : null;
    }
}
