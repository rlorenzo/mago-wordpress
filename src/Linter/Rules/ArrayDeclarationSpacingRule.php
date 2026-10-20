<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\ArrayBreaks;
use Rlorenzo\MagoWordPress\Internal\ArrayLayout;
use Rlorenzo\MagoWordPress\Internal\ArrayShape;
use Rlorenzo\MagoWordPress\Internal\ArrayText;
use Rlorenzo\MagoWordPress\Internal\GapText;
use Rlorenzo\MagoWordPress\Internal\LineIndex;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function max;
use function preg_match;
use function sprintf;
use function str_repeat;
use function strpos;
use function substr;

/**
 * Ports `WordPress.Arrays.ArrayDeclarationSpacing` (`AssociativeArrayFound`, `ArrayItemNoNewLine`):
 * an array with explicit keys and several items puts each item on a line of its own, and so does
 * every multi-line array. `mago format` keeps a short keyed array on one line, so this runs after
 * it, as `mago lint --fix --only wordpress/array-declaration-spacing` (a step of
 * `mago-wordpress format`). The fix breaks the array the way phpcbf does over
 * its passes, with the indentation the full ruleset gives: items one tab in from the line the array
 * starts on, the closing bracket on that indentation.
 */
final class ArrayDeclarationSpacingRule implements Rule
{
    private const SNIFF = 'WordPress.Arrays.ArrayDeclarationSpacing';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/array-declaration-spacing',
            name: 'Array declaration spacing',
            description: 'Puts each item of a multi-item array with explicit keys, and of any multi-line array, on its own line. `mago-wordpress format` runs its fix after `mago format`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
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
        $match = [];
        $eol = preg_match('/\r\n?|\n/', $text, $match) === 1 ? $match[0] : "\n";
        $base =
            ArrayText::lineIndent($text, $shape['open'])
            . str_repeat("\t", max(0, ArrayLayout::depth($file, $array, $shape['open'])));
        if (LineIndex::of($file, $shape['open']) === LineIndex::of($file, $shape['close'])) {
            $this->singleLine($context, $shape, $base, $eol);

            return;
        }

        $this->multiLine($context, $shape, $base, $eol);
    }

    /**
     * @param array{open: int, close: int, items: list<array{start: int, end: int, keyed: bool}>} $shape
     */
    private function singleLine(LintContext $context, array $shape, string $base, string $eol): void
    {
        if (!ArrayLayout::breaksFirst($context->file, $shape)) {
            return;
        }

        $issue = Issue::new(
            'When a multi-item array is declared with explicit keys, each value should start on a new line.',
            new Span($shape['close'], $shape['close'] + 1),
        );
        foreach (ArrayBreaks::breakAll($context->file->contents, $shape, $base, $eol) as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.AssociativeArrayFound']);
    }

    /**
     * @param array{open: int, close: int, items: list<array{start: int, end: int, keyed: bool}>} $shape
     */
    private function multiLine(LintContext $context, array $shape, string $base, string $eol): void
    {
        $text = $context->file->contents;
        $indent = ArrayLayout::itemIndent($text, $shape, $base);
        $previous = $shape['open'];
        foreach ($shape['items'] as $item) {
            $gap = substr($text, $previous + 1, $item['start'] - $previous - 1);
            if (strpos($gap, "\n") === false) {
                $this->report($context, $previous, $item, $indent, $eol);
            }

            $comma = strpos($text, ',', $item['end']);
            $previous = $comma !== false && $comma < $shape['close'] ? $comma : $item['end'] - 1;
        }
    }

    /**
     * @param array{start: int, end: int, keyed: bool} $item
     */
    private function report(LintContext $context, int $previous, array $item, string $indent, string $eol): void
    {
        $text = $context->file->contents;
        $at = $previous + 1 + GapText::leadingSpace($text, $previous + 1);
        $issue = Issue::new(
            sprintf('Each item in a multi-line array must be on a new line. Found: %s', GapText::found(
                $text,
                $previous + 1,
                $at,
            )),
            new Span($at, $at + 1),
        );
        foreach (ArrayBreaks::moveItem($context->file, $previous, $item, $indent, $eol) as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.ArrayItemNoNewLine']);
    }
}
