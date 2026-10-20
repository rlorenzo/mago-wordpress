<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

/**
 * Ports `WordPress.WhiteSpace.CastStructureSpacing`: a type cast is preceded by whitespace
 * (or by the spread operator `...`).
 */
final class CastStructureSpacingRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/cast-structure-spacing',
            name: 'Cast structure spacing',
            description: 'Requires a space before a type cast.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $index => $token) {
            if (!isset(Spacing::CASTS[$token->code]) || $index === 0) {
                continue;
            }

            if (Spacing::wsBefore($tokens, $index) !== null || $tokens[$index - 1]->code === 'T_ELLIPSIS') {
                continue;
            }

            Spacing::report(
                $this->report,
                $context,
                $token,
                'Expected a space before the type cast open parenthesis; none found',
                ['WordPress.WhiteSpace.CastStructureSpacing.NoSpaceBeforeOpenParenthesis'],
                [TextEdit::insert($token->pos, ' ')],
            );
        }
    }
}
