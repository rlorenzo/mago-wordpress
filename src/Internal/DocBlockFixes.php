<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function in_array;
use function preg_match;
use function strlen;
use function strrpos;
use function substr;

/**
 * The edits `Generic.Commenting.DocComment`'s fixers make, over a docblock's phpcs tokens.
 *
 * @internal
 */
final class DocBlockFixes
{
    private function __construct() {}

    /**
     * Deletes tokens $from up to the one before the first on line $line, as the sniffs' loops do.
     *
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    public static function removal(array $t, int $from, int $to, int $line): TextEdit
    {
        $end = $t[$to]['offset'];
        for ($i = $from; $i < $to; ++$i) {
            if ($t[$i + 1]['line'] === $line) {
                $end = $t[$i]['offset'];

                break;
            }
        }

        return TextEdit::delete(new Span($t[$from]['offset'], $end));
    }

    /**
     * The `SpacingBetween` fix: drop tokens after the short description's line, keeping one blank line.
     *
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    public static function betweenFix(array $t, int $end, int $long): TextEdit
    {
        $from = null;
        $until = $t[$long]['offset'];
        for ($i = $end + 1; $i < $long; ++$i) {
            if ($t[$i]['line'] === $t[$end]['line']) {
                continue;
            }

            if ($t[$i]['line'] === ($t[$long]['line'] - 1)) {
                $until = $t[$i]['offset'];

                break;
            }

            $from ??= $t[$i]['offset'];
        }

        $from ??= $until;

        return TextEdit::delete(new Span($from, $until));
    }

    /**
     * Replaces what lies between $prev and the line of $next with one blank docblock line.
     *
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    public static function blankLineFix(array $t, int $prev, int $next, string $indent, string $source): TextEdit
    {
        $start = $t[$prev]['offset'] + strlen($t[$prev]['text']);
        $end = $t[$next]['offset'];
        for ($i = $prev + 1; $i < $next; ++$i) {
            if ($t[$i]['line'] === $t[$next]['line']) {
                $end = $t[$i]['offset'];

                break;
            }
        }

        $match = [];
        $eol = preg_match('/\r\n?|\n/', $source, $match) === 1 ? $match[0] : "\n";

        return TextEdit::replace(new Span($start, $end), $eol . $indent . '*' . $eol);
    }
}
