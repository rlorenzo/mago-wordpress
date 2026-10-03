<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\PreparedQuery;
use Rlorenzo\MagoWordPress\Internal\WordPress\WpVersion;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

/**
 * Ports `WordPress.DB.PreparedSQLPlaceholders`, except its
 * `UnquotedComplexPlaceholder` code.
 */
final class PreparedSqlPlaceholdersRule extends CallRule
{
    private const SNIFF = 'WordPress.DB.PreparedSQLPlaceholders';

    /**
     * Whether the configured minimum WordPress version supports `%i`,
     * which arrived in 6.2. WPCS: an unparsable minimum falls back to its
     * current default, which does.
     */
    private readonly bool $identifierSupported;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->identifierSupported = WpVersion::reached($settings->normalizedMinimumWpVersion(), '6.2.0');
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prepared-sql-placeholders',
            name: 'Prepared SQL placeholders',
            description: 'Validates placeholder usage in $wpdb->prepare() calls: quoted placeholders, placeholders other than %s, %d, %f, %F and %i, unescaped % literals, SQL wildcards in LIKE operands, dynamic IN () placeholder lists, a placeholder count that differs from the replacement arguments, and prepare() calls with no placeholders at all.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::MethodCall, NodeKind::NullSafeMethodCall, NodeKind::StaticMethodCall],
        );
    }

    protected function names(): array
    {
        return ['prepare'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        foreach (PreparedQuery::analyze($context->file, $call, $this->identifierSupported) as [$code, $node, $found]) {
            if ($code === 'UnquotedComplexPlaceholder') {
                continue;
            }

            [$message, $annotation, $help] = self::describe($code, $found);
            $this->report->issue(
                $context,
                Issue::new($message, $node instanceof Span ? $node : $node->span, $annotation)->withHelp($help),
                [self::SNIFF . '.' . $code],
            );
        }
    }

    /**
     * @return array{string, string, string} The message, annotation and help.
     */
    private static function describe(string $code, string $found): array
    {
        return match ($code) {
            'QuotedSimplePlaceholder' => [
                "Simple placeholders should not be quoted in the query string in `\$wpdb->prepare()`. Found: {$found}",
                'Quoted simple placeholder found in this SQL query',
                'Remove the quotes around the placeholder; `$wpdb->prepare()` quotes `%s`, `%d`, `%f` and `%F` values itself.',
            ],
            'QuotedIdentifierPlaceholder' => [
                "Placeholders used for identifiers (`%i`) in `\$wpdb->prepare()` are always quoted automagically. Found: {$found}",
                'Quoted identifier placeholder found in this SQL query',
                'Remove the quotes or backticks around the identifier placeholder.',
            ],
            'UnsupportedIdentifierPlaceholder' => [
                "The `%i` modifier is only supported in WP 6.2 or higher. Found: {$found}",
                'Identifier placeholder found in this SQL query',
                'Raise `minimum-wp-version` to 6.2 or higher, or validate the identifier against an allowlist and interpolate it.',
            ],
            'UnsupportedPlaceholder' => [
                "Unsupported placeholder used in `\$wpdb->prepare()`. Found: {$found}",
                'Unsupported placeholder found in this SQL query',
                'Use `%s`, `%d`, `%f`, `%F` or `%i` (WP >= 6.2). Use `%%` for a literal percent sign.',
            ],
            'UnescapedLiteral' => [
                'Found unescaped literal `%` character in `$wpdb->prepare()` query',
                'Unescaped `%` found in this SQL query',
                'Use `%%` for a literal percent sign.',
            ],
            'LikeWithoutWildcards' => [
                "Unless you are using SQL wildcards, using LIKE is inefficient. Use a straight compare instead. Found: {$found}",
                'LIKE without wildcards found in this SQL query',
                'Use `=` instead of `LIKE`.',
            ],
            'LikeWildcardsInQuery' => [
                "SQL wildcards for a LIKE query should be passed in through a replacement parameter. Found: {$found}",
                'SQL wildcards found in this LIKE operand',
                'Use `LIKE %s` and pass the pattern, wildcards included, as a replacement.',
            ],
            'LikeWildcardsInQueryWithPlaceholder' => [
                "SQL wildcards for a LIKE query should be passed in through a replacement parameter and the variable part should be escaped using `esc_like()`. Found: {$found}",
                'SQL wildcards found around a placeholder in this LIKE operand',
                "Use `LIKE %s` and pass `'%' . \$wpdb->esc_like(\$value) . '%'` as the replacement.",
            ],
            'QuotedDynamicPlaceholderGeneration' => [
                'Dynamic placeholder generation should not have surrounding quotes',
                'Quote before the generated placeholder list',
                'Remove the quotes around the `implode()` of placeholders in the `IN ()` clause.',
            ],
            'IdentifierWithinIN' => [
                'The `%i` placeholder cannot be used within SQL `IN()` clauses',
                'Identifier placeholder generated for an IN () list',
                'Use `%s`, `%d`, `%f` or `%F` for the values of an `IN ()` list.',
            ],
            'UnnecessaryPrepare' => [
                "It is not necessary to prepare a query which doesn't use variable replacement",
                'This `prepare()` call has no placeholders to replace',
                'Pass the query directly to the query method (e.g. `$wpdb->query()`), or add placeholders for the dynamic values.',
            ],
            'UnfinishedPrepare' => [
                'Replacement variables found, but no valid placeholders found in the query',
                'This `prepare()` call has replacements but no placeholders',
                'Add a placeholder for each replacement argument.',
            ],
            'MissingReplacements' => [
                "Placeholders found in the query passed to `\$wpdb->prepare()`, but no replacements found. Expected {$found} replacement(s)",
                'This `prepare()` call has no replacement arguments',
                'Pass one replacement argument per placeholder.',
            ],
            default => [
                "Incorrect number of replacements passed to `\$wpdb->prepare()`. Found/expected: {$found}",
                'Replacement count differs from the placeholder count',
                'Pass one replacement argument per placeholder (`%%` is a literal percent sign, not a placeholder).',
            ],
        };
    }
}
