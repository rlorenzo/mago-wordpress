<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;

use function array_key_exists;
use function array_map;
use function is_string;
use function strtolower;
use function trim;

/**
 * A rule re-levelled by the `levels` setting: Mago reads an extension rule's level only from
 * its `RuleDefinition` (`[linter.rules]` rejects `wordpress/*` keys), so the definition is
 * rebuilt with the configured level.
 *
 * @internal
 */
final class LeveledRule implements Rule
{
    private const LEVELS = [
        'error' => Level::Error,
        'warning' => Level::Warning,
        'note' => Level::Note,
        'help' => Level::Help,
    ];

    private readonly RuleDefinition $definition;

    private function __construct(
        private readonly Rule $rule,
        Level $level,
    ) {
        $definition = $rule->getDefinition();
        $this->definition = new RuleDefinition(
            code: $definition->code,
            name: $definition->name,
            description: $definition->description,
            defaultLevel: $level,
            defaultEnabled: $definition->defaultEnabled,
            targets: $definition->targets,
        );
    }

    /**
     * Applies `levels` (rule code => level name) to the rules.
     *
     * @param list<Rule> $rules
     * @param array<array-key, mixed> $levels
     *
     * @return array{list<Rule>, list<string>} the rules, and a message per entry that names
     *         no registered rule or no level
     */
    public static function apply(array $rules, array $levels): array
    {
        $problems = [];
        $codes = [];
        foreach ($rules as $rule) {
            $codes[$rule->getDefinition()->code] = true;
        }

        $given = $levels;
        $levels = [];
        foreach ($given as $code => $level) {
            $level = is_string($level) ? strtolower(trim($level)) : '';
            $levels[$code] = $level;
            if (!array_key_exists($code, $codes)) {
                $problems[] = "levels: unknown rule `{$code}`; use a rule code such as `wordpress/capital-p-dangit`.";
            } elseif (!array_key_exists($level, self::LEVELS)) {
                $problems[] = "levels: `{$code}` has level `{$level}`; use error, warning, note or help.";
            }
        }

        $rules = array_map(static function (Rule $rule) use ($levels): Rule {
            $level = self::LEVELS[$levels[$rule->getDefinition()->code] ?? ''] ?? null;

            return $level === null ? $rule : new self($rule, $level);
        }, $rules);

        return [$rules, $problems];
    }

    public function getDefinition(): RuleDefinition
    {
        return $this->definition;
    }

    public function lint(LintContext $context): void
    {
        $this->rule->lint($context);
    }
}
