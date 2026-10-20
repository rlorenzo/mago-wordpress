<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_filter;
use function array_map;
use function in_array;

/**
 * An assignment statement, constant or property declaration as the alignment sniff reads it.
 *
 * @internal
 */
final class AssignmentStatement
{
    private function __construct() {}

    /**
     * @return list<array{varEnd: int, length: int, found: int, gap: Span, operator: Span}> one per
     *         equals sign the sniff sees: a declaration with several defaults has one per item
     */
    public static function of(SourceFile $file, Node $child): array
    {
        $node = $file->getChildren($child)[0] ?? null;
        if (!in_array($child->kind, [NodeKind::Statement, NodeKind::ClassLikeMember], strict: true) || $node === null) {
            return [];
        }

        $found = match ($node->kind) {
            NodeKind::ExpressionStatement => array_filter([self::expression($file, $node)]),
            NodeKind::Constant,
            NodeKind::Static,
            NodeKind::ClassLikeConstant,
            NodeKind::Property,
                => AssignmentItems::all($file, $node),
            default => [],
        };

        return array_map(static fn(array $item): array => AssignmentItems::describe(
            $file->contents,
            $item[0],
            $item[1],
        ), $found);
    }

    /**
     * @return null|array{int, Span} the end of the assigned-to expression and the operator
     */
    private static function expression(SourceFile $file, Node $statement): ?array
    {
        $assignment = $file->getChildren($file->getChildren($statement)[0] ?? $statement)[0] ?? null;
        if ($assignment === null || $assignment->kind !== NodeKind::Assignment) {
            return null;
        }

        $parts = $file->getChildren($assignment);
        $operator = $parts[1] ?? null;

        return (
            $operator === null || $operator->kind !== NodeKind::AssignmentOperator
                ? null
                : [$parts[0]->span->end, $operator->span]
        );
    }
}
