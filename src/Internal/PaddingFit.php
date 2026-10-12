<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

/**
 * Fitting one more assignment into a block of aligned equals signs, as the alignment sniff does.
 *
 * @internal
 */
final class PaddingFit
{
    private const MAX_PADDING = 40;

    private function __construct() {}

    /**
     * @param array<int, array{var_end: int, assign_len: int, assign_col: int, expected: int, found: int}> $assignments
     * @param array{int, int, int} $at the previous assignment, the one with the widest padding, the current one
     *
     * @return null|array{int, int, int, array<int, array{var_end: int, assign_len: int, assign_col: int, expected: int, found: int}>}
     *                                                                                                         padding, column, widest, assignments; NULL when the padding is too wide
     */
    public static function align(array $assignments, array $at, int $varEnd, int $length): ?array
    {
        [$previous, $widest, $current] = $at;
        $before = $assignments[$previous];
        if (($varEnd + 1) > $before['assign_col']) {
            $padding = 1;
            $column = $varEnd + 1;
        } else {
            $padding = $before['assign_col'] - $varEnd + $before['assign_len'] - $length;
            if ($padding <= 0) {
                $padding = 1;
            }

            if ($padding > self::MAX_PADDING) {
                return null;
            }

            $column = $varEnd + $padding;
        }

        $max = $assignments[$widest];
        if (($column + $length) > ($max['assign_col'] + $max['assign_len'])) {
            $newPadding = $varEnd - $max['var_end'] + $length - $max['assign_len'] + 1;
            if ($newPadding > self::MAX_PADDING) {
                return null;
            }

            foreach ($assignments as $i => $data) {
                $expected = $varEnd - $data['var_end'] + $length - $data['assign_len'] + 1;
                $assignments[$i]['expected'] = $expected;
                $assignments[$i]['assign_col'] = $data['var_end'] + $expected;
            }

            return [1, $varEnd + 1, $widest, $assignments];
        }

        if ($padding > $max['expected']) {
            $widest = $current;
        }

        return [$padding, $column, $widest, $assignments];
    }
}
