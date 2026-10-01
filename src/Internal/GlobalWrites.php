<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;

/**
 * Finds variable writes and the scopes they happen in, for the rules that
 * check writes to global variables. The ancestor walks need the parent
 * chain of every node, so callers target `Program`.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class GlobalWrites
{
    /**
     * Function-like scopes. A write outside all of these is top-level.
     */
    private const SCOPE_KINDS = [
        NodeKind::Function,
        NodeKind::Method,
        NodeKind::Closure,
        NodeKind::ArrowFunction,
        NodeKind::PropertyHook,
    ];

    /**
     * The name `$$` a function scope maps to once it has any `global` statement,
     * since a variable variable may resolve to any imported name.
     */
    public const ANY_IMPORT = '$$';

    private function __construct() {}

    /**
     * Returns the nearest enclosing function-like scope, or null in the top-level scope.
     */
    public static function nearestScope(SourceFile $file, Node $node): ?Node
    {
        $parent = $file->getParent($node);
        while ($parent !== null) {
            if (in_array($parent->kind, self::SCOPE_KINDS, strict: true)) {
                return $parent;
            }

            $parent = $file->getParent($parent);
        }

        return null;
    }

    /**
     * Maps each function-like scope's id (the program's for the file scope) to the
     * variables it imports with `global`, each mapped to the start of its earliest
     * `global` statement.
     *
     * @return array<int, array<string, int>>
     */
    public static function imports(SourceFile $file, Node $program): array
    {
        $imports = [];
        foreach (NodeIndex::ofKind($file, $program, NodeKind::Global) as $global) {
            // A top-level `global` keys on the program node, for `treat_files_as_scoped`.
            $scope = self::nearestScope($file, $global) ?? $program;

            // Descendants arrive in source order, so the first import is the earliest.
            $imports[$scope->id][self::ANY_IMPORT] ??= $global->span->start;
            foreach ($file->getDescendants($global, NodeKind::DirectVariable) as $variable) {
                $imports[$scope->id][$file->getText($variable)] ??= $global->span->start;
            }
        }

        return $imports;
    }

    /**
     * Returns the write targets of every assignment and foreach binding,
     * unwrapped: a by-reference `&$x` yields `$x`, and a list/array
     * destructuring pattern yields each of its targets, however deeply nested.
     *
     * @return list<Node>
     */
    public static function targets(SourceFile $file, Node $program): array
    {
        $targets = [];
        $nodes = NodeIndex::ofKinds(
            $file,
            $program,
            [
                NodeKind::Assignment,
                NodeKind::ForeachValueTarget,
                NodeKind::ForeachKeyValueTarget,
            ],
        );
        foreach ($nodes as $node) {
            $bindings = match ($node->kind) {
                // `$x = ...` and `foreach ($a as $x)`: the first child binds.
                NodeKind::Assignment, NodeKind::ForeachValueTarget => [$file->getChildren($node)[0] ?? $node],
                // `foreach ($a as $k => $v)`: both the key and the value bind.
                NodeKind::ForeachKeyValueTarget => $file->getChildren($node),
                default => [],
            };

            foreach ($bindings as $binding) {
                self::collect($file, $binding, $targets);
            }
        }

        return $targets;
    }

    /**
     * @param list<Node> $targets
     */
    private static function collect(SourceFile $file, Node $target, array &$targets): void
    {
        $target = Values::unwrap($file, $target);
        $children = $file->getChildren($target);

        if ($target->kind === NodeKind::UnaryPrefix) {
            if (($children[1] ?? null) !== null && $file->getText($children[0]) === '&') {
                self::collect($file, $children[1], $targets);
            }

            return;
        }

        if (!in_array($target->kind, [NodeKind::Array, NodeKind::LegacyArray, NodeKind::List], strict: true)) {
            $targets[] = $target;

            return;
        }

        foreach ($children as $element) {
            $wrapped = $file->getChildren($element)[0] ?? null;
            if ($wrapped === null) {
                continue;
            }

            // A key-value element writes to its value; the key only selects the source offset.
            $value = match ($wrapped->kind) {
                NodeKind::KeyValueArrayElement => $file->getChildren($wrapped)[1] ?? null,
                NodeKind::ValueArrayElement => $file->getChildren($wrapped)[0] ?? null,
                default => null,
            };
            if ($value !== null) {
                self::collect($file, $value, $targets);
            }
        }
    }
}
