<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\FileGate;
use Rlorenzo\MagoWordPress\Internal\Strings;

use function rtrim;
use function str_contains;
use function str_starts_with;
use function strcspn;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * Ports `WordPress.WP.EnqueuedResources`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:halstead
 * @mago-expect lint:too-many-methods
 */
final class EnqueuedResourcesRule implements Rule
{
    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/enqueued-resources',
            name: 'Enqueued resources',
            description: 'Detects hardcoded `<script src="...">` and `<link rel="stylesheet">` tags in string '
            . 'literals and inline HTML. Scripts and stylesheets must be registered through the WordPress '
            . 'dependency API (`wp_enqueue_script()` / `wp_enqueue_style()`) so that dependencies, versioning, '
            . 'concatenation, and deduplication work correctly.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::LiteralString, NodeKind::CompositeString, NodeKind::Inline],
        );
    }

    public function lint(LintContext $context): void
    {
        $this->gate ??= new FileGate(pattern: '/<(?:script|link)\b/i');
        if (!$this->gate->passes($context->file)) {
            return;
        }

        $text = $context->getText();
        if (!str_contains($text, '<')) {
            return;
        }

        // An interpolated string is scanned whole, so a tag split across parts is still seen. Each
        // dynamic part becomes NUL bytes of the same length, which keeps every offset in place.
        if ($context->node->kind === NodeKind::CompositeString) {
            $base = $context->node->span->start;
            foreach (Strings::compositeParts($context->file, $context->node) as $part => $value) {
                if ($value !== null) {
                    continue;
                }

                for ($index = $part->span->start - $base; $index < ($part->span->end - $base); ++$index) {
                    $text[$index] = "\0";
                }
            }
        }

        $this->scanText($context, strtolower($text), $context->node->span);
    }

    private function scanText(LintContext $context, string $lower, Span $span): void
    {
        foreach (self::tagOccurrences($lower, '<script') as [$start, $end, $tag]) {
            if (self::attributeValueOffset($tag, 'src') === null) {
                continue;
            }

            $context->report(Issue::new(
                'Hardcoded `<script>` tag with a `src` attribute',
                new Span($span->start + $start, $span->start + $end),
                'Script loaded outside the WordPress dependency API',
            )->withNote(
                'Hardcoded script tags bypass dependency resolution, versioning, and deduplication provided by WordPress.',
            )->withHelp('Register the script with `wp_enqueue_script()` instead.'));
        }

        foreach (self::tagOccurrences($lower, '<link') as [$start, $end, $tag]) {
            if (!self::tagHasStylesheetRel($tag)) {
                continue;
            }

            $context->report(Issue::new(
                'Hardcoded stylesheet `<link>` tag',
                new Span($span->start + $start, $span->start + $end),
                'Stylesheet loaded outside the WordPress dependency API',
            )->withNote(
                'Hardcoded stylesheet tags bypass dependency resolution, versioning, and deduplication provided by WordPress.',
            )->withHelp('Register the stylesheet with `wp_enqueue_style()` instead.'));
        }
    }

    /**
     * Yields `[start, end, tagText]` for each occurrence of an HTML opening tag (e.g. `<script`)
     * in an already-lowercased text, where tagText runs from `<` up to (including) the next `>`
     * or the end of the text. Occurrences where the tag name continues (e.g. `<scripting`) are
     * skipped.
     *
     * @return iterable<array{int, int, string}>
     */
    private static function tagOccurrences(string $lower, string $open): iterable
    {
        $length = strlen($lower);
        $openLength = strlen($open);
        $offset = 0;

        while (($start = strpos($lower, $open, $offset)) !== false) {
            $nameEnd = $start + $openLength;
            $offset = $start + 1;

            $next = $nameEnd < $length ? $lower[$nameEnd] : null;
            if ($next !== null && self::isAlnumOrHyphen($next)) {
                continue;
            }

            $closeAt = self::findTagClosingBracket($lower, $nameEnd);
            $end = $closeAt === false ? $length : $closeAt + 1;

            yield [$start, $end, substr($lower, $start, $end - $start)];
        }
    }

    /**
     * Finds the `>` that closes an HTML tag starting at `$offset`, ignoring any `>` that falls
     * inside a quoted attribute value (e.g. `data-query="a > b"`).
     */
    private static function findTagClosingBracket(string $lower, int $offset): int|false
    {
        $length = strlen($lower);

        while (($offset += strcspn($lower, characters: '\'">', offset: $offset)) < $length) {
            if ($lower[$offset] === '>') {
                return $offset;
            }

            $closeQuote = strpos($lower, $lower[$offset], $offset + 1);
            if ($closeQuote === false) {
                return false;
            }

            $offset = $closeQuote + 1;
        }

        return false;
    }

    /**
     * Finds `name=` as an HTML attribute in an already-lowercased tag text and returns the
     * offset of the first byte of its value (past `=` and any surrounding whitespace). An
     * attribute-name boundary is required before it, so `data-src=` does not count as `src=`.
     * Text inside a quoted attribute value is never mistaken for an attribute.
     */
    private static function attributeValueOffset(string $tag, string $name): ?int
    {
        $length = strlen($tag);
        $nameLength = strlen($name);
        $index = 0;
        $quote = null;

        while ($index < $length) {
            $byte = $tag[$index];

            if ($quote !== null) {
                if ($byte === $quote) {
                    $quote = null;
                }

                ++$index;
                continue;
            }

            if ($byte === "'" || $byte === '"') {
                $quote = $byte;
                ++$index;
                continue;
            }

            if (substr($tag, $index, $nameLength) === $name) {
                $before = $index > 0 ? $tag[$index - 1] : null;
                $boundary =
                    $before !== null && (self::isAsciiWhitespace($before) || $before === "'" || $before === '"');

                if ($boundary) {
                    $cursor = $index + $nameLength;
                    while ($cursor < $length && self::isAsciiWhitespace($tag[$cursor])) {
                        ++$cursor;
                    }

                    if ($cursor < $length && $tag[$cursor] === '=') {
                        ++$cursor;
                        while ($cursor < $length && self::isAsciiWhitespace($tag[$cursor])) {
                            ++$cursor;
                        }

                        return $cursor;
                    }
                }
            }

            ++$index;
        }

        return null;
    }

    /**
     * Checks whether an already-lowercased `<link ...` tag text has `stylesheet` among its
     * space-separated `rel` tokens (e.g. `rel="stylesheet"`, `rel="alternate stylesheet"`),
     * accepting single quotes, double quotes, no quotes, and whitespace around `=`.
     */
    private static function tagHasStylesheetRel(string $tag): bool
    {
        $offset = self::attributeValueOffset($tag, 'rel');
        if ($offset === null) {
            return false;
        }

        $tokens = self::relTokenText(substr($tag, $offset));

        foreach (self::splitAsciiWhitespace($tokens) as $word) {
            if ($word === 'stylesheet') {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts the raw `rel` attribute value's token text: the quoted content up to the
     * matching quote, or, for an unquoted value, the run of alphanumeric/hyphen bytes.
     */
    private static function relTokenText(string $value): string
    {
        if (str_starts_with($value, '\\"') || str_starts_with($value, "\\'")) {
            $value = substr($value, offset: 1);
        }

        $first = $value === '' ? null : $value[0];
        if ($first === "'" || $first === '"') {
            $rest = substr($value, offset: 1);
            $end = strpos($rest, $first);

            $token = $end === false ? $rest : substr($rest, offset: 0, length: $end);

            return rtrim($token, characters: '\\');
        }

        $length = strlen($value);
        $end = 0;
        while ($end < $length && self::isAlnumOrHyphen($value[$end])) {
            ++$end;
        }

        return substr($value, offset: 0, length: $end);
    }

    /**
     * @return list<string>
     */
    private static function splitAsciiWhitespace(string $value): array
    {
        $tokens = [];
        $current = '';

        for ($index = 0, $length = strlen($value); $index < $length; ++$index) {
            $byte = $value[$index];
            if (self::isAsciiWhitespace($byte)) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $byte;
        }

        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    private static function isAlnumOrHyphen(string $byte): bool
    {
        return $byte >= 'a' && $byte <= 'z' || $byte >= '0' && $byte <= '9' || $byte === '-';
    }

    private static function isAsciiWhitespace(string $byte): bool
    {
        return $byte === ' ' || $byte === "\t" || $byte === "\n" || $byte === "\r" || $byte === "\x0C";
    }
}
