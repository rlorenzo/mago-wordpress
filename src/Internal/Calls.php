<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function chr;
use function count;
use function in_array;
use function str_starts_with;
use function stripos;
use function strspn;
use function strtolower;
use function substr;

/**
 * Finds and matches call expressions by name.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class Calls
{
    private const IDENTIFIER_KINDS = [
        NodeKind::Identifier,
        NodeKind::LocalIdentifier,
        NodeKind::QualifiedIdentifier,
        NodeKind::FullyQualifiedIdentifier,
    ];

    private function __construct() {}

    /**
     * Returns the normalized wanted name a call node matches, or NULL.
     *
     * An imported resolution wins over the written name, so
     * `use function Foo\bar;` does not match global bar(), and an aliased
     * import still matches. Mago resolves an unimported unqualified call
     * into the current namespace, but PHP uses the global function at run
     * time, so only an imported resolution wins over the written text.
     * Mago's own rules match global functions the same way.
     *
     * The written name comes from the cheap text scan. That scan
     * over-matches one shape. A curried call such as `md5(1)(2)` starts
     * with `md5` in the source, but its callee is another call. This method
     * checks every hit from the scan against name(). That method walks the
     * real callee, so a match reported here is always exact. A miss
     * needs no second check, because the scanned name covers every name
     * that the walk can find.
     *
     * The result is memoized per node. Several call rules subscribe to the
     * same call kinds, so the worker dispatches each of them for the same
     * node. Without the cache, each of them derives the same name again. The
     * cache has one slot per file, and a change of contents clears it, so
     * a file re-analyzed at the same path after an edit starts fresh.
     *
     * @param array<string, true> $wanted
     */
    public static function matchWanted(SourceFile $file, Node $node, array $wanted): ?string
    {
        static $contents = null;
        /** @var array<int, string|null> $names */
        static $names = [];
        /** @var array<int, true> $resolved */
        static $resolved = [];

        if ($contents !== $file->contents) {
            $contents = $file->contents;
            $names = [];
            $resolved = [];
        }

        $id = $node->id;
        if (!array_key_exists($id, $names)) {
            $name = null;
            $written = self::writtenNameFast($file, $node);
            if ($written !== null && stripos($written, needle: 'namespace\\') === 0) {
                // A `namespace\foo()` relative call is the global function only in the
                // global namespace. WPCS cannot resolve relative names and never reports
                // them, so the rules match that (WPCS documents this as a limitation it
                // means to lift).
                $written = null;
            } elseif ($node->kind === NodeKind::FunctionCall) {
                $imported = $file->getResolvedName($node);
                if ($imported !== null && $imported->imported) {
                    $name = $imported->name;
                    $resolved[$id] = true;
                }
            }

            $names[$id] = $name ?? $written;
        }

        $name = $names[$id];
        if ($name === null) {
            return null;
        }

        $name = self::normalize($name);
        if (!($wanted[$name] ?? false)) {
            return null;
        }

        if ($resolved[$id] ?? false) {
            return $name;
        }

        $precise = self::name($file, $node);

        return $precise !== null && self::normalize($precise) === $name ? $name : null;
    }

    /**
     * Returns the written name of a call node without walking its callee.
     *
     * The callee text of a function call is at the start of the node's own
     * span, so the identifier run there is the written name. This
     * over-matches a curried call, whose callee is another call that starts
     * with the same run. A caller that acts on a hit must check it again
     * against name().
     *
     * A method or static call keeps the selector in the member child. A
     * named selector has exactly the member's span, so the member's text is
     * the selector. A variable or expression selector has `$` or `{` as its
     * first byte. name() maps those shapes to NULL, so this branch is exact
     * and does not over-match.
     */
    public static function writtenNameFast(SourceFile $file, Node $node): ?string
    {
        if ($node->kind === NodeKind::FunctionCall) {
            return self::leadingIdentifier($file->contents, $node->span->start);
        }

        $member = $file->getChildren($node)[1] ?? null;
        if ($member === null) {
            return null;
        }

        $text = $file->getText($member);
        if ($text === '' || $text[0] === '$' || $text[0] === '{') {
            return null;
        }

        return $text;
    }

    /**
     * Reads the identifier run at a byte offset straight from the source.
     *
     * The accepted bytes cover PHP's identifier grammar plus the namespace
     * separator, so a qualified name comes back whole, and a dynamic
     * callee gives NULL at its `$`, `(` or quote.
     */
    public static function leadingIdentifier(string $contents, int $offset): ?string
    {
        $length = strspn($contents, self::identifierBytes(), $offset);

        return $length === 0 ? null : substr($contents, $offset, $length);
    }

    private static function identifierBytes(): string
    {
        static $bytes = '';
        if ($bytes === '') {
            $bytes = '\\_0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            for ($byte = 0x80; $byte <= 0xff; ++$byte) {
                $bytes .= chr($byte);
            }
        }

        return $bytes;
    }

    /**
     * Returns the callee name of a call node, as written in the source.
     *
     * CallExpression::fromNode materializes every argument first, so a
     * name lookup that only filters candidates reads the callee children
     * directly.
     */
    public static function name(SourceFile $file, Node $node): ?string
    {
        $children = $file->getChildren($node);

        if ($node->kind === NodeKind::FunctionCall) {
            $callee = $children[0] ?? null;
            while (
                $callee !== null
                && ($callee->kind === NodeKind::Expression || $callee->kind === NodeKind::ConstantAccess)
            ) {
                $callee = $file->getChildren($callee)[0] ?? null;
            }

            if ($callee !== null && in_array($callee->kind, self::IDENTIFIER_KINDS, strict: true)) {
                return $file->getText($callee);
            }

            return null;
        }

        // Method and static calls keep the selector in the member child.
        $member = $children[1] ?? null;
        $selector = $member === null ? null : $file->getChildren($member)[0] ?? null;

        return $selector !== null && $selector->kind === NodeKind::LocalIdentifier ? $file->getText($selector) : null;
    }

    /**
     * Returns the positional argument values of a call view, in source order.
     *
     * This skips named and unpacked arguments, so an index here agrees
     * with the parameter position only if every earlier argument is
     * positional.
     *
     * @return list<Node>
     */
    public static function positionalArguments(SourceFile $file, CallExpression $call): array
    {
        $values = [];
        foreach ($call->arguments as $argument) {
            if ($argument->unpacked || $argument->name !== null) {
                continue;
            }

            $values[] = Values::unwrap($file, $argument->value);
        }

        return $values;
    }

    /**
     * Returns an argument value, read positionally or by parameter name.
     *
     * PHP binds a named argument by parameter name, so `unserialize($data,
     * options: [])` puts the options in the same place as the second
     * positional argument. Reading only the positions does not find it.
     * A list of names covers a parameter PHP 8.0 renamed.
     *
     * @param string|list<string>|null $parameter
     */
    public static function argument(
        SourceFile $file,
        CallExpression $call,
        int $index,
        string|array|null $parameter = null,
    ): ?Node {
        if ($parameter !== null) {
            foreach ($call->arguments as $argument) {
                if ($argument->name !== null && in_array($argument->name, (array) $parameter, strict: true)) {
                    return Values::unwrap($file, $argument->value);
                }
            }
        }

        return self::positionalArguments($file, $call)[$index] ?? null;
    }

    /**
     * Whether a comment overlaps the span, so a fix that rewrites it would drop or break the comment.
     */
    public static function hasComment(SourceFile $file, Span $span): bool
    {
        // Trivia are disjoint and in source order: binary search for the first one ending after the span starts.
        $trivia = $file->getTrivia();
        $low = 0;
        $high = count($trivia);
        while ($low < $high) {
            $mid = ($low + $high) >> 1;
            if ($trivia[$mid]->span->end > $span->start) {
                $high = $mid;
                continue;
            }

            $low = $mid + 1;
        }

        return $low < count($trivia) && $trivia[$low]->span->start < $span->end;
    }

    /**
     * Whether a call spreads an array into its arguments. Such a spread
     * hides what is in a given position.
     */
    public static function isUnpacked(CallExpression $call): bool
    {
        foreach ($call->arguments as $argument) {
            if ($argument->unpacked) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a callee name matches a wanted name.
     */
    public static function matches(string $name, string $wanted): bool
    {
        return self::normalize($name) === self::normalize($wanted);
    }

    /**
     * Normalizes wanted names into a lookup set.
     *
     * The finders test one candidate per descendant, so this method
     * normalizes the list once, instead of once per candidate.
     *
     * @param list<string> $names
     * @return array<string, true>
     */
    public static function normalizeAll(array $names): array
    {
        $set = [];
        foreach ($names as $name) {
            $set[self::normalize($name)] = true;
        }

        return $set;
    }

    /**
     * Lowercases a name and removes a leading separator.
     *
     * A caller that keys a table by name must normalize the same way that
     * the match does. Without that, `\format_date()` matches and then
     * misses the lookup.
     */
    public static function normalize(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            $name = substr($name, offset: 1);
        }

        return strtolower($name);
    }
}
