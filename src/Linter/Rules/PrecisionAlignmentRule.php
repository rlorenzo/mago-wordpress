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
use Rlorenzo\MagoWordPress\Internal\LineIndents;
use Rlorenzo\MagoWordPress\Internal\Report;

use function floor;
use function intdiv;
use function max;
use function round;
use function sprintf;
use function str_contains;
use function str_repeat;
use function substr;

/**
 * Ports `Universal.WhiteSpace.PrecisionAlignment` with the defaults (`indent` the tab width of 4, blank
 * lines ignored): indentation whose width is not a multiple of 4 is a warning, and the fix rounds it to
 * the nearest tab stop (down for a heredoc or nowdoc closer), in tabs if the indentation had a tab,
 * in spaces otherwise. A space before the `*` of a comment line is not counted.
 */
final class PrecisionAlignmentRule implements Rule
{
    private const SNIFF = 'Universal.WhiteSpace.PrecisionAlignment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/precision-alignment',
            name: 'Precision alignment',
            description: 'Reports indentation that is not a multiple of the tab width (precision alignment with spaces).',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        $width = LineIndents::TAB_WIDTH;
        foreach (LineIndents::of($context->file) as $line) {
            $kind = $line['kind'];
            if ($line['blank'] && $kind !== LineIndents::CLOSER) {
                continue;
            }

            $indent = substr($text, $line['start'], $line['length']);
            $length = LineIndents::width($indent);
            $spaces = $length % $width;
            if ($spaces !== 0 && $line['star'] && $kind !== LineIndents::WHITESPACE && $kind !== LineIndents::CLOSER) {
                --$spaces;
            }

            if ($spaces === 0) {
                continue;
            }

            $closer = $kind === LineIndents::CLOSER;
            $stops = (int) ($closer ? floor($spaces / $width) : round($spaces / $width));
            $total = max(0, (intdiv($length, $width) + $stops) * $width);
            $replacement = str_contains($indent, "\t")
                ? str_repeat("\t", max(0, intdiv($total, $width))) . str_repeat(' ', max(0, $total % $width))
                : str_repeat(' ', $total);
            if ($stops === 0 && $line['star'] && $kind !== LineIndents::WHITESPACE && $kind !== LineIndents::CLOSER) {
                $replacement .= ' ';
            }

            $span = new Span($line['start'], $line['start'] + $line['length']);
            $this->report->issue(
                $context,
                Issue::new(
                    sprintf('Found precision alignment of %d spaces.', $spaces),
                    $span,
                )->withEdit(TextEdit::replace($span, $replacement)),
                [self::SNIFF . '.Found'],
            );
        }
    }
}
