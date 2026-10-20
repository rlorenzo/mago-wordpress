<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\KernighanRitchieBrace;
use Rlorenzo\MagoWordPress\Internal\Report;

/**
 * Ports `Generic.Functions.OpeningFunctionBraceKernighanRitchie` as WordPress-Core sets it: the
 * opening brace of a named function or method (closures are not checked) is on the line of the
 * declaration (`BraceOnNewLine`), one space after it (`SpaceBeforeBrace`). `ContentAfterBrace` is
 * off, as in WordPress-Core: `generic/multi-line-function-declaration` reports it.
 */
final class OpeningFunctionBraceKernighanRitchieRule implements Rule
{
    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/opening-function-brace-kernighan-ritchie',
            name: 'Opening function brace (Kernighan and Ritchie)',
            description: 'Reports the opening brace of a function that is not on the line of its declaration, or not one space after it.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        foreach ($tokens as $index => $token) {
            if ($token->code === 'T_FUNCTION' && $token->scopeOpener !== null) {
                KernighanRitchieBrace::check(
                    $this->report,
                    $context,
                    $tokens,
                    $index,
                    'Generic.Functions.OpeningFunctionBraceKernighanRitchie',
                    contentAfter: false,
                );
            }
        }
    }
}
