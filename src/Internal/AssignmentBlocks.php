<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\SourceFile;

/**
 * The blocks of adjacent assignments in a file, as `Generic.Formatting.MultipleStatementAlignment`
 * finds them: runs of assignment statements, constants and properties that are siblings on
 * consecutive lines.
 *
 * @internal
 */
final class AssignmentBlocks
{
    private function __construct() {}

    /**
     * @param list<Node> $containers nodes whose children are statements or class members
     *
     * @return list<list<array{varEnd: int, length: int, found: int, gap: Span, operator: Span}>>
     */
    public static function of(SourceFile $file, array $containers): array
    {
        $blocks = [];
        foreach ($containers as $container) {
            $block = [];
            $last = null;
            foreach ($file->getChildren($container) as $child) {
                $assignment = AssignmentStatement::of($file, $child);
                $lines = [LineIndex::of($file, $child->span->start), LineIndex::of($file, $child->span->end - 1)];
                if ($assignment === null || $last !== null && ($lines[0] - $last) > 1) {
                    if ($block !== []) {
                        $blocks[] = $block;
                    }

                    $block = [];
                }

                if ($assignment !== null) {
                    $block[] = $assignment;
                    if (StatementGaps::splits($file, $child)) {
                        $blocks[] = $block;
                        $block = [];
                    }
                }

                $last = $lines[1];
            }

            if ($block !== []) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }
}
