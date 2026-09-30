<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

use function explode;
use function rtrim;
use function str_starts_with;
use function strlen;

/**
 * Ports `Generic.VersionControl.GitMergeConflict`: a git merge conflict opener
 * (`<<<<<<< HEAD`), delimiter (seven `=`) or closer (seven `>` and a space) at the start
 * of a line.
 * In PHP code these also fail to parse; this rule also finds them in inline HTML,
 * comments and heredocs, where the file still parses.
 */
final class GitMergeConflictRule implements Rule
{
    private const SNIFF = 'Generic.VersionControl.GitMergeConflict';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/git-merge-conflict',
            name: 'Git merge conflict',
            description: 'Reports git merge conflict markers left in a file.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // ponytail: a line scan, so a marker line inside a multi-line quoted string is reported
        // too (phpcs skips those tokens); a token walk if that ever matters.
        $offset = 0;
        foreach (explode("\n", $context->file->contents) as $line) {
            $type = match (true) {
                str_starts_with($line, '<<<<<<< HEAD') => ['opener', 'OpenerFound'],
                str_starts_with($line, '>>>>>>> ') => ['closer', 'CloserFound'],
                rtrim($line, characters: "\r") === '=======' => ['delimiter', 'DelimiterFound'],
                default => null,
            };
            if ($type !== null) {
                $this->report->issue(
                    $context,
                    Issue::new(
                        "Merge conflict boundary found; type: {$type[0]}",
                        new Span($offset, $offset + strlen(rtrim($line, characters: "\r"))),
                        "merge conflict {$type[0]}",
                    )->withHelp('Resolve the merge conflict and remove the markers.'),
                    [self::SNIFF . '.' . $type[1]],
                );
            }

            $offset += strlen($line) + 1;
        }
    }
}
