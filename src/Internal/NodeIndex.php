<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_values;
use function in_array;
use function ksort;

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
 * each file, so the lists are cached for the file being linted.
 *
 * @internal
 */
final class NodeIndex
{
    private function __construct() {}

    /**
     * The descendants of $program, the file's `Program` node, of one kind.
     *
     * @return list<Node>
     */
    public static function ofKind(SourceFile $file, Node $program, NodeKind $kind): array
    {
        if ($file->getNode(0) !== $program) {
            return $file->getDescendants($program, $kind);
        }

        return FileCache::remember($file, 'nodes:' . $kind->value, static fn(): array => $file->getNodes($kind));
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

        // Node ids are pre-order, so sorting by id restores source order.
        $nodes = [];
        foreach ($kinds as $kind) {
            foreach (self::ofKind($file, $program, $kind) as $node) {
                $nodes[$node->id] = $node;
            }
        }

        ksort($nodes);

        return array_values($nodes);
    }
}
