<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

/**
 * Describing the whitespace between two items of an array.
 *
 * @internal
 */
final class GapText
{
    private function __construct() {}

    /** How many whitespace bytes start the text at $from. */
    public static function leadingSpace(string $text, int $from): int
    {
        $length = 0;
        while (
            isset($text[$from + $length])
            && (
                $text[$from + $length] === ' '
                || $text[$from + $length] === "\t"
                || $text[$from + $length] === "\n"
                || $text[$from + $length] === "\r"
            )
        ) {
            ++$length;
        }

        return $length;
    }

    /** The "Found: ..." text of the sniff for the whitespace between two offsets. */
    public static function found(string $text, int $from, int $to): string
    {
        $width = ArrayText::gapWidth($text, $from, $to);

        return match (true) {
            $width === 0 => 'no spaces',
            $width === 1 => '1 space',
            default => $width . ' spaces',
        };
    }
}
