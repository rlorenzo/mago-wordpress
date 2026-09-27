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
use Rlorenzo\MagoWordPress\Internal\WordPress\WpVersion;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function implode;
use function is_string;
use function strtolower;

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
        if (!WpVersion::reached($this->settings->normalizedMinimumWpVersion(), $parameter['version'])) {
            return;
        }

        $argument = $this->argument($context, $call, $position - 1, $parameter['name']);
        if ($argument === null || self::valueMatchesDefault($context->file, $argument, $parameter['value'])) {
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
}
