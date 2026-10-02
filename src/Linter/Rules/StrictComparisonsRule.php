<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;

/**
 * Ports `Universal.Operators.StrictComparisons`: a loose `==`, `!=` or `<>` comparison.
 * WordPress-Core sets the sniff to a warning and marks it `phpcs-only`, so phpcbf never
 * rewrites the operator; no fix is offered either, as `===` changes what the code does.
 */
final class StrictComparisonsRule implements Rule
{
    private const SNIFF = 'Universal.Operators.StrictComparisons';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/strict-comparisons',
            name: 'Strict comparisons',
            description: 'Reports a loose `==`, `!=` or `<>` comparison; use `===` or `!==`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::BinaryOperator],
        );
    }

    public function lint(LintContext $context): void
    {
        $operator = $context->getText();
        [$expected, $code] = match ($operator) {
            '==' => ['===', 'LooseEqual'],
            '!=', '<>' => ['!==', 'LooseNotEqual'],
            default => [null, null],
        };
        if ($expected === null) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                "Loose comparisons are not allowed. Expected: \"{$expected}\"; Found: \"{$operator}\"",
                $context->node->span,
                'loose comparison',
            )->withHelp("Use `{$expected}` once both sides are known to have the same type."),
            [self::SNIFF . '.' . $code],
        );
    }
}
