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

use function str_contains;
use function str_replace;

/**
 * Ports `Generic.WhiteSpace.HereNowdocIdentifierSpacing`: no space or tab between `<<<` and
 * the heredoc or nowdoc identifier (`SpaceFound`).
 */
final class HereNowdocIdentifierSpacingRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/here-nowdoc-identifier-spacing',
            name: 'Heredoc and nowdoc identifier spacing',
            description: 'Reports whitespace (spaces or tabs) between <<< and a heredoc or nowdoc identifier.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        foreach (Spacing::tokens($context) as $token) {
            if (
                $token->code !== 'T_START_HEREDOC' && $token->code !== 'T_START_NOWDOC'
                || !str_contains($token->text, ' ') && !str_contains($token->text, "\t")
            ) {
                continue;
            }

            Spacing::report(
                $this->report,
                $context,
                $token,
                'There should be no space between the <<< and the heredoc/nowdoc identifier string. Found: '
                . $token->text,
                ['Generic.WhiteSpace.HereNowdocIdentifierSpacing.SpaceFound'],
                [Spacing::replace($token, str_replace([' ', "\t"], '', $token->text))],
            );
        }
    }
}
