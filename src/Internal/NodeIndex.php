<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function array_merge;
use function in_array;
use function usort;

/**
 * The nodes of one kind under a file's program, for the rules that target
 * `Program`. Each list is exactly what `getDescendants($program, $kind)`
 * returns, in the same source order, but costs far less.
 *
 * Walking the tree materializes a PHP object for every node of the file,
 * which dominated those rules. A snapshot with a `Program` target holds the
 * whole tree numbered in depth-first pre-order with the program as node 0,
 * so the node table in id order is already that walk, and `getNodes($kind)`
 * materializes only the nodes of the kind. A snapshot whose node 0 is not
 * the program is walked instead.
 *
 * The rules are singletons in one worker and see the same `SourceFile` for
 * each file, so the lists are cached per file and dropped with it.
 *
 * @internal
 */
final class NodeIndex
{
    /** @var null|WeakMap<SourceFile, array<string, list<Node>>> */
    private static ?WeakMap $cache = null;

    private function __construct() {}

    /**
     * The descendants of $program, the file's `Program` node, of one kind.
     *
     * @return list<Node>
     */
    public static function ofKind(SourceFile $file, Node $program, NodeKind $kind): array
    {
        self::$cache ??= new WeakMap();
        /** @var array<string, list<Node>> $lists */
        $lists = self::$cache[$file] ?? [];
        $nodes = $lists[$kind->value] ?? null;
        if ($nodes === null) {
            $nodes = $file->getNode(0) === $program ? $file->getNodes($kind) : $file->getDescendants($program, $kind);
            $lists[$kind->value] = $nodes;
            self::$cache[$file] = $lists;
        }

        return $nodes;
    }

    /**
     * The descendants of $program of any of the kinds, in source order.
     *
     * @param list<NodeKind> $kinds
     *
     * @return list<Node>
     */
    public static function ofKinds(SourceFile $file, Node $program, array $kinds): array
    {
        if ($file->getNode(0) !== $program) {
            $nodes = [];
            foreach ($file->getDescendants($program) as $node) {
                if (!in_array($node->kind, $kinds, strict: true)) {
                    continue;
                }

                $nodes[] = $node;
            }

            return $nodes;
        }

        $lists = [];
        foreach ($kinds as $kind) {
            $lists[] = self::ofKind($file, $program, $kind);
        }

        $nodes = array_merge(...$lists);
        usort($nodes, static fn(Node $a, Node $b): int => $a->id <=> $b->id);

        return $nodes;
    }
}
