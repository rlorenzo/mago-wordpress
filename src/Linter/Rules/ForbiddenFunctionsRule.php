<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function ltrim;

/**
 * Ports `Generic.PHP.ForbiddenFunctions` with the sniff's default list, which WordPress-Extra
 * uses unchanged: `sizeof()` (use `count()`) and `delete()` (use `unset()`).
 */
final class ForbiddenFunctionsRule extends CallRule
{
    private const CODE = 'Generic.PHP.ForbiddenFunctions.FoundWithAlternative';

    private const ALTERNATIVES = ['sizeof' => 'count', 'delete' => 'unset'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/forbidden-functions',
            name: 'Forbidden functions',
            description: 'Reports `sizeof()` and `delete()`, aliases of `count()` and `unset()`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['sizeof', 'delete'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $written = ltrim((string) Calls::name($context->file, $context->node), characters: '\\');
        $alternative = self::ALTERNATIVES[$name];
        $this->report->issue(
            $context,
            Issue::new(
                "The use of function {$written}() is forbidden; use {$alternative}() instead",
                $call->callee->span,
                'forbidden function',
            )->withHelp("Use `{$alternative}()`."),
            [self::CODE],
        );
    }
}
