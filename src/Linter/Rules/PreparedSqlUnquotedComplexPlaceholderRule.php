<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\WordPress\PreparedQuery;
use Rlorenzo\MagoWordPress\Linter\CallRule;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function implode;
use function preg_match;
use function preg_match_all;
use function str_ends_with;

/**
 * Ports `WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder`.
 */
final class PreparedSqlUnquotedComplexPlaceholderRule extends CallRule
{
    /**
     * WPCS `PREPARE_PLACEHOLDER_REGEX`, not preceded or followed by a quote.
     */
    private const UNQUOTED_PLACEHOLDER = '`(?<![\'"])(?<![^%]%)%(?:[0-9]+\\\\?\$)?[+-]?(?:(?:0|\'.)?-?[0-9]*(?:\.(?:[ 0]|\'.)?[0-9]+)?|[ ]?-?[0-9]+(?:\.(?:[ 0]|\'.)?[0-9]+)?)[dfFsi](?![\'"])`';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prepared-sql-unquoted-complex-placeholder',
            name: 'Prepared SQL unquoted complex placeholder',
            description: 'Reports complex value placeholders such as %1$s, %05s or %\'.10s that are not quoted in a $wpdb->prepare() query. WordPress quotes only the simple %s, %d, %f and %F placeholders, so the value of a complex one lands in the query unquoted. Identifier placeholders (%i) are always quoted by WordPress and are not reported.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::MethodCall, NodeKind::NullSafeMethodCall],
        );
    }

    protected function names(): array
    {
        return ['prepare'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        $prepared = PreparedQuery::fromCall($context->file, $call);
        if ($prepared === null) {
            return;
        }

        $matches = [];
        preg_match_all(self::UNQUOTED_PLACEHOLDER, $prepared->text, $matches);
        $complex = array_values(array_unique(array_filter(
            $matches[0],
            static fn(string $placeholder): bool => (
                !str_ends_with($placeholder, 'i')
                && preg_match('`^%[dfFs]$`', $placeholder) !== 1
            ),
        )));
        if ($complex === []) {
            return;
        }

        $found = implode(', ', array_map(static fn(string $placeholder): string => "`{$placeholder}`", $complex));
        $context->report(Issue::new(
            "Complex placeholders in `\$wpdb->prepare()` are not quoted: {$found}",
            $prepared->argument->value->span,
            'Unquoted complex placeholder found in this SQL query',
        )->withNote(
            '`$wpdb->prepare()` quotes only the simple `%s`, `%d`, `%f` and `%F` placeholders; the value of a complex placeholder is inserted without quotes.',
        )->withHelp("Quote the placeholder in the query (e.g. `'{$complex[0]}'`), or use a simple placeholder."));
    }
}
