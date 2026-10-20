<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\SpacesFixer;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function strlen;

/**
 * Ports `Universal.WhiteSpace.AnonClassKeywordSpacing` (`Incorrect`) with `spacing` 0, as
 * WordPress-Core sets it: no space between `class` and `(` in `new class( ... )`.
 */
final class AnonClassKeywordSpacingRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/anon-class-keyword-spacing',
            name: 'Anonymous class keyword spacing',
            description: 'Forbids space between `class` and the opening parenthesis of an anonymous class.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = Spacing::tokens($context);
        foreach ($tokens as $i => $token) {
            if ($token->code !== 'T_ANON_CLASS' || $token->scopeOpener === null) {
                continue;
            }

            $next = PhpcsTokenStream::next($tokens, $i);
            if ($next === null || $tokens[$next]->code !== 'T_OPEN_PARENTHESIS') {
                continue;
            }

            SpacesFixer::check(
                $context,
                $this->report,
                $token->pos + strlen($token->text),
                $tokens[$next]->pos,
                0,
                'There must be %1$s between the class keyword and the open parenthesis for an anonymous class. Found: %2$s',
                'Universal.WhiteSpace.AnonClassKeywordSpacing.Incorrect',
                Spacing::span($token),
            );
        }
    }
}
