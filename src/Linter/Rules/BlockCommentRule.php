<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\BlockCommentChecks;
use Rlorenzo\MagoWordPress\Internal\BlockCommentContext;
use Rlorenzo\MagoWordPress\Internal\BlockCommentRun;
use Rlorenzo\MagoWordPress\Internal\PhpcsComments;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function str_starts_with;

/**
 * Ports `Squiz.Commenting.BlockComment` as `WordPress-Docs` sets it, for `/* ... *\/` comments of
 * several lines: `Empty`, `NoNewLine`, `HasEmptyLine`, `NoCapital`, `CloserSameLine`,
 * `HasEmptyLineBefore` and `NoEmptyLineBefore`. A non-empty comment on one line is left alone; an
 * empty one (`/* *\/`) is still `Empty`, as in the sniff.
 *
 * `WordPress-Docs` turns off `SingleLine`, `FirstLineIndent`, `LineIndent`, `LastLineIndent`,
 * `WrongStart` and `NoEmptyLineAfter`; this rule does not report them.
 */
final class BlockCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.BlockComment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/block-comment',
            name: 'Block comment',
            description: 'Reports an empty block comment, or a multi-line one whose text starts on the opening line or in lower case, whose closer shares a line with text, or that lacks a blank line before it (or has one right after an opening brace or tag).',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $eol = BlockCommentContext::eol($file->contents);
        $tokens = PhpcsComments::tokens($file);
        for ($i = 0, $count = count($tokens); $i < $count; ++$i) {
            if ($tokens[$i]['type'] !== 'comment' || !str_starts_with($tokens[$i]['text'], '/*')) {
                continue;
            }

            $run = BlockCommentRun::from($tokens, $i);
            foreach (BlockCommentChecks::findings($file, $tokens, $i, $run, $eol) as [$message, $span, $code, $edit]) {
                $issue = Issue::new($message, $span);
                if ($edit !== null) {
                    $issue = $issue->withEdit($edit);
                }

                $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
            }
        }
    }
}
