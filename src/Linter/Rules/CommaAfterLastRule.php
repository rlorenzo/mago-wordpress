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
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\ArrayShape;
use Rlorenzo\MagoWordPress\Internal\LineIndex;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\PhpComments;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function preg_match;
use function substr;

/**
 * Ports `NormalizedArrays.Arrays.CommaAfterLast` as WordPress-Core sets it (the sniff's defaults):
 * a multi-line array needs a comma after its last item (`MissingMultiLine`, and
 * `MissingMultiLineCloserSameLine` when the closing bracket is on that item's line), and a
 * single-line array must not have one (`FoundSingleLine`). `wordpress/array-declaration-spacing`
 * breaks an array onto lines but leaves the last item without a comma, so this runs after it, as
 * `mago lint --fix --only wordpress/comma-after-last` (a step of `mago-wordpress format`); it is off
 * by default.
 *
 * A missing comma is reported at the last character of the last item, which is the sniff's last token
 * unless that token spans lines (a multi-line string), and a forbidden one at the comma; the line of the closing bracket is compared with that
 * character's, so a multi-line last string ending on the bracket's line gets the
 * `CloserSameLine` code where phpcs gives it the plain one. Short lists are left out, as the
 * sniff leaves them.
 */
final class CommaAfterLastRule implements Rule
{
    private const SNIFF = 'NormalizedArrays.Arrays.CommaAfterLast';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/comma-after-last',
            name: 'Comma after last array item',
            description: 'Requires a comma after the last item of a multi-line array and forbids one in a single-line array. Off by default; run after `wordpress/array-declaration-spacing`.',
            defaultLevel: Level::Error,
            defaultEnabled: false,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach ([NodeKind::LegacyArray, NodeKind::Array] as $kind) {
            foreach (NodeIndex::ofKind($context->file, $context->node, $kind) as $array) {
                $this->array($context, $array);
            }
        }
    }

    private function array(LintContext $context, Node $array): void
    {
        $file = $context->file;
        $shape = ArrayShape::of($file, $array);
        if ($shape === null || $shape['items'] === []) {
            return;
        }

        $text = $file->contents;
        $last = $shape['items'][count($shape['items']) - 1];
        $after = PhpComments::skipEmpty($text, $last['end']);
        $comma = ($text[$after] ?? '') === ',' ? $after : null;
        if ($comma === null && $after !== $shape['close']) {
            return;
        }

        $multiLine = LineIndex::of($file, $shape['open']) !== LineIndex::of($file, $shape['close']);
        if ($multiLine && $comma === null) {
            $this->missing($context, $shape['close'], $last);
        } elseif (!$multiLine && $comma !== null) {
            $this->found($context, $comma);
        }
    }

    /**
     * @param array{start: int, end: int, keyed: bool} $last
     */
    private function missing(LintContext $context, int $close, array $last): void
    {
        $text = $context->file->contents;
        $sameLine = LineIndex::of($context->file, $last['end'] - 1) === LineIndex::of($context->file, $close);
        $item = substr($text, $last['start'], $last['end'] - $last['start']);
        $this->report->issue(
            $context,
            Issue::new(
                'There should be a comma after the last array item in a multi-line array.',
                new Span($last['end'] - 1, $last['end']),
            )->withEdit(TextEdit::insert($last['end'], self::comma($item, $text))),
            [self::SNIFF . ($sameLine ? '.MissingMultiLineCloserSameLine' : '.MissingMultiLine')],
        );
    }

    private function found(LintContext $context, int $comma): void
    {
        $this->report->issue(
            $context,
            Issue::new(
                'A comma after the last array item in a single-line array is not allowed.',
                new Span($comma, $comma + 1),
            )->withEdit(TextEdit::delete(new Span($comma, $comma + 1))),
            [self::SNIFF . '.FoundSingleLine'],
        );
    }

    /**
     * The text to insert after the last item: a comma, on a line of its own after an unindented
     * heredoc or nowdoc terminator, which PHP before 7.3 cannot follow with one.
     */
    private static function comma(string $item, string $text): string
    {
        if (preg_match('/<<<\h*[\'"]?\w+[\'"]?\r?\n.*\n\w+$/sD', $item) !== 1) {
            return ',';
        }

        $eol = [];
        preg_match('/\r\n?|\n/', $text, $eol);

        return ($eol[0] ?? "\n") . ',';
    }
}
