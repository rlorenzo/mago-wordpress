<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallArgument;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Internal\WordPress\Placeholders;
use Rlorenzo\MagoWordPress\Internal\WordPress\PreparedQuery;
use Rlorenzo\MagoWordPress\Internal\WordPress\WpVersion;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function count;
use function implode;
use function ksort;
use function max;
use function preg_match;
use function preg_match_all;
use function str_contains;

use const PREG_SET_ORDER;
use const SORT_STRING;

/**
 * Ports `WordPress.DB.PreparedSQLPlaceholders`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
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

    public function __construct(Settings $settings)
    {
        $this->identifierSupported = WpVersion::reached($settings->normalizedMinimumWpVersion(), '6.2.0');
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prepared-sql-placeholders',
            name: 'Prepared SQL placeholders',
            description: 'Validates placeholder usage in $wpdb->prepare() calls: quoted placeholders, placeholders other than %s, %d, %f, %F and %i, a placeholder count that differs from the replacement arguments, and prepare() calls with no placeholders at all.',
            defaultLevel: Level::Error,
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

        $query = $prepared->argument;
        [$expected, $highest, $quotedSimple, $quotedIdentifier, $identifier, $unsupported] =
            $this->scan($prepared->text);
        if ($quotedSimple) {
            Report::issue(
                $context,
                Issue::new(
                    'Simple placeholders should not be quoted in the query string in `$wpdb->prepare()`',
                    $query->value->span,
                    'Quoted simple placeholder found in this SQL query',
                )->withNote(
                    '`$wpdb->prepare()` quotes the values of the simple `%s`, `%d`, `%f` and `%F` placeholders itself; quoting the placeholder as well breaks the escaping.',
                )->withHelp(
                    'Remove the quotes around the placeholder (e.g. use `WHERE name = %s` instead of `WHERE name = \'%s\'`).',
                ),
                [self::SNIFF . '.QuotedSimplePlaceholder'],
            );
        }

        if ($quotedIdentifier) {
            Report::issue(
                $context,
                Issue::new(
                    'Placeholders used for identifiers (`%i`) in the query string in `$wpdb->prepare()` are always quoted automagically',
                    $query->value->span,
                    'Quoted identifier placeholder found in this SQL query',
                )->withNote('`$wpdb->prepare()` wraps `%i` values in backticks itself.')->withHelp(
                    'Remove the quotes or backticks around the identifier placeholder (e.g. use `FROM %i` instead of `FROM \'%i\'`).',
                ),
                [self::SNIFF . '.QuotedIdentifierPlaceholder'],
            );
        }

        if ($identifier && !$this->identifierSupported) {
            Report::issue(
                $context,
                Issue::new(
                    'The `%i` modifier is only supported in WP 6.2 or higher',
                    $query->value->span,
                    'Identifier placeholder found in this SQL query',
                )->withNote(
                    'The configured `minimum-wp-version` predates WordPress 6.2, where `$wpdb->prepare()` gained `%i`.',
                )->withHelp(
                    'Raise `minimum-wp-version` to 6.2 or higher, or validate the identifier against an allowlist and interpolate it.',
                ),
                [self::SNIFF . '.UnsupportedIdentifierPlaceholder'],
            );
        }

        if ($unsupported !== '') {
            Report::issue(
                $context,
                Issue::new(
                    "Unsupported placeholder in `\$wpdb->prepare()` query: {$unsupported}",
                    $query->value->span,
                    'Unsupported placeholder found in this SQL query',
                )->withNote(
                    '`$wpdb->prepare()` only supports the `%s`, `%d`, `%f`, `%F`, and `%i` placeholders.',
                )->withHelp(
                    'Use `%s` for strings, `%d` for integers, `%f` or `%F` for floats, or `%i` for identifiers (WP >= 6.2). Use `%%` for a literal percent sign.',
                ),
                [self::SNIFF . '.UnsupportedPlaceholder'],
            );

            return;
        }

        if ($prepared->fullyLiteral) {
            $this->checkCount($context, $call, $query, $expected, $highest);
        }
    }

    private function checkCount(
        LintContext $context,
        CallExpression $call,
        CallArgument $query,
        int $expected,
        int $highest,
    ): void {
        $replacements = [];
        foreach ($call->arguments as $argument) {
            if ($argument->unpacked) {
                return;
            }

            if ($argument !== $query) {
                $replacements[] = $argument->value;
            }
        }

        $provided = count($replacements);
        if ($expected === 0 && $provided === 0) {
            Report::issue(
                $context,
                Issue::new(
                    '`$wpdb->prepare()` called without any placeholders',
                    $context->node->span,
                    'This `prepare()` call has no placeholders to replace',
                )->withNote(
                    'Calling `$wpdb->prepare()` on a fully-literal query with no placeholders is useless.',
                )->withHelp(
                    'Pass the query directly to the query method (e.g. `$wpdb->query()`), or add placeholders for the dynamic values.',
                ),
                [self::SNIFF . '.UnnecessaryPrepare'],
            );

            return;
        }

        // A single non-literal replacement may be an array of all the values.
        // With no placeholders, any replacement is a mismatch.
        if ($expected > 0 && $provided === 1 && $replacements[0]->kind !== NodeKind::Literal) {
            $provided = $this->countArrayValues(
                $context->file,
                Values::unparenthesize($context->file, $replacements[0]),
            );
        }

        if ($provided !== null && $provided !== $expected) {
            Report::issue(
                $context,
                Issue::new(
                    "`\$wpdb->prepare()` placeholder count mismatch: {$expected} placeholder(s) but {$provided} replacement argument(s)",
                    $context->node->span,
                    "Query expects {$expected} replacement(s), {$provided} provided",
                )->withNote(
                    'Each placeholder in the query must correspond to exactly one replacement argument.',
                )->withHelp(
                    'Pass one replacement argument per placeholder (`%%` is a literal percent sign, not a placeholder).',
                ),
                [
                    self::SNIFF . match (true) {
                        $provided === 0 => '.MissingReplacements',
                        $expected === 0 => '.UnfinishedPrepare',
                        default => '.ReplacementsWrongNumber',
                    },
                ],
            );

            return;
        }

        // `%3$s` needs a third replacement even when the counts match.
        if ($provided !== null && $highest > $provided) {
            Report::issue(
                $context,
                Issue::new(
                    "`\$wpdb->prepare()` placeholder `%{$highest}\$` refers to a missing replacement argument",
                    $context->node->span,
                    "Query refers to replacement {$highest}, {$provided} provided",
                )->withNote(
                    'A numbered placeholder such as `%2$s` reads the replacement argument at that position.',
                )->withHelp('Renumber the placeholders, or pass the missing replacement arguments.'),
                [self::SNIFF . '.ReplacementsWrongNumber'],
            );
        }
    }

    /**
     * Counts the values of an array literal. NULL means the count is
     * unknowable: a spread element, or a value that is not an array.
     */
    private function countArrayValues(SourceFile $file, Node $array): ?int
    {
        if ($array->kind !== NodeKind::Array && $array->kind !== NodeKind::LegacyArray) {
            return null;
        }

        $count = 0;
        foreach ($file->getChildren($array) as $element) {
            $kind = ($file->getChildren($element)[0] ?? $element)->kind;
            if ($kind === NodeKind::VariadicArrayElement) {
                return null;
            }

            $count += $kind === NodeKind::MissingArrayElement ? 0 : 1;
        }

        return $count;
    }

    /**
     * Scans query text for placeholders.
     *
     * @return array{int, int, bool, bool, bool, string} The placeholder
     *     count, the highest argument number, whether a simple value placeholder is quoted, whether an
     *     identifier placeholder is quoted, whether any identifier
     *     placeholder is used, and the formatted unsupported specifiers.
     */
    private function scan(string $text): array
    {
        // WPCS and `wpdb::prepare()` expect one replacement per placeholder
        // occurrence, even when `%1$s` repeats a number, and count those in
        // `LIKE` wildcards too.
        $placeholders = [];
        $count = (int) preg_match_all('`' . Placeholders::PLACEHOLDER . '`', $text, $placeholders);
        $highest = 0;
        foreach ($placeholders[0] as $placeholder) {
            $argnum = [];
            if (preg_match('`^%([0-9]+)`', $placeholder, $argnum) === 1 && str_contains($placeholder, '$')) {
                $highest = max($highest, (int) $argnum[1]);
            }
        }

        $text = Placeholders::withoutLikeOperands($text);
        $matches = [];
        preg_match_all('`%(?:%|' . Placeholders::SPEC . '([a-zA-Z]))`', $text, $matches, flags: PREG_SET_ORDER);

        $unsupported = [];
        $identifier = false;
        foreach ($matches as $match) {
            $letter = $match[1] ?? '';
            if ($letter === 'i') {
                $identifier = true;
            }

            if ($letter !== '' && !str_contains('sdfFi', $letter)) {
                $unsupported["`%{$letter}`"] = true;
            }
        }

        ksort($unsupported, SORT_STRING);
        // WPCS: WordPress quotes only the simple `%s`, `%d`, `%f` and `%F`,
        // so a quoted complex value placeholder like `'%1$s'` is correct.
        // `%i` in any form is always backtick-quoted.
        $quotedSimple = preg_match('`([\'"])%[sdfF]\1`', $text) === 1;
        $quotedIdentifier = preg_match('/([\'"`])%' . Placeholders::SPEC . 'i\1/', $text) === 1;

        return [
            $count,
            $highest,
            $quotedSimple,
            $quotedIdentifier,
            $identifier,
            implode(', ', array_keys($unsupported)),
        ];
    }
}
