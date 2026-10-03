<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Rlorenzo\MagoWordPress\Internal\WordPress\Levels;

use function array_slice;
use function explode;
use function fnmatch;
use function implode;

/**
 * One half of a rule split by level: WPCS reports some of a sniff's message codes as warnings
 * and the rest as errors, and a Mago rule has one level. The rule runs once per node; its
 * issues are cached and each half replays its share, routed by `Levels`: the `-warning`
 * companion takes the codes WPCS always reports as warnings, the main rule the rest.
 *
 * @internal
 */
final class SplitRule implements Rule
{
    private function __construct(
        private readonly Rule $rule,
        private readonly Report $report,
        private readonly ?RuleDefinition $companion,
    ) {}

    /**
     * @return array{Rule, Rule} the rule (minus its warning codes) and its `<code>-warning` companion
     */
    public static function pair(Rule $rule, Report $report, string $name, string $description): array
    {
        $definition = $rule->getDefinition();

        return [
            new self($rule, $report, null),
            new self(
                $rule,
                $report,
                new RuleDefinition(
                    code: $definition->code . '-warning',
                    name: $name,
                    description: $description,
                    defaultLevel: Level::Warning,
                    defaultEnabled: $definition->defaultEnabled,
                    targets: $definition->targets,
                ),
            ),
        ];
    }

    public function getDefinition(): RuleDefinition
    {
        return $this->companion ?? $this->rule->getDefinition();
    }

    public function lint(LintContext $context): void
    {
        $issues = FileCache::remember(
            $context->file,
            'split:' . $this->rule::class . ':' . $context->node->id,
            function () use ($context): array {
                $issues = [];
                $this->report->recording(
                    static function (Issue $issue, array $codes) use (&$issues): void {
                        $issues[] = [$issue, self::isWarning($codes)];
                    },
                    fn() => $this->rule->lint($context),
                );

                return $issues;
            },
        );

        foreach ($issues as [$issue, $warning]) {
            if ($warning === ($this->companion !== null)) {
                $context->report($issue);
            }
        }
    }

    /**
     * Whether WPCS always reports the first of $codes that `Levels` lists as a warning.
     *
     * @param list<string> $codes
     */
    private static function isWarning(array $codes): bool
    {
        foreach ($codes as $code) {
            $parts = explode('.', $code);
            $sniff = implode('.', array_slice($parts, offset: 0, length: 3));
            $message = implode('.', array_slice($parts, offset: 3));
            foreach (Levels::SNIFFS[$sniff] ?? [] as $pattern => $level) {
                if (fnmatch($pattern, $message)) {
                    return $level === 'warning';
                }
            }
        }

        return false;
    }
}
