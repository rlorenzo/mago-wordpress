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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function substr;
use function trim;

/**
 * Ports `Squiz.Commenting.EmptyCatchComment` (`Missing`): a `catch` block with nothing in it, not
 * even a comment saying why the exception is ignored. Mago's own `no-empty-catch-clause` reports
 * a `catch` that has a comment too, so the presets leave it off.
 */
final class EmptyCatchCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.EmptyCatchComment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/empty-catch-comment',
            name: 'Empty catch comment',
            description: 'Reports an empty `catch` block that has no comment explaining why the exception is not handled.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        $text = $context->file->contents;
        foreach ($tokens->codes as $index => $code) {
            if ($code !== 'T_CATCH') {
                continue;
            }

            $parenthesis = $tokens->closer($index + 1);
            $open = $parenthesis === null ? null : $parenthesis + 1;
            $close = $open === null ? null : $tokens->closer($open);
            if ($open === null || $close === null || $tokens->code($open) !== '{') {
                continue;
            }

            $from = $tokens->pos($open) + 1;
            if (trim(substr($text, $from, $tokens->pos($close) - $from)) !== '') {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    'Empty CATCH statement must have a comment to explain why the exception is not handled',
                    new Span($from - 1, $from),
                ),
                [self::SNIFF . '.Missing'],
            );
        }
    }
}
