<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function preg_match;
use function str_replace;
use function trim;

/**
 * Reads values out of expression nodes.
 *
 * @internal
 */
final class Values
{
    private function __construct() {}

    /**
     * Returns the decoded value of a literal-string node.
     *
     * If the snapshot sends a raw literal, the text is decoded here.
     */
    public static function literalString(SourceFile $file, Node $node): ?string
    {
        if ($node->kind !== NodeKind::LiteralString) {
            return null;
        }

        return $file->getLiteralString($node) ?? Strings::unquote($file->getText($node));
    }

    /**
     * Returns the value of a decimal integer-literal node, accepting `_`
     * digit separators. Hex, octal, and binary literals yield NULL.
     */
    public static function literalInteger(SourceFile $file, Node $node): ?int
    {
        if ($node->kind !== NodeKind::LiteralInteger) {
            return null;
        }

        $text = str_replace(search: '_', replace: '', subject: $file->getText($node));

        // Leading zero = legacy octal; the round-trip rejects values past PHP_INT_MAX.
        if (preg_match('/^(?:0|[1-9]\d*)$/', $text) !== 1) {
            return null;
        }

        $value = (int) $text;

        return (string) $value === $text ? $value : null;
    }

    /**
     * Unwraps the wrapper nodes around a value.
     *
     * An argument that is itself a call arrives as `Expression -> Call ->
     * FunctionCall`, so `Call` must come off too. Without that, every
     * check against a call kind misses.
     */
    public static function unwrap(SourceFile $file, Node $node): Node
    {
        while (
            $node->kind === NodeKind::Expression
            || $node->kind === NodeKind::Literal
            || $node->kind === NodeKind::Call
        ) {
            $child = $file->getChildren($node)[0] ?? null;
            if ($child === null) {
                break;
            }

            $node = $child;
        }

        return $node;
    }

    /**
     * Whether the subtree concatenates strings with `.`.
     */
    public static function concatenates(SourceFile $file, Node $node): bool
    {
        // One walk with an early exit at the first '.' operator.
        $stack = [$node];
        while (($current = array_pop($stack)) !== null) {
            if ($current->kind === NodeKind::BinaryOperator && trim($file->getText($current)) === '.') {
                return true;
            }

            foreach ($file->getChildren($current) as $child) {
                $stack[] = $child;
            }
        }

        return false;
    }
}
