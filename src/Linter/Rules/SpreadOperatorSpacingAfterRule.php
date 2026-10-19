<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

/**
 * Ports `Generic.WhiteSpace.SpreadOperatorSpacingAfter` with its defaults (`spacing` 0,
 * `ignoreNewlines` false), which WordPress-Core keeps: nothing between `...` and what it spreads
 * (`CommentFound`, `TooMuchSpace`).
 */
final class SpreadOperatorSpacingAfterRule implements Rule
{
    private const SNIFF = 'Generic.WhiteSpace.SpreadOperatorSpacingAfter';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/spread-operator-spacing-after',
            name: 'Spread operator spacing after',
            description: 'Reports whitespace or a comment between the spread operator and its operand.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            if ($token->code !== 'T_ELLIPSIS') {
                continue;
            }

            $nextNonEmpty = PhpcsTokenStream::next($tokens, $i);
            if (
                $nextNonEmpty === null
                || $nextNonEmpty === ($i + 1)
                || $tokens[$nextNonEmpty]->code === 'T_CLOSE_PARENTHESIS'
            ) {
                continue;
            }

            $nextNonWhitespace = $i + 1;
            while (Spacing::isWs($tokens, $nextNonWhitespace)) {
                $nextNonWhitespace++;
            }

            if ($nextNonEmpty !== $nextNonWhitespace) {
                Spacing::report(
                    $this->report,
                    $context,
                    $token,
                    'Expected 0 spaces after the spread operator; comment found',
                    [self::SNIFF . '.CommentFound'],
                );
                continue;
            }

            $found = Spacing::gap($context->file->contents, $tokens, $i, true);
            Spacing::report(
                $this->report,
                $context,
                $token,
                "Expected 0 spaces after the spread operator; {$found} found",
                [self::SNIFF . '.TooMuchSpace'],
                [TextEdit::replace(new Span($tokens[$i + 1]->pos, $tokens[$nextNonEmpty]->pos), '')],
            );
        }
    }
}
