<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\SpacesFixer;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function strlen;

/**
 * Ports `Universal.Attributes.BracketSpacing` (`SpaceAfterOpener`, `SpaceBeforeCloser`) with
 * `spacing` 0 and `ignoreNewlines` off, the sniff's defaults: `#[Attr]`, not `#[ Attr ]`,
 * and no line breaks inside the brackets. An empty `#[]` is left alone.
 */
final class AttributeBracketSpacingRule implements Rule
{
    private const SNIFF = 'Universal.Attributes.BracketSpacing';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/attribute-bracket-spacing',
            name: 'Attribute bracket spacing',
            description: 'Forbids whitespace and line breaks just inside the brackets of an attribute block.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            if ($token->code !== 'T_ATTRIBUTE' || $token->closer === null || $token->closer === ($i + 1)) {
                continue;
            }

            $next = $i + 1;
            while ($tokens[$next]->code === 'T_WHITESPACE') {
                ++$next;
            }

            SpacesFixer::check(
                $context,
                $this->report,
                $token->pos + strlen($token->text),
                $tokens[$next]->pos,
                0,
                'Expected %1$s after the attribute block opener. Found: %2$s.',
                self::SNIFF . '.SpaceAfterOpener',
                Spacing::span($token),
            );

            $closer = $token->closer;
            $prev = $closer - 1;
            while ($tokens[$prev]->code === 'T_WHITESPACE') {
                --$prev;
            }

            SpacesFixer::check(
                $context,
                $this->report,
                $tokens[$prev]->pos + strlen($tokens[$prev]->text),
                $tokens[$closer]->pos,
                0,
                'Expected %1$s before the attribute block closer. Found: %2$s.',
                self::SNIFF . '.SpaceBeforeCloser',
                Spacing::span($tokens[$prev]),
            );
        }
    }
}
