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
use Rlorenzo\MagoWordPress\Internal\Report;

use function preg_match;
use function sprintf;
use function str_contains;
use function strpos;
use function strtr;

/**
 * Ports `Generic.Files.LineEndings` with `eolChar` set to `\n`, as WordPress-Core does: phpcs takes the file's
 * line ending from its first line break, and a file whose first break is not `\n` is reported once, on
 * line 1. The fix turns every `\r\n` and `\r` into `\n`.
 */
final class LineEndingsRule implements Rule
{
    private const SNIFF = 'Generic.Files.LineEndings';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/line-endings',
            name: 'Line endings',
            description: 'Reports a file whose line endings are not `\n` (Unix).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        // Cheap exit for the usual file: no carriage return, and no other kind of line break to be first.
        if (!str_contains($text, "\r") && preg_match('/[\x0B\x0C\x85]/', $text) !== 1) {
            return;
        }

        // phpcs detects the line ending with `\R`, the first break of any kind.
        if (preg_match('/\R/', $text, $first) !== 1 || $first[0] === "\n") {
            return;
        }

        $edits = [];
        for ($offset = strpos($text, "\r"); $offset !== false; $offset = strpos($text, "\r", $offset + 1)) {
            $width = ($text[$offset + 1] ?? '') === "\n" ? 2 : 1;
            $edits[] = TextEdit::replace(new Span($offset, $offset + $width), "\n");
        }

        $issue = Issue::new(
            sprintf('End of line character is invalid; expected "\n" but found "%s"', strtr($first[0], [
                "\n" => '\n',
                "\r" => '\r',
            ])),
            new Span(0, 1),
        );
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.InvalidEOLChar']);
    }
}
