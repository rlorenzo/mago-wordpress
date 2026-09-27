<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function ltrim;
use function str_starts_with;
use function strtr;
use function substr;
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

        $decoded = $file->getLiteralString($node);
        if ($decoded !== null) {
            return $decoded;
        }

        // A binary string literal carries a `b` prefix before its quote.
        $literal = ltrim($file->getText($node), characters: 'bB');
        $body = substr($literal, offset: 1, length: -1);

        return str_starts_with($literal, "'") ? strtr($body, ["\\'" => "'", '\\\\' => '\\']) : Strings::decode($body);
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
