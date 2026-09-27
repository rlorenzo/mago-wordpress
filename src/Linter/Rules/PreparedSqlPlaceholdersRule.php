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
use Rlorenzo\MagoWordPress\Internal\Strings;
use Rlorenzo\MagoWordPress\Internal\Values;
use Rlorenzo\MagoWordPress\Linter\CallRule;
use Rlorenzo\MagoWordPress\Settings;

use function array_keys;
use function count;
use function implode;
use function is_string;
use function ksort;
use function max;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function version_compare;

use const PREG_SET_ORDER;
use const PREG_UNMATCHED_AS_NULL;
use const SORT_STRING;

/**
 * Ports `WordPress.DB.PreparedSQLPlaceholders`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class PreparedSqlPlaceholdersRule extends CallRule
{
    /**
     * Stands in for a dynamic part of the query. A placeholder never
     * matches across it.
     */
    private const GAP = "\0";

    /**
     * The sign, padding, alignment, width and precision a placeholder may
     * carry between `%` and its type, as in `%05d` or `%.2f`. WPCS: a
     * space pads only together with a width.
     *
     * ponytail: omits WPCS's `'x` custom padding, because it reads the
     * `%' AND` after a LIKE wildcard as a placeholder; add it with WPCS's
     * LIKE-content stripping if needed.
     */
    private const MODIFIERS = '[+-]?(?:0?-?\d*(?:\.[ 0]?\d+)?|[ ]?-?\d+(?:\.[ 0]?\d+)?)';

    /**
     * Whether the configured minimum WordPress version supports `%i`,
     * which arrived in 6.2. WPCS: an unparsable minimum falls back to its
     * current default, which does.
     */
    private readonly bool $identifierSupported;

    public function __construct(Settings $settings)
    {
        $minimum = $settings->normalizedMinimumWpVersion();
        $this->identifierSupported = $minimum === null || version_compare($minimum, version2: '6.2.0', operator: '>=');
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
        $file = $context->file;
        if ($call->receiver === null || !$this->isWpdb($file, $call->receiver)) {
            return;
        }

        $query = $this->queryArgument($call);
        if ($query === null) {
            return;
        }

        $flattened = $this->flatten($file, $query->value);
        if ($flattened === null) {
            return;
        }

        [$text, $fullyLiteral] = $flattened;
        [$expected, $quotedSimple, $quotedIdentifier, $identifier, $unsupported] = $this->scan($text);
        if ($quotedSimple) {
            $context->report(Issue::new(
                'Simple placeholders should not be quoted in the query string in `$wpdb->prepare()`',
                $query->value->span,
                'Quoted simple placeholder found in this SQL query',
            )->withNote(
                '`$wpdb->prepare()` quotes the values of the simple `%s`, `%d`, `%f` and `%F` placeholders itself; quoting the placeholder as well breaks the escaping.',
            )->withHelp(
                'Remove the quotes around the placeholder (e.g. use `WHERE name = %s` instead of `WHERE name = \'%s\'`).',
            ));
        }

        if ($quotedIdentifier) {
            $context->report(Issue::new(
                'Placeholders used for identifiers (`%i`) in the query string in `$wpdb->prepare()` are always quoted automagically',
                $query->value->span,
                'Quoted identifier placeholder found in this SQL query',
            )->withNote('`$wpdb->prepare()` wraps `%i` values in backticks itself.')->withHelp(
                'Remove the quotes or backticks around the identifier placeholder (e.g. use `FROM %i` instead of `FROM \'%i\'`).',
            ));
        }

        if ($identifier && !$this->identifierSupported) {
            $context->report(Issue::new(
                'The `%i` modifier is only supported in WP 6.2 or higher',
                $query->value->span,
                'Identifier placeholder found in this SQL query',
            )->withNote(
                'The configured `minimum-wp-version` predates WordPress 6.2, where `$wpdb->prepare()` gained `%i`.',
            )->withHelp(
                'Raise `minimum-wp-version` to 6.2 or higher, or validate the identifier against an allowlist and interpolate it.',
            ));
        }

        if ($unsupported !== '') {
            $context->report(Issue::new(
                "Unsupported placeholder in `\$wpdb->prepare()` query: {$unsupported}",
                $query->value->span,
                'Unsupported placeholder found in this SQL query',
            )->withNote(
                '`$wpdb->prepare()` only supports the `%s`, `%d`, `%f`, `%F`, and `%i` placeholders.',
            )->withHelp(
                'Use `%s` for strings, `%d` for integers, `%f` or `%F` for floats, or `%i` for identifiers (WP >= 6.2). Use `%%` for a literal percent sign.',
            ));

            return;
        }

        if ($fullyLiteral) {
            $this->checkCount($context, $call, $query, $expected);
        }
    }

    private function queryArgument(CallExpression $call): ?CallArgument
    {
        foreach ($call->arguments as $argument) {
            if ($argument->name === 'query') {
                return $argument;
            }
        }

        $first = $call->arguments[0] ?? null;

        return $first !== null && $first->name === null ? $first : null;
    }

    private function checkCount(LintContext $context, CallExpression $call, CallArgument $query, int $expected): void
    {
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
            $context->report(Issue::new(
                '`$wpdb->prepare()` called without any placeholders',
                $context->node->span,
                'This `prepare()` call has no placeholders to replace',
            )->withNote(
                'Calling `$wpdb->prepare()` on a fully-literal query with no placeholders is useless.',
            )->withHelp(
                'Pass the query directly to the query method (e.g. `$wpdb->query()`), or add placeholders for the dynamic values.',
            ));

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
            $context->report(Issue::new(
                "`\$wpdb->prepare()` placeholder count mismatch: {$expected} placeholder(s) but {$provided} replacement argument(s)",
                $context->node->span,
                "Query expects {$expected} replacement(s), {$provided} provided",
            )->withNote('Each placeholder in the query must correspond to exactly one replacement argument.')->withHelp(
                'Pass one replacement argument per placeholder (`%%` is a literal percent sign, not a placeholder).',
            ));
        }
    }

    private function isWpdb(SourceFile $file, Node $node): bool
    {
        $node = Values::unwrap($file, $node);

        return $node->kind === NodeKind::Variable && $file->getText($node) === '$wpdb';
    }

    /**
     * Whether a node reads a `$wpdb` table property such as `$wpdb->posts`.
     */
    private function isStaticWpdbProperty(SourceFile $file, Node $node): bool
    {
        $node = Values::unwrap($file, $node);
        if ($node->kind !== NodeKind::PropertyAccess && $node->kind !== NodeKind::NullSafePropertyAccess) {
            return false;
        }

        $children = $file->getChildren($node);
        $selector = $children[1] ?? null;

        return (
            $selector !== null
            && $this->isWpdb($file, $children[0])
            && ($file->getChildren($selector)[0] ?? null)?->kind === NodeKind::LocalIdentifier
        );
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
     * Flattens a query into its literal text, with a gap for every dynamic
     * part. A `$wpdb` table property is a static identifier, so it keeps
     * the query fully literal.
     *
     * @return null|array{string, bool} The text and whether the query is
     *     fully literal, or NULL when the query has no literal text at all.
     */
    private function flatten(SourceFile $file, Node $query): ?array
    {
        $text = '';
        $fullyLiteral = true;
        $sawLiteralText = false;
        foreach ($this->parts($file, $query) as $part) {
            if (is_string($part)) {
                $sawLiteralText = true;
                $text .= $part;
                continue;
            }

            $fullyLiteral = $fullyLiteral && $this->isStaticWpdbProperty($file, $part);
            $text .= self::GAP;
        }

        return $sawLiteralText ? [$text, $fullyLiteral] : null;
    }

    /**
     * Yields the parts of a query in source order: the decoded text of a
     * literal part, or the node of a dynamic part.
     *
     * @return iterable<string|Node>
     */
    private function parts(SourceFile $file, Node $node): iterable
    {
        $node = Values::unwrap($file, $node);
        $children = $file->getChildren($node);

        if ($node->kind === NodeKind::LiteralString) {
            yield Values::literalString($file, $node) ?? '';

            return;
        }

        if ($node->kind === NodeKind::CompositeString) {
            foreach (Strings::compositeParts($file, $node) as $part => $text) {
                yield $text ?? $file->getChildren($part)[0] ?? $part;
            }

            return;
        }

        if ($node->kind === NodeKind::Binary && count($children) === 3 && $file->getText($children[1]) === '.') {
            yield from $this->parts($file, $children[0]);
            yield from $this->parts($file, $children[2]);

            return;
        }

        if ($node->kind === NodeKind::Parenthesized && $children !== []) {
            yield from $this->parts($file, $children[0]);

            return;
        }

        yield $node;
    }

    /**
     * Scans query text for placeholders.
     *
     * @return array{int, bool, bool, bool, string} The expected replacement
     *     count, whether a simple value placeholder is quoted, whether an
     *     identifier placeholder is quoted, whether any identifier
     *     placeholder is used, and the formatted unsupported specifiers.
     */
    private function scan(string $text): array
    {
        $matches = [];
        preg_match_all(
            '/%(?:%|(?:(\d+)\$)?' . self::MODIFIERS . '([a-zA-Z]))/',
            $text,
            $matches,
            flags: PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );

        $unnumbered = 0;
        $maxArgnum = 0;
        $unsupported = [];
        $identifier = false;
        foreach ($matches as $match) {
            $letter = $match[2] ?? null;
            if ($letter === null) {
                continue;
            }

            if (!str_contains('sdfFi', $letter)) {
                $unsupported["`%{$letter}`"] = true;
                continue;
            }

            if ($letter === 'i') {
                $identifier = true;
            }

            $argnum = $match[1] ?? null;
            $unnumbered += $argnum === null ? 1 : 0;
            $maxArgnum = max($maxArgnum, (int) $argnum);
        }

        ksort($unsupported, SORT_STRING);
        // WPCS: WordPress quotes only the simple `%s`, `%d`, `%f` and `%F`,
        // so a quoted complex value placeholder like `'%1$s'` is correct.
        // `%i` in any form is always backtick-quoted.
        $quotedSimple = preg_match('/([\'"])%[sdfF]\1/', $text) === 1;
        $quotedIdentifier = preg_match('/([\'"`])%(?:\d+\$)?' . self::MODIFIERS . 'i\1/', $text) === 1;

        // WPCS: `%1$s` reuses a replacement, so numbered placeholders need
        // only as many as the highest number; unnumbered ones need one each.
        return [
            max($unnumbered, $maxArgnum),
            $quotedSimple,
            $quotedIdentifier,
            $identifier,
            implode(', ', array_keys($unsupported)),
        ];
    }
}
