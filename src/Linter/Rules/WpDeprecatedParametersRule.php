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
use function implode;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;
use function version_compare;

/**
 * Ports `WordPress.WP.DeprecatedParameters`.
 *
 * The sniff reports every usage that still passes a deprecated parameter a
 * value other than its current default, as an error or a warning depending
 * on whether the deprecation is already behind the project's minimum
 * supported WordPress version. A Mago issue has one level per rule, not per
 * report, so this rule instead mirrors `WpDeprecatedFunctionsRule`'s gate:
 * it reports (at a single `Warning` level) only usages whose deprecation is
 * at or before the configured `minimum-wp-version`, and stays silent
 * otherwise.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class WpDeprecatedParametersRule extends CallRule
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/wp-deprecated-parameters',
            name: 'WordPress deprecated parameters',
            description: 'Reports calls to WordPress core functions that pass a value other than the current '
            . 'default for a parameter that has been deprecated. WordPress ignores the argument once a parameter '
            . 'is deprecated, so passing anything else no longer has any effect.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return array_keys(Lists::DEPRECATED_PARAMETERS);
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        foreach (Lists::DEPRECATED_PARAMETERS[$name] as $position => $parameter) {
            $this->checkParameter($context, $call, $name, $position, $parameter);
        }
    }

    /**
     * @param array{name: string|list<string>, value: mixed, version: string} $parameter
     */
    private function checkParameter(
        LintContext $context,
        CallExpression $call,
        string $name,
        int $position,
        array $parameter,
    ): void {
        $argument = $this->findArgument($context, $call, $position, $parameter['name']);
        if ($argument === null || !$this->isReportable($parameter['version'])) {
            return;
        }

        if (self::valueMatchesDefault($context->file, $argument, $parameter['value'])) {
            return;
        }

        $help = $parameter['value'] === null
            ? 'Do not pass this argument.'
            : 'Pass '
            . self::describeDefault($parameter['value'])
            . ' (the current default) instead, or do not pass this argument.';

        $paramName = self::namesLabel($parameter['name']);

        $context->report(Issue::new(
            "The \"{$paramName}\" parameter (position #{$position}) of `{$name}()` has been deprecated "
            . "since WordPress {$parameter['version']}.",
            $argument->span,
        )->withNote('Deprecated parameters are ignored; passing anything but their default has no effect.')->withHelp(
            $help,
        ));
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
     * @param string|list<string> $names
     */
    private static function namesLabel(string|array $names): string
    {
        return is_string($names) ? $names : implode('/', $names);
    }

    /**
     * Whether a call argument's value is the deprecated parameter's current
     * default, following the same three shapes the sniff recognizes:
     * `true`/`false`/`null` keywords, an empty array literal, and a string.
     */
    private static function valueMatchesDefault(SourceFile $file, Node $node, mixed $default): bool
    {
        $node = Values::unwrap($file, $node);

        return match ($node->kind) {
            NodeKind::Keyword => match (strtolower($file->getText($node))) {
                'true' => $default === true,
                'false' => $default === false,
                'null' => $default === null,
                default => false,
            },
            NodeKind::LiteralString => is_string($default) && Values::literalString($file, $node) === $default,
            NodeKind::Array, NodeKind::LegacyArray => $default === [] && self::isEmptyArrayLiteral($file, $node),
            default => false,
        };
    }

    private static function isEmptyArrayLiteral(SourceFile $file, Node $node): bool
    {
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === NodeKind::ArrayElement) {
                return false;
            }
        }

        return true;
    }

    private static function describeDefault(mixed $value): string
    {
        if ($value === '') {
            return 'an empty string';
        }

        if (is_string($value)) {
            return "`'{$value}'`";
        }

        if ($value === []) {
            return 'an empty array';
        }

        return $value === true ? '`true`' : '`false`';
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
