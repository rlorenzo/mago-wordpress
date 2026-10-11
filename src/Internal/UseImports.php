<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function ltrim;
use function strtolower;

/**
 * The imports of one `use` statement: each item with its type (`name`, `function` or `const`),
 * its full name, its name node and its alias, whether the statement is a plain list, a typed list
 * or a group.
 *
 * @internal
 */
final class UseImports
{
    private function __construct() {}

    /**
     * @return list<array{type: string, name: string, nameNode: Node, alias: ?Node}>
     */
    public static function items(SourceFile $file, Node $use): array
    {
        $items = [];
        foreach ($file->getDescendants($use, NodeKind::UseItem) as $item) {
            $nameNode = self::child($file, $item, NodeKind::Identifier);
            if ($nameNode === null) {
                continue;
            }

            $parent = $file->getParent($item);
            $typed = $parent !== null && $parent->kind === NodeKind::MaybeTypedUseItem;
            $list = $typed ? $file->getParent($parent) : $parent;
            $type = self::type($file, $typed ? $parent : null) ?? self::type($file, $list) ?? 'name';

            $items[] = [
                'type' => $type,
                'name' => self::groupPrefix($file, $list) . ltrim($file->getText($nameNode), characters: '\\'),
                'nameNode' => $nameNode,
                'alias' => self::child($file, $item, NodeKind::UseItemAlias),
            ];
        }

        return $items;
    }

    private static function child(SourceFile $file, Node $node, NodeKind $kind): ?Node
    {
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === $kind) {
                return $child;
            }
        }

        return null;
    }

    /** The `Foo\` of `use Foo\{A, B};`, or an empty string outside a group. */
    private static function groupPrefix(SourceFile $file, ?Node $list): string
    {
        if (
            $list === null
            || !in_array($list->kind, [NodeKind::MixedUseItemList, NodeKind::TypedUseItemList], strict: true)
        ) {
            return '';
        }

        $base = self::child($file, $list, NodeKind::Identifier);

        return $base === null ? '' : ltrim($file->getText($base), characters: '\\') . '\\';
    }

    /** `function` or `const` from the `UseType` child of $node, or NULL when it has none. */
    private static function type(SourceFile $file, ?Node $node): ?string
    {
        $type = $node === null ? null : self::child($file, $node, NodeKind::UseType);

        return $type === null ? null : strtolower($file->getText($type));
    }
}
