<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function count;
use function intdiv;
use function strpos;

/**
 * Line numbers of offsets, from the newline offsets of the file being linted.
 *
 * @internal
 */
final class LineIndex
{
    private function __construct() {}

    /** The 1-based line of an offset. */
    public static function of(SourceFile $file, int $offset): int
    {
        /** @var list<int> $starts */
        $starts = FileCache::remember($file, 'inline-comment-lines', static function () use ($file): array {
            $starts = [];
            $offset = 0;
            while (($found = strpos($file->contents, "\n", $offset)) !== false) {
                $starts[] = $found;
                $offset = $found + 1;
            }

            return $starts;
        });
        $low = 0;
        $high = count($starts);
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($starts[$mid] < $offset) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low + 1;
    }
}
