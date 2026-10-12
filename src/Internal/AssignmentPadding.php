<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function array_slice;
use function count;

/**
 * The padding `Generic.Formatting.MultipleStatementAlignment` wants before the equals signs of a
 * block of adjacent assignments (`alignAtEnd` on, `maxPadding` 40 as WordPress-Core sets it), a
 * port of the sniff's `checkAlignment()` over the assignments' columns.
 *
 * @internal
 */
final class AssignmentPadding
{
    private function __construct() {}

    /**
     * @param list<array{varEnd: int, length: int, found: int, ...}> $block the assignments in source order
     *
     * @return list<array{index: int, expected: int, found: int, single: bool}> every assignment whose padding is wrong
     */
    public static function problems(array $block): array
    {
        $problems = [];
        $start = 0;
        while ($start < count($block)) {
            [$assignments, $stopped] = self::segment(array_slice($block, $start, null, true));
            foreach ($assignments as $index => $data) {
                if ($data['found'] !== $data['expected']) {
                    $problems[] = [
                        'index' => $index,
                        'expected' => $data['expected'],
                        'found' => $data['found'],
                        'single' => count($assignments) === 1,
                    ];
                }
            }

            if ($stopped === null) {
                break;
            }

            $start = $stopped;
        }

        return $problems;
    }

    /**
     * Aligns assignments from the first until one would need more than the maximum padding.
     *
     * @param array<int, array{varEnd: int, length: int, found: int, ...}> $block
     *
     * @return array{array<int, array{var_end: int, assign_len: int, assign_col: int, expected: int, found: int}>, null|int}
     */
    private static function segment(array $block): array
    {
        $assignments = [];
        $previous = null;
        $widest = null;
        foreach ($block as $index => $assign) {
            $varEnd = $assign['varEnd'];
            $length = $assign['length'];
            if ($previous === null) {
                $padding = 1;
                $column = $varEnd + 1;
                $widest = $index;
            } else {
                $aligned = PaddingFit::align($assignments, [$previous, $widest ?? $previous, $index], $varEnd, $length);
                if ($aligned === null) {
                    return [$assignments, $index];
                }

                [$padding, $column, $widest, $assignments] = $aligned;
            }

            $assignments[$index] = [
                'var_end' => $varEnd,
                'assign_len' => $length,
                'assign_col' => $column,
                'expected' => $padding,
                'found' => $assign['found'],
            ];
            $previous = $index;
        }

        return [$assignments, null];
    }
}
