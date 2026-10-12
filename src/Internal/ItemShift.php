<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;

use function ltrim;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * The edits that re-indent the later lines of a value moved onto a line of its own, so a nested
 * array keeps its shape. The text inside a multi-line string is left as it is.
 *
 * @internal
 */
final class ItemShift
{
    private function __construct() {}

    /**
     * @return list<TextEdit>
     */
    public static function edits(PhpcsTokens $tokens, string $text, int $start, int $end, string $indent): array
    {
        $delta = self::delta($text, $start, $indent);
        if ($delta === '' || strpos(substr($text, $start, $end - $start), "\n") === false) {
            return [];
        }

        $protected = MultilineTokens::within($tokens, $start, $end);
        $edits = [];
        $at = strpos($text, "\n", $start);
        while ($at !== false && $at < $end) {
            $lineStart = $at + 1;
            $at = strpos($text, "\n", $lineStart);
            if ($lineStart >= $end || !MultilineTokens::isCode($protected, $lineStart)) {
                continue;
            }

            $line = substr($text, $lineStart, ($at === false ? strlen($text) : $at) - $lineStart);
            if (trim($line) !== '') {
                $edits[] = TextEdit::insert($lineStart, $delta);
            }
        }

        return $edits;
    }

    /** What the new item indent adds to the indent of the line the item starts on. */
    private static function delta(string $text, int $start, string $indent): string
    {
        $newline = strrpos(substr($text, 0, $start), "\n");
        $lineStart = $newline === false ? 0 : $newline + 1;
        $lead = substr($text, $lineStart, $start - $lineStart);
        if (trim($lead) !== '') {
            $lead = substr($lead, 0, strlen($lead) - strlen(ltrim($lead)));
        }

        return substr($indent, 0, strlen($lead)) === $lead ? substr($indent, strlen($lead)) : '';
    }
}
