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
use Rlorenzo\MagoWordPress\Internal\WordPress\PreparedQuery;
use Rlorenzo\MagoWordPress\Linter\CallRule;

/**
 * Ports `WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder`.
 */
final class PreparedSqlUnquotedComplexPlaceholderRule extends CallRule
{
    private const SNIFF = 'WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prepared-sql-unquoted-complex-placeholder',
            name: 'Prepared SQL unquoted complex placeholder',
            description: 'Reports complex value placeholders such as %1$s, %05s or %\'.10s that are not quoted in a $wpdb->prepare() query. WordPress quotes only the simple %s, %d, %f and %F placeholders, so the value of a complex one lands in the query unquoted. Identifier placeholders (%i) are always quoted by WordPress and are not reported.',
            defaultLevel: Level::Warning,
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
        foreach (PreparedQuery::analyze($context->file, $call, identifierSupported: true) as [$code, $node, $found]) {
            if ($code !== 'UnquotedComplexPlaceholder') {
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    "Complex placeholders in `\$wpdb->prepare()` are not quoted: `{$found}`",
                    $node->span,
                    'Unquoted complex placeholder found in this SQL query',
                )->withNote(
                    '`$wpdb->prepare()` quotes only the simple `%s`, `%d`, `%f` and `%F` placeholders; the value of a complex placeholder is inserted without quotes.',
                )->withHelp("Quote the placeholder in the query (e.g. `'{$found}'`), or use a simple placeholder."),
                [self::SNIFF],
            );
        }
    }
}
