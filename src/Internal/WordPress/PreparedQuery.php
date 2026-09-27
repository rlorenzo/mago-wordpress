<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

use Mago\Sdk\Syntax\CallArgument;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Strings;
use Rlorenzo\MagoWordPress\Internal\Values;

use function count;
use function is_string;

/**
 * The query of a `$wpdb->prepare()` call, flattened into its literal text.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class PreparedQuery
{
    /**
     * Stands in for a dynamic part of the query. A placeholder never
     * matches across it.
     */
    public const GAP = "\0";

    private function __construct(
        public readonly CallArgument $argument,
        public readonly string $text,
        public readonly bool $fullyLiteral,
    ) {}

    /**
     * Reads the query of a `prepare()` call on `$wpdb`. NULL when the call
     * is on another receiver, or the query has no literal text at all.
     */
    public static function fromCall(SourceFile $file, CallExpression $call): ?self
    {
        if ($call->receiver === null || !self::isWpdb($file, $call->receiver)) {
            return null;
        }

        $argument = self::queryArgument($call);
        if ($argument === null) {
            return null;
        }

        $flattened = self::flatten($file, $argument->value);

        return $flattened === null ? null : new self($argument, $flattened[0], $flattened[1]);
    }

    private static function queryArgument(CallExpression $call): ?CallArgument
    {
        foreach ($call->arguments as $argument) {
            if ($argument->name === 'query') {
                return $argument;
            }
        }

        $first = $call->arguments[0] ?? null;

        return $first !== null && $first->name === null ? $first : null;
    }

    private static function isWpdb(SourceFile $file, Node $node): bool
    {
        $node = Values::unwrap($file, $node);

        return $node->kind === NodeKind::Variable && $file->getText($node) === '$wpdb';
    }

    /**
     * Whether a node reads a `$wpdb` table property such as `$wpdb->posts`.
     */
    private static function isStaticWpdbProperty(SourceFile $file, Node $node): bool
    {
        $node = Values::unwrap($file, $node);
        if ($node->kind !== NodeKind::PropertyAccess && $node->kind !== NodeKind::NullSafePropertyAccess) {
            return false;
        }

        $children = $file->getChildren($node);
        $selector = $children[1] ?? null;

        return (
            $selector !== null
            && self::isWpdb($file, $children[0])
            && ($file->getChildren($selector)[0] ?? null)?->kind === NodeKind::LocalIdentifier
        );
    }

    /**
     * Flattens a query into its literal text, with a gap for every dynamic
     * part. A `$wpdb` table property is a static identifier, so it keeps
     * the query fully literal.
     *
     * @return null|array{string, bool} The text and whether the query is
     *     fully literal, or NULL when the query has no literal text at all.
     */
    private static function flatten(SourceFile $file, Node $query): ?array
    {
        $text = '';
        $fullyLiteral = true;
        $sawLiteralText = false;
        foreach (self::parts($file, $query) as $part) {
            if (is_string($part)) {
                $sawLiteralText = true;
                $text .= $part;
                continue;
            }

            $fullyLiteral = $fullyLiteral && self::isStaticWpdbProperty($file, $part);
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
    private static function parts(SourceFile $file, Node $node): iterable
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
            yield from self::parts($file, $children[0]);
            yield from self::parts($file, $children[2]);

            return;
        }

        if ($node->kind === NodeKind::Parenthesized && $children !== []) {
            yield from self::parts($file, $children[0]);

            return;
        }

        yield $node;
    }
}
