<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function count;
use function str_contains;
use function strlen;

/**
 * The code tokens that span several lines (strings, heredocs), whose inner lines a re-indent must not touch.
 *
 * @internal
 */
final class MultilineTokens
{
    private function __construct() {}

    /**
     * @return list<array{int, int}>
     */
    public static function within(PhpcsTokens $tokens, int $start, int $end): array
    {
        $ranges = [];
        $index = $tokens->indexAt($start);
        for ($i = $index ?? count($tokens->codes); $i < count($tokens->codes) && $tokens->pos($i) < $end; ++$i) {
            $content = $tokens->content($i);
            if (str_contains($content, "\n")) {
                $ranges[] = [$tokens->pos($i), $tokens->pos($i) + strlen($content)];
            }
        }

        return $ranges;
    }

    /**
     * @param list<array{int, int}> $ranges
     */
    public static function isCode(array $ranges, int $offset): bool
    {
        foreach ($ranges as [$from, $to]) {
            if ($offset > $from && $offset < $to) {
                return false;
            }
        }

        return true;
    }
}
