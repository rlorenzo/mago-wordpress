<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function ctype_space;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Ports the `|` of `WordPress.WhiteSpace.OperatorSpacing` for the types of a multi-catch
 * (`catch ( A | B $e )`): one space on each side (`NoSpaceBefore`, `NoSpaceAfter`, `SpacingBefore`,
 * `SpacingAfter`), a line break counting as fine, as WordPress sets `ignoreNewlines`. `mago format`
 * writes `A|B` there; it keeps the spaces around every other operator, so those are not checked.
 * Off by default; it is meant to run after `mago format`, as
 * `mago lint --fix --only wordpress/operator-spacing` (a step of `mago-wordpress format`).
 *
 * Widths are counted with a tab width of 4, as the `WordPress` ruleset sets. A comment before the
 * operator makes a wrong width an error phpcs cannot fix; it is reported here without a fix.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class OperatorSpacingRule implements Rule
{
    private const SNIFF = 'WordPress.WhiteSpace.OperatorSpacing';

    private const TAB_WIDTH = 4;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/operator-spacing',
            name: 'Operator spacing',
            description: 'Requires one space on each side of the `|` between the types of a multi-catch. Off by default; run after `mago format`.',
            defaultLevel: Level::Error,
            defaultEnabled: false,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        $text = $context->file->contents;
        foreach ($tokens->codes as $index => $code) {
            if ($code === '|' && self::inCatch($tokens, $index)) {
                $this->check($context, $text, $tokens->pos($index));
            }
        }
    }

    private static function inCatch(PhpcsTokens $tokens, int $index): bool
    {
        $nested = $tokens->nested($index);
        $open = $nested === [] ? null : $nested[count($nested) - 1];
        if ($open === null) {
            return false;
        }

        return $tokens->code($open - 1) === 'T_CATCH';
    }

    private function check(LintContext $context, string $text, int $at): void
    {
        $start = $at;
        while ($start > 0 && ctype_space($text[$start - 1])) {
            --$start;
        }

        $end = $at + 1;
        while ($end < strlen($text) && ctype_space($text[$end])) {
            ++$end;
        }

        $this->side($context, $text, $at, [$start, $at], 'before');
        $this->side($context, $text, $at, [$at + 1, $end], 'after');
    }

    /**
     * @param array{int, int} $gap the whitespace on one side of the operator
     */
    private function side(LintContext $context, string $text, int $at, array $gap, string $side): void
    {
        [$from, $to] = $gap;
        $space = substr($text, $from, $to - $from);
        if ($space !== '' && (str_contains($space, "\n") || self::width($text, $from, $to) === 1)) {
            return;
        }

        $found = $space === '' ? '0' : (string) self::width($text, $from, $to);
        $ucSide = $side === 'before' ? 'Before' : 'After';
        $edit = $space === ''
            ? TextEdit::insert($side === 'before' ? $at : $at + 1, ' ')
            : TextEdit::replace(new Span($from, $to), ' ');
        if ($side === 'before' && str_ends_with(substr($text, 0, $from), '*/')) {
            $edit = null;
        }

        $this->add(
            $context,
            $at,
            "Expected 1 space {$side} \"|\"; {$found} found",
            ($space === '' ? 'NoSpace' : 'Spacing') . $ucSide,
            $edit,
        );
    }

    private function add(LintContext $context, int $at, string $message, string $code, ?TextEdit $edit): void
    {
        $issue = Issue::new($message, new Span($at, $at + 1));
        if ($edit !== null) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }

    /** The display width of the whitespace $from..$to, with tabs going to the next multiple of the tab width. */
    private static function width(string $text, int $from, int $to): int
    {
        $newline = strrpos(substr($text, 0, $from), "\n");
        $line = substr($text, $newline === false ? 0 : $newline + 1, $from - ($newline === false ? 0 : $newline + 1));
        $column = 0;
        for ($i = 0, $length = strlen($line); $i < $length; ++$i) {
            $column += $line[$i] === "\t" ? self::TAB_WIDTH - ($column % self::TAB_WIDTH) : 1;
        }

        $width = 0;
        for ($i = $from; $i < $to; ++$i) {
            $step = $text[$i] === "\t" ? self::TAB_WIDTH - ($column % self::TAB_WIDTH) : 1;
            $width += $step;
            $column += $step;
        }

        return $width;
    }
}
