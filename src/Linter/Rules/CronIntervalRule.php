<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Settings;

use function count;
use function in_array;
use function is_int;
use function ltrim;
use function trim;

/**
 * Ports `WordPress.WP.CronInterval`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class CronIntervalRule implements Rule
{
    private const ADD_FILTER = 'add_filter';

    private const HOOK_NAME = 'cron_schedules';

    private const INTERVAL_KEY = 'interval';

    private const STOP_KINDS = [NodeKind::Function, NodeKind::Method, NodeKind::Closure, NodeKind::ArrowFunction];

    private const CONSTANTS = ['MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'DAY_IN_SECONDS' => 86_400];

    private readonly FileGate $gate;

    /** @var array<string, true> */
    private readonly array $wanted;

    private readonly int $minInterval;

    public function __construct(Settings $settings)
    {
        $this->minInterval = $settings->minCronInterval;
        $this->gate = new FileGate('/cron_schedules/i');
        $this->wanted = Calls::normalizeAll([self::ADD_FILTER]);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/cron-interval',
            name: 'Cron interval',
            description: "Flags custom cron schedules registered via add_filter('cron_schedules', ...) whose interval "
            . 'is shorter than the configured minimum (default 900 seconds, min-cron-interval). Cron schedules that run too often can severely degrade site '
            . 'performance. Only inline callbacks (closures and arrow functions) are inspected, and only interval values '
            . 'that are simple constant integer expressions: integer literals, */+ arithmetic on them, and the WordPress '
            . 'time constants MINUTE_IN_SECONDS, HOUR_IN_SECONDS, and DAY_IN_SECONDS. Callbacks referenced by name and '
            . 'dynamic interval values are not resolved.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->gate->passes($context->file)) {
            return;
        }

        $file = $context->file;
        if (Calls::matchWanted($file, $context->node, $this->wanted) === null) {
            return;
        }

        $call = CallExpression::fromNode($file, $context->node);
        $hook = Calls::argument($file, $call, index: 0, parameter: 'hook_name');
        if ($hook === null) {
            return;
        }

        if (Values::literalString($file, $hook) !== self::HOOK_NAME) {
            return;
        }

        $callback = Calls::argument($file, $call, index: 1, parameter: 'callback');
        if ($callback === null) {
            return;
        }

        $callback = Values::unparenthesize($file, $callback);
        if ($callback->kind === NodeKind::Closure) {
            $children = $file->getChildren($callback);
            $body = $children[count($children) - 1] ?? null;
            if ($body !== null && $body->kind === NodeKind::Block) {
                foreach ($file->getChildren($body) as $statement) {
                    $this->scanForIntervals($context, $statement);
                }
            }

            return;
        }

        if ($callback->kind === NodeKind::ArrowFunction) {
            $children = $file->getChildren($callback);
            $expression = $children[count($children) - 1] ?? null;
            if ($expression !== null) {
                $this->scanForIntervals($context, $expression);
            }
        }

        // A callback referenced by name (string, first-class callable, method
        // reference) is not resolved; the Rust rule leaves those alone too.
    }

    /**
     * Recursively looks for `'interval' => ...` entries, without descending
     * into a nested function-like scope.
     */
    private function scanForIntervals(LintContext $context, Node $node): void
    {
        if (in_array($node->kind, self::STOP_KINDS, strict: true)) {
            return;
        }

        if ($node->kind === NodeKind::KeyValueArrayElement) {
            $this->checkIntervalElement($context, $node);
        }

        foreach ($context->file->getChildren($node) as $child) {
            $this->scanForIntervals($context, $child);
        }
    }

    private function checkIntervalElement(LintContext $context, Node $element): void
    {
        $file = $context->file;
        $children = $file->getChildren($element);
        $key = $children[0] ?? null;
        $value = $children[1] ?? null;
        if ($key === null || $value === null) {
            return;
        }

        $key = Values::unparenthesize($file, $key);
        if (Values::literalString($file, $key) !== self::INTERVAL_KEY) {
            return;
        }

        $interval = $this->evaluateConstantInteger($file, $value);
        if ($interval === null || $interval >= $this->minInterval) {
            return;
        }

        $context->report(Issue::new(
            "Cron schedule interval of {$interval} seconds is below the minimum of {$this->minInterval} seconds.",
            $value->span,
            "This interval evaluates to {$interval} seconds",
        )->withNote('Cron schedules that run too frequently can severely degrade site performance.')->withHelp(
            "Use a longer interval ({$this->minInterval} seconds or more).",
        ));
    }

    /**
     * Evaluates simple constant integer expressions: integer literals, `*`/`+`
     * arithmetic on them, and the WordPress time constants `MINUTE_IN_SECONDS`,
     * `HOUR_IN_SECONDS`, and `DAY_IN_SECONDS`. Anything else yields NULL.
     */
    private function evaluateConstantInteger(SourceFile $file, Node $node): ?int
    {
        $node = Values::unparenthesize($file, $node);

        if ($node->kind === NodeKind::LiteralInteger) {
            return Values::literalInteger($file, $node);
        }

        if ($node->kind === NodeKind::ConstantAccess) {
            $name = ltrim($file->getText($node), characters: '\\');

            return self::CONSTANTS[$name] ?? null;
        }

        if ($node->kind === NodeKind::Binary) {
            $children = $file->getChildren($node);
            $lhs = $children[0] ?? null;
            $operator = $children[1] ?? null;
            $rhs = $children[2] ?? null;
            if ($lhs === null || $operator === null || $rhs === null) {
                return null;
            }

            $left = $this->evaluateConstantInteger($file, $lhs);
            $right = $this->evaluateConstantInteger($file, $rhs);
            if ($left === null || $right === null) {
                return null;
            }

            $result = match (trim($file->getText($operator))) {
                '*' => $left * $right,
                '+' => $left + $right,
                default => null,
            };

            return is_int($result) ? $result : null;
        }

        return null;
    }
}
