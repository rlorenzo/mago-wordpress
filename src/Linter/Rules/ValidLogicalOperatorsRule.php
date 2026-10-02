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

use function strtolower;

/**
 * Ports `Squiz.Operators.ValidLogicalOperators`: the `and` and `or` keywords. No fix, as in
 * phpcs: `&&` and `||` bind tighter, so swapping them can change what the code does.
 */
final class ValidLogicalOperatorsRule implements Rule
{
    private const CODE = 'Squiz.Operators.ValidLogicalOperators.NotAllowed';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/valid-logical-operators',
            name: 'Valid logical operators',
            description: 'Reports the `and` and `or` operators; use `&&` and `||`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::BinaryOperator],
        );
    }

    public function lint(LintContext $context): void
    {
        $operator = strtolower($context->getText());
        $replacement = match ($operator) {
            'and' => '&&',
            'or' => '||',
            default => null,
        };
        if ($replacement === null) {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                "Logical operator \"{$operator}\" is prohibited; use \"{$replacement}\" instead",
                $context->node->span,
                'word operator',
            )->withHelp("Use `{$replacement}`, adding parentheses where its higher precedence changes the grouping."),
            [self::CODE],
        );
    }
}
