<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function preg_match;
use function strtolower;
use function trim;
use function version_compare;

/**
 * Ports `WordPress.WP.DeprecatedParameterValues`.
 *
 * The sniff reports every usage that passes a deprecated value for a still-
 * valid parameter, as an error or a warning depending on whether the
 * deprecation is already behind the project's minimum supported WordPress
 * version. A Mago issue has one level per rule, not per report, so this
 * rule instead mirrors `WpDeprecatedFunctionsRule`'s gate: it reports (at a
 * single `Warning` level) only usages whose deprecation is at or before the
 * configured `minimum-wp-version`, and stays silent otherwise.
 *
 * Only a literal string or a `true`/`false` keyword argument can match a
 * deprecated value; a dynamic argument (a variable, constant, or call) is
 * left unflagged, exactly as the sniff leaves it undetermined.
 */
final class WpDeprecatedParameterValuesRule extends CallRule
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/wp-deprecated-parameter-values',
            name: 'WordPress deprecated parameter values',
            description: 'Reports calls to WordPress core functions that pass a specific value which has been '
            . 'deprecated for one of the parameters, such as `bloginfo(\'home\')` or `add_option(\'blacklist_keys\', ...)`.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return array_keys(Lists::DEPRECATED_PARAMETER_VALUES);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        foreach (Lists::DEPRECATED_PARAMETER_VALUES[$name] as $position => $parameter) {
            $this->checkParameter($context, $call, $name, $position, $parameter);
        }
    }

    /**
     * @param array{name: string|list<string>, values: array<string, array{alt: string, version: string}>} $parameter
     */
    private function checkParameter(
        LintContext $context,
        CallExpression $call,
        string $name,
        int $position,
        array $parameter,
    ): void {
        $argument = $this->findArgument($context, $call, $position, $parameter['name']);
        if ($argument === null) {
            return;
        }

        $value = self::rawValueText($context->file, $argument);
        if ($value === null) {
            return;
        }

        $deprecation = $parameter['values'][$value] ?? null;
        if ($deprecation === null || !$this->isReportable($deprecation['version'])) {
            return;
        }

        $issue = Issue::new(
            "The parameter value \"{$value}\" of `{$name}()` has been deprecated since WordPress "
            . "{$deprecation['version']}.",
            $argument->span,
        )->withNote('Deprecated parameter values may stop working in a future release.');

        if ($deprecation['alt'] !== '') {
            $issue = $issue->withHelp("Use {$deprecation['alt']} instead.");
        }

        $context->report($issue);
    }

    /**
     * Finds the argument at $position, trying every name a renamed parameter
     * may have been passed under (PHP 8.0 renamed some WP core parameters).
     *
     * @param string|list<string> $names
     */
    private function findArgument(LintContext $context, CallExpression $call, int $position, string|array $names): ?Node
    {
        foreach ((array) $names as $name) {
            $argument = $this->argument($context, $call, $position - 1, $name);
            if ($argument !== null) {
                return $argument;
            }
        }

        return null;
    }

    /**
     * Returns the argument's value as plain text, the way the sniff reads it
     * off the raw token: a string literal decoded, or a `true`/`false`
     * keyword lowercased. Any other shape yields NULL, since it cannot be
     * one of the deprecated value strings.
     */
    private static function rawValueText(SourceFile $file, Node $node): ?string
    {
        $node = Values::unwrap($file, $node);

        return match ($node->kind) {
            NodeKind::LiteralString => Values::literalString($file, $node),
            NodeKind::Keyword => strtolower($file->getText($node)),
            default => null,
        };
    }

    /**
     * Copied from `WpDeprecatedFunctionsRule::isReportable()`: this rule owns no
     * shared helper file, so the gate is duplicated here rather than extracted.
     *
     * Whether a deprecation at $deprecatedSince should be reported under the
     * project's configured minimum WordPress version. An empty or unparsable
     * `minimum-wp-version` reports everything, matching the ported sniff.
     * `version_compare()` treats a shorter version as older than the same
     * version with a trailing `.0` (`"4.5" < "4.5.0"`), so the minimum is
     * padded to three components before it is compared against the table's
     * `major.minor.patch` entries.
     */
    private function isReportable(string $deprecatedSince): bool
    {
        $minimum = trim($this->settings->minimumWpVersion);
        $parts = [];
        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $minimum, $parts) !== 1) {
            return true;
        }

        $normalizedMinimum = ($parts[1] ?? '0') . '.' . ($parts[2] ?? '0') . '.' . ($parts[3] ?? '0');

        return version_compare($deprecatedSince, $normalizedMinimum, operator: '<=');
    }
}
