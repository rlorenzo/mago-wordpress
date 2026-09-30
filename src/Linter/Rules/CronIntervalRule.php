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
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Settings;

use function bindec;
use function count;
use function hexdec;
use function in_array;
use function is_float;
use function is_int;
use function is_numeric;
use function ltrim;
use function octdec;
use function preg_match;
use function round;
use function str_replace;
use function strcasecmp;
use function strrchr;
use function substr;
use function trim;

/**
 * Ports `WordPress.WP.CronInterval`.
 *
 * Like the sniff, a callback referenced by name resolves to the first function
 * or method of that name (case-insensitively, namespace ignored) declared in
 * the same file, and the first `'interval'` string in the callback body decides.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class CronIntervalRule implements Rule
{
    private const SNIFF = 'WordPress.WP.CronInterval';

    private const ADD_FILTER = 'add_filter';

    private const HOOK_NAME = 'cron_schedules';

    private const INTERVAL_KEY = 'interval';

    private const ARRAY_KINDS = [NodeKind::Array, NodeKind::LegacyArray];

    private const PARTIAL_KINDS = [
        NodeKind::FunctionPartialApplication,
        NodeKind::MethodPartialApplication,
        NodeKind::StaticMethodPartialApplication,
    ];

    private const CONSTANTS = [
        'MINUTE_IN_SECONDS' => 60,
        'HOUR_IN_SECONDS' => 3600,
        'DAY_IN_SECONDS' => 86_400,
        'WEEK_IN_SECONDS' => 604_800,
        'MONTH_IN_SECONDS' => 2_592_000,
        'YEAR_IN_SECONDS' => 31_536_000,
    ];

    private readonly FileGate $gate;

    /** @var array<string, true> */
    private readonly array $wanted;

    private readonly int $minInterval;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->minInterval = $settings->minCronInterval;
        $this->gate = new FileGate(['/cron_schedules/i']);
        $this->wanted = Calls::normalizeAll([self::ADD_FILTER]);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/cron-interval',
            name: 'Cron interval',
            description: "Flags custom cron schedules registered via add_filter('cron_schedules', ...) whose interval "
            . 'is shorter than the configured minimum (default 900 seconds, min-cron-interval). Cron schedules that run too often can severely degrade site '
            . 'performance. The callback may be a closure, an arrow function, or a function or method named by a string, '
            . 'an array callable, or a first-class callable and declared in the same file. Interval values are evaluated '
            . 'when they are constant integer expressions: integer literals, arithmetic on them, and the WordPress time '
            . 'constants (MINUTE_IN_SECONDS through YEAR_IN_SECONDS). When the callback or its interval cannot be '
            . 'determined, a separate warning says the schedule change was detected.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if (!$this->gate->passes($file)) {
            return;
        }

        // Named callbacks resolve to declarations anywhere in the file, which
        // only a `Program` snapshot is guaranteed to hold.
        foreach (NodeIndex::ofKind($file, $context->node, NodeKind::FunctionCall) as $node) {
            $this->checkCall($context, $node);
        }
    }

    private function checkCall(LintContext $context, Node $node): void
    {
        $file = $context->file;
        if (Calls::matchWanted($file, $node, $this->wanted) === null) {
            return;
        }

        $call = CallExpression::fromNode($file, $node);
        $hook = Calls::argument($file, $call, index: 0, parameter: 'hook_name');
        if ($hook === null) {
            return;
        }

        $hook = Values::unparenthesize($file, $hook);
        if (Values::literalString($file, $hook) !== self::HOOK_NAME) {
            return;
        }

        $callback = Calls::argument($file, $call, index: 1, parameter: 'callback');
        if ($callback === null) {
            return;
        }

        $body = $this->resolveBody($context, $callback);
        if ($body === null) {
            $this->confused($context, $hook);

            return;
        }

        $value = $this->findIntervalValue($file, $body);
        if ($value === false) {
            return;
        }

        $interval = $value === null ? null : $this->evaluate($file, $value);
        if ($value === null || $interval === null) {
            $this->confused($context, $hook);

            return;
        }

        if ($interval >= $this->minInterval) {
            return;
        }

        $minutes = round($this->minInterval / 60, precision: 1);
        $this->report->issue(
            $context,
            Issue::new(
                "Scheduling crons at {$interval} sec ( less than {$minutes} minutes ) is discouraged.",
                $hook->span,
            )
                ->withSecondaryAnnotation($value->span, "This interval evaluates to {$interval} seconds")
                ->withNote('Cron schedules that run too frequently can severely degrade site performance.')
                ->withHelp("Use a longer interval ({$this->minInterval} seconds or more)."),
            [self::SNIFF . '.CronSchedulesInterval'],
        );
    }

    private function confused(LintContext $context, Node $hook): void
    {
        $this->report->issue(
            $context,
            Issue::new(
                'Detected changing of cron_schedules, but could not detect the interval value.',
                $hook->span,
            )->withHelp('Make sure the schedule interval is at least ' . $this->minInterval . ' seconds.'),
            [self::SNIFF . '.ChangeDetected'],
        );
    }

    /**
     * The body to search for the interval: a closure or arrow function in the
     * callback, or the declaration it names. NULL when it cannot be resolved.
     *
     * Mirrors the sniff's token search: an array callable narrows to its second
     * element, then the first string, closure, arrow function, or first-class
     * callable found in source order decides.
     */
    private function resolveBody(LintContext $context, Node $callback): ?Node
    {
        $file = $context->file;
        $array = $this->leftmostArray($file, $callback);
        if ($array !== null) {
            $callback = $file->getChildren($array)[1] ?? null;
            if ($callback === null) {
                return null;
            }
        }

        foreach ([$callback, ...$file->getDescendants($callback)] as $node) {
            if ($node->kind === NodeKind::Closure || $node->kind === NodeKind::ArrowFunction) {
                return $this->lastChild($file, $node);
            }

            if ($node->kind === NodeKind::LiteralString) {
                return $this->findDeclaration($context, (string) Values::literalString($file, $node));
            }

            if (in_array($node->kind, self::PARTIAL_KINDS, strict: true)) {
                $name = $this->partialName($file, $node);

                return $name === null ? null : $this->findDeclaration($context, $name);
            }
        }

        return null;
    }

    /**
     * The array literal the callback starts with, as in `array( $this, 'm' )`
     * or `[ $this, 'm' ](...)`.
     */
    private function leftmostArray(SourceFile $file, Node $node): ?Node
    {
        while (!in_array($node->kind, self::ARRAY_KINDS, strict: true)) {
            $first = $file->getChildren($node)[0] ?? null;
            if ($first === null || $first->span->start !== $node->span->start) {
                return null;
            }

            $node = $first;
        }

        return $node;
    }

    /**
     * The function or method name of a first-class callable. Like the sniff,
     * only the last segment of a qualified name counts.
     */
    private function partialName(SourceFile $file, Node $node): ?string
    {
        $children = $file->getChildren($node);
        if ($node->kind === NodeKind::FunctionPartialApplication) {
            $callee = Values::unparenthesize($file, $children[0] ?? $node);
            $literal = Values::literalString($file, $callee);
            if ($literal !== null) {
                return $literal;
            }

            $callee = $callee->kind === NodeKind::Identifier ? $file->getChildren($callee)[0] ?? $callee : $callee;
            if (!in_array(
                $callee->kind,
                [NodeKind::LocalIdentifier, NodeKind::QualifiedIdentifier, NodeKind::FullyQualifiedIdentifier],
                strict: true,
            )) {
                return null;
            }

            $name = $file->getText($callee);
            $last = strrchr($name, needle: '\\');

            return $last === false ? $name : substr($last, offset: 1);
        }

        $selector = $children[1] ?? null;
        $identifier = $selector === null ? null : $file->getChildren($selector)[0] ?? null;

        return $identifier !== null && $identifier->kind === NodeKind::LocalIdentifier
            ? $file->getText($identifier)
            : null;
    }

    /**
     * The body of the first function or method declared in this file with the
     * name, case-insensitively.
     */
    private function findDeclaration(LintContext $context, string $name): ?Node
    {
        $file = $context->file;
        $program = $file->getNode(0);
        foreach (NodeIndex::ofKinds($file, $program, [NodeKind::Function, NodeKind::Method]) as $declaration) {
            foreach ($file->getChildren($declaration) as $child) {
                if ($child->kind !== NodeKind::LocalIdentifier) {
                    continue;
                }

                if (strcasecmp($file->getText($child), $name) === 0) {
                    return $this->lastChild($file, $declaration);
                }

                break;
            }
        }

        return null;
    }

    private function lastChild(SourceFile $file, Node $node): ?Node
    {
        $children = $file->getChildren($node);

        return $children[count($children) - 1] ?? null;
    }

    /**
     * The value of the first `'interval'` string in the body when that string
     * is an array key, NULL when it is not, and FALSE when there is none.
     */
    private function findIntervalValue(SourceFile $file, Node $body): Node|false|null
    {
        foreach ($file->getDescendants($body, NodeKind::LiteralString) as $string) {
            if (Values::literalString($file, $string) !== self::INTERVAL_KEY) {
                continue;
            }

            $key = $string;
            $parent = $file->getParent($key);
            while (
                $parent !== null
                && ($parent->kind === NodeKind::Literal || $parent->kind === NodeKind::Expression)
            ) {
                $key = $parent;
                $parent = $file->getParent($key);
            }

            if ($parent === null || $parent->kind !== NodeKind::KeyValueArrayElement) {
                return null;
            }

            $children = $file->getChildren($parent);

            return $children[0] === $key ? $children[1] ?? null : null;
        }

        return false;
    }

    /**
     * Evaluates constant numeric expressions: integer literals in any base,
     * `+ - * /` arithmetic on them, and the WordPress time constants. Anything
     * else yields NULL.
     */
    private function evaluate(SourceFile $file, Node $node): int|float|null
    {
        $node = Values::unparenthesize($file, $node);

        if ($node->kind === NodeKind::LiteralInteger) {
            $text = str_replace(search: '_', replace: '', subject: $file->getText($node));

            return match (true) {
                preg_match('/^0[xX][0-9a-fA-F]+$/', $text) === 1 => hexdec($text),
                preg_match('/^0[bB][01]+$/', $text) === 1 => bindec($text),
                preg_match('/^0[oO]?[0-7]+$/', $text) === 1 => octdec(ltrim($text, characters: '0oO')),
                // A decimal literal past PHP_INT_MAX is a float, as at run time.
                default => Values::literalInteger($file, $node) ?? (is_numeric($text) ? (float) $text : null),
            };
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

            $left = $this->evaluate($file, $lhs);
            $right = $this->evaluate($file, $rhs);
            if ($left === null || $right === null) {
                return null;
            }

            $result = match (trim($file->getText($operator))) {
                '*' => $left * $right,
                '+' => $left + $right,
                '-' => $left - $right,
                '/' => (float) $right === 0.0 ? null : $left / $right,
                default => null,
            };

            return is_int($result) || is_float($result) ? $result : null;
        }

        return null;
    }
}
