<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function max;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strcspn;
use function strlen;
use function strpos;
use function strspn;
use function substr;
use function trim;

/**
 * `PHPCSUtils\Fixers\SpacesFixer::checkAndFix()` for two adjacent tokens A (ending at `$aEnd`) and B
 * (starting at `$bStart`) separated by whitespace and/or comments: the gap must be `$expected`
 * spaces, or a line break when `$expected` is `newline`. Reports at `$at`, with a fix when only
 * whitespace separates the two.
 *
 * @internal
 */
final class SpacesFixer
{
    private function __construct() {}

    /**
     * @param int|'newline' $expected
     * @param string $template with `%1$s` (expected) and `%2$s` (found)
     * @mago-expect lint:excessive-parameter-list
     */
    public static function check(
        LintContext $context,
        Report $report,
        int $aEnd,
        int $bStart,
        int|string $expected,
        string $template,
        string $code,
        Span $at,
    ): void {
        $file = $context->file;
        $text = $file->contents;
        $found = 0;
        $phrase = 'no spaces';
        $lineA = LineIndex::of($file, $aEnd - 1);
        $lineB = LineIndex::of($file, $bStart);
        if ($lineA !== $lineB) {
            $found = 'newline';
            $phrase = ($lineA + 1) === $lineB ? 'a new line' : 'multiple new lines';
        } elseif ($aEnd !== $bStart) {
            $run = strspn($text, " \t", $aEnd);
            if ($run > 0) {
                // phpcs measures the whitespace token after tab replacement.
                $found = strlen(Spacing::expand($text, substr($text, $aEnd, $run), $aEnd));
                $phrase = $found . ($found === 1 ? ' space' : ' spaces');
            } else {
                $found = 'non-whitespace tokens';
                $phrase = 'non-whitespace tokens';
            }
        }

        if ($found === $expected) {
            return;
        }

        $wanted = match (true) {
            $expected === 'newline' => 'a new line',
            $expected === 0 => 'no space',
            $expected === 1 => '1 space',
            default => $expected . ' spaces',
        };
        $issue = Issue::new(sprintf($template, $wanted, $phrase), $at);
        if (trim(substr($text, $aEnd, $bStart - $aEnd)) === '') {
            $eol = str_contains($text, "\r\n") ? "\r\n" : "\n";
            $issue = $issue->withEdit(TextEdit::replace(
                new Span($aEnd, $bStart),
                $expected === 'newline' ? $eol : str_repeat(' ', max(0, (int) $expected)),
            ));
        }

        $report->issue($context, $issue, [$code]);
    }

    /** The end of the comment starting at $offset, or null when none does. */
    public static function commentEnd(string $text, int $offset): ?int
    {
        $two = substr($text, $offset, 2);
        if ($two === '/*') {
            $close = strpos($text, '*/', $offset + 2);

            return $close === false ? strlen($text) : $close + 2;
        }

        if ($two === '//' || ($text[$offset] ?? '') === '#' && $two !== '#[') {
            return $offset + strcspn($text, "\r\n", $offset);
        }

        return null;
    }
}
