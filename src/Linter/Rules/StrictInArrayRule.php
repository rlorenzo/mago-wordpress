<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function strtolower;
use function trim;

/**
 * Ports `WordPress.PHP.StrictInArray`.
 */
final class StrictInArrayRule extends CallRule
{
    private const SNIFF = 'WordPress.PHP.StrictInArray';

    /**
     * Keyed by function name. `position` and `parameter` locate the
     * `$strict` argument; `alwaysNeeded` is false only for `array_keys()`,
     * which only needs it once a `$filter_value` argument is passed.
     *
     * @var array<string, array{position: int, alwaysNeeded: bool}>
     */
    private const TARGETS = [
        'in_array' => ['position' => 2, 'alwaysNeeded' => true],
        'array_search' => ['position' => 2, 'alwaysNeeded' => true],
        'array_keys' => ['position' => 2, 'alwaysNeeded' => false],
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/strict-in-array',
            name: 'Strict in_array()',
            description: 'Flags calls to in_array(), array_search() and array_keys() without true as the $strict argument.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['in_array', 'array_search', 'array_keys'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $target = self::TARGETS[$name];

        if (!$target['alwaysNeeded'] && $this->argument($context, $call, 1, 'filter_value') === null) {
            // array_keys() without a $filter_value never compares values, so $strict is unused.
            return;
        }

        $strict = $this->argument($context, $call, $target['position'], 'strict');
        if (
            $strict !== null
            && strtolower($context->file->getText(Values::unparenthesize($context->file, $strict))) === 'true'
        ) {
            return;
        }

        // WPCS gives a deliberate `false` its own code, so it can be ignored on its own.
        $code =
            $strict !== null && strtolower(trim($context->file->getText($strict))) === 'false'
                ? 'FoundNonStrictFalse'
                : 'MissingTrueStrict';
        Report::issue(
            $context,
            Issue::new(
                "Not using strict comparison for {$name}(); supply true for \$strict argument.",
                $context->node->span,
            )->withHelp('Pass true as the $strict argument so the comparison also checks type.'),
            [self::SNIFF . '.' . $code],
        );
    }
}
