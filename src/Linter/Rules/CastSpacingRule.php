<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function sprintf;
use function str_replace;

/**
 * Ports `Squiz.WhiteSpace.CastSpacing`: a type cast holds no whitespace, `( int )` is `(int)`
 * (`ContainsWhiteSpace`).
 */
final class CastSpacingRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/cast-spacing',
            name: 'Cast spacing',
            description: 'Reports whitespace inside a type cast.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach (Spacing::tokens($context) as $token) {
            if (!isset(Spacing::CASTS[$token->code])) {
                continue;
            }

            $expected = str_replace([' ', "\t"], '', $token->text);
            if ($expected === $token->text) {
                continue;
            }

            Spacing::report(
                $this->report,
                $context,
                $token,
                sprintf(
                    'Cast statements must not contain whitespace; expected "%s" but found "%s"',
                    $expected,
                    $token->text,
                ),
                ['Squiz.WhiteSpace.CastSpacing.ContainsWhiteSpace'],
                [Spacing::replace($token, $expected)],
            );
        }
    }
}
