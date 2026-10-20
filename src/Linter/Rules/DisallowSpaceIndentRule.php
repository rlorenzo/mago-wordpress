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

use function intdiv;
use function max;
use function str_repeat;
use function strpos;
use function substr;
use function substr_count;

/**
 * Ports `Generic.WhiteSpace.DisallowSpaceIndent` with the tab width of 4 the `WordPress` ruleset sets:
 * indentation is tabs; spaces are allowed only as the precision part after them (fewer than a tab
 * width, at the end), and the fix is that many tabs and spaces. The closer of a heredoc or nowdoc is
 * reported but not fixed, as in the sniff. Blank lines holding only spaces are reported too.
 */
final class DisallowSpaceIndentRule implements Rule
{
    private const SNIFF = 'Generic.WhiteSpace.DisallowSpaceIndent';

    private const MESSAGE = 'Tabs must be used to indent lines; spaces are not allowed';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-space-indent',
            name: 'Disallow space indent',
            description: 'Reports indentation that uses spaces where tabs are expected, apart from precision alignment after the tabs.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        foreach (LineIndents::of($context->file) as $line) {
            $indent = substr($text, $line['start'], $line['length']);
            $spaces = substr_count($indent, ' ');
            if ($spaces === 0) {
                continue;
            }

            $kind = $line['kind'];
            if (($kind === LineIndents::DOC || $kind === LineIndents::COMMENT) && $indent === ' ') {
                continue;
            }

            $tabs = $line['length'] - $spaces;
            // A comment or HTML line that has text counts each tab as a full tab width; the other
            // tokens (and blank lines) reach the next tab stop, and a closer counts its marker too.
            $size = match (true) {
                ($kind === LineIndents::COMMENT || $kind === LineIndents::HTML) && !$line['blank'] => $spaces
                    + ($tabs * LineIndents::TAB_WIDTH),
                $kind === LineIndents::CLOSER => LineIndents::width($indent) + $line['tail'],
                default => LineIndents::width($indent),
            };
            $expectedTabs = max(0, intdiv($size, LineIndents::TAB_WIDTH));
            $expectedSpaces = max(0, $size % LineIndents::TAB_WIDTH);
            if (
                $tabs === $expectedTabs
                && $spaces === $expectedSpaces
                && ($tabs === 0 || strpos($indent, "\t", (int) strpos($indent, ' ')) === false)
            ) {
                continue;
            }

            $span = new Span($line['start'], $line['start'] + $line['length']);
            if ($kind === LineIndents::CLOSER) {
                $this->report->issue(
                    $context,
                    Issue::new(self::MESSAGE, $span),
                    [self::SNIFF . '.SpacesUsedHeredocCloser'],
                );
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(self::MESSAGE, $span)->withEdit(TextEdit::replace(
                    $span,
                    str_repeat("\t", $expectedTabs) . str_repeat(' ', $expectedSpaces),
                )),
                [self::SNIFF . '.SpacesUsed'],
            );
        }
    }
}
