<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallArgument;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Values;

use function array_keys;
use function count;
use function implode;
use function ksort;
use function max;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_starts_with;
use function stripcslashes;
use function strtolower;
use function strtr;
use function substr;

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
final class PreparedSqlPlaceholdersRule implements Rule
{
    /**
     * Stands in for a dynamic part of the query. A placeholder never
     * matches across it.
     */
    private const GAP = "\0";

    private ?FileGate $gate = null;

    private string $text = '';

    private bool $fullyLiteral = true;

    private bool $sawLiteralText = false;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/prepared-sql-placeholders',
            name: 'Prepared SQL placeholders',
            description: 'Validates placeholder usage in $wpdb->prepare() calls: quoted placeholders, placeholders other than %s, %d, %f and %i, a placeholder count that differs from the replacement arguments, and prepare() calls with no placeholders at all.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::MethodCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $this->gate ??= new FileGate(pattern: '/\$wpdb\s*->\s*prepare\s*\(/i');
        if (!$this->gate->passes($file)) {
            return;
        }

        $object = $file->getChildren($context->node)[0] ?? null;
        if (
            $object === null
            || !$this->isWpdb($file, $object)
            || strtolower(Calls::name($file, $context->node) ?? '') !== 'prepare'
        ) {
            return;
        }

        $call = CallExpression::fromNode($file, $context->node);
        $query = $this->queryArgument($call);
        if ($query === null) {
            return;
        }

        $this->text = '';
        $this->fullyLiteral = true;
        $this->sawLiteralText = false;
        $this->walk($file, $query->value);
        if (!$this->sawLiteralText) {
            return;
        }

        [$expected, $quoted, $unsupported] = $this->scan($this->text);
        if ($quoted) {
            $context->report(Issue::new(
                'Placeholder in `$wpdb->prepare()` query must not be quoted',
                $query->value->span,
                'Quoted placeholder found in this SQL query',
            )->withNote(
                '`$wpdb->prepare()` adds quoting to replaced values itself; quoting the placeholder breaks the escaping.',
            )->withHelp(
                'Remove the quotes around the placeholder (e.g. use `WHERE name = %s` instead of `WHERE name = \'%s\'`).',
            ));
        }

        if ($unsupported !== '') {
            $context->report(Issue::new(
                "Unsupported placeholder in `\$wpdb->prepare()` query: {$unsupported}",
                $query->value->span,
                'Unsupported placeholder found in this SQL query',
            )->withNote('`$wpdb->prepare()` only supports the `%s`, `%d`, `%f`, and `%i` placeholders.')->withHelp(
                'Use `%s` for strings, `%d` for integers, `%f` for floats, or `%i` for identifiers (WP >= 6.2). Use `%%` for a literal percent sign.',
            ));

            return;
        }

        if ($this->fullyLiteral) {
            $this->checkCount($context, $call, $query, $expected);
        }
    }

    private function queryArgument(CallExpression $call): ?CallArgument
    {
        foreach ($call->arguments as $argument) {
            if ($argument->name !== null && strtolower($argument->name) === 'query') {
                return $argument;
            }
        }

        $first = $call->arguments[0] ?? null;

        return $first !== null && $first->name === null ? $first : null;
    }

    private function checkCount(LintContext $context, CallExpression $call, CallArgument $query, int $expected): void
    {
        $extra = null;
        foreach ($call->arguments as $argument) {
            if ($argument === $query) {
                continue;
            }

            if ($argument->unpacked) {
                return;
            }

            $extra = $argument->value;
        }

        $provided = count($call->arguments) - 1;
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

        if ($provided === 1 && $extra !== null && $extra->kind !== NodeKind::Literal) {
            $provided = $this->countArrayValues($context->file, $extra);
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
        if ($node->kind === NodeKind::Access) {
            $node = $file->getChildren($node)[0] ?? $node;
        }

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
     * Collects the literal text of a query into $text, with a gap for
     * every dynamic part.
     */
    private function walk(SourceFile $file, Node $node): void
    {
        $node = Values::unwrap($file, $node);
        $children = $file->getChildren($node);

        if ($node->kind === NodeKind::LiteralString) {
            $this->sawLiteralText = true;
            $this->text .= $this->unquote($file->getText($node));

            return;
        }

        if ($node->kind === NodeKind::CompositeString) {
            $this->walkParts($file, $children[0] ?? $node);

            return;
        }

        if ($node->kind === NodeKind::Binary && count($children) === 3 && $file->getText($children[1]) === '.') {
            $this->walk($file, $children[0]);
            $this->walk($file, $children[2]);

            return;
        }

        if ($node->kind === NodeKind::Parenthesized && $children !== []) {
            $this->walk($file, $children[0]);

            return;
        }

        $this->gap($file, $node);
    }

    private function walkParts(SourceFile $file, Node $string): void
    {
        $nowdoc = str_starts_with($file->getText($string), "<<<'");
        foreach ($file->getChildren($string) as $part) {
            $part = $file->getChildren($part)[0] ?? $part;
            if ($part->kind !== NodeKind::LiteralStringPart) {
                $this->gap($file, $file->getChildren($part)[0] ?? $part);
                continue;
            }

            $this->sawLiteralText = true;
            $text = $file->getText($part);
            $this->text .= $nowdoc ? $text : stripcslashes($text);
        }
    }

    /**
     * Ends the literal run at a dynamic part. A `$wpdb` table property is a
     * static identifier, so it keeps the query fully literal.
     */
    private function gap(SourceFile $file, Node $node): void
    {
        $this->fullyLiteral = $this->fullyLiteral && $this->isStaticWpdbProperty($file, $node);
        $this->text .= self::GAP;
    }

    private function unquote(string $literal): string
    {
        $body = substr($literal, offset: 1, length: -1);

        return str_starts_with($literal, "'") ? strtr($body, ["\\'" => "'", '\\\\' => '\\']) : stripcslashes($body);
    }

    /**
     * Scans query text for placeholders.
     *
     * @return array{int, bool, string} The expected replacement count, whether a
     *     placeholder is quoted, and the formatted unsupported specifiers.
     */
    private function scan(string $text): array
    {
        $matches = [];
        preg_match_all(
            '/%(?:%|(?:(\d+)\$)?([a-zA-Z]))/',
            $text,
            $matches,
            flags: PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );

        $unnumbered = 0;
        $maxArgnum = 0;
        $unsupported = [];
        foreach ($matches as $match) {
            $letter = $match[2] ?? null;
            if ($letter === null) {
                continue;
            }

            if (!str_contains('sdfi', $letter)) {
                $unsupported["`%{$letter}`"] = true;
                continue;
            }

            $argnum = $match[1] ?? null;
            $unnumbered += $argnum === null ? 1 : 0;
            $maxArgnum = max($maxArgnum, (int) $argnum);
        }

        ksort($unsupported, SORT_STRING);
        $quoted = preg_match('/([\'"])%(?:\d+\$)?[sdfi]\1/', $text) === 1;

        return [max($unnumbered, $maxArgnum), $quoted, implode(', ', array_keys($unsupported))];
    }
}
