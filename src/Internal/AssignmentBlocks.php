<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\SourceFile;

use function array_filter;
use function array_key_last;
use function array_values;

/**
 * The blocks of adjacent assignments in a file, as `Generic.Formatting.MultipleStatementAlignment`
 * finds them: runs of assignment statements, constants and properties that are siblings on
 * consecutive lines.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class AssignmentBlocks
{
    /** `Tokens::$parenthesisOpeners`: the tokens whose parentheses have an owner. */
    private const OWNERS = [
        'T_ARRAY' => true,
        'T_LIST' => true,
        'T_FUNCTION' => true,
        'T_CLASS' => true,
        'T_WHILE' => true,
        'T_FOR' => true,
        'T_FOREACH' => true,
        'T_SWITCH' => true,
        'T_IF' => true,
        'T_ELSEIF' => true,
        'T_CATCH' => true,
        'T_DECLARE' => true,
        'T_MATCH' => true,
    ];

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
            $lastOperatorLine = null;
            foreach ($file->getChildren($container) as $child) {
                $items = array_values(array_filter(
                    AssignmentStatement::of($file, $child),
                    fn(array $item): bool => !self::inGroupingParentheses($file, $item['operator']->start),
                ));
                $lines = [LineIndex::of($file, $child->span->start), LineIndex::of($file, $child->span->end - 1)];
                if ($items === [] || $last !== null && ($lines[0] - $last) > 1) {
                    if ($block !== []) {
                        $blocks[] = $block;
                    }

                    $block = [];
                }

                foreach ($items as $assignment) {
                    $operatorLine = LineIndex::of($file, $assignment['operator']->start);
                    if ($operatorLine === $lastOperatorLine) {
                        // A second assignment on the line of the previous one is not aligned, and its
                        // semicolon (no assignment since the last one) ends the block.
                        if ($block !== []) {
                            $blocks[] = $block;
                        }

                        $block = [];

                        continue;
                    }

                    $lastOperatorLine = $operatorLine;
                    $block[] = $assignment;
                }

                if ($items !== [] && $block !== [] && StatementGaps::splits($file, $child)) {
                    $blocks[] = $block;
                    $block = [];
                }

                $last = $lines[1];
            }

            if ($block !== []) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Whether the sniff ignores an assignment for the parentheses around it: the innermost pair
     * opens on its line, or some pair belongs to an `array(`, control structure or other owner.
     */
    private static function inGroupingParentheses(SourceFile $file, int $operator): bool
    {
        $tokens = PhpcsTokens::of($file);
        $index = $tokens->indexAt($operator);
        $parens = $index === null ? [] : $tokens->nested($index);
        if ($parens === []) {
            return false;
        }

        if (LineIndex::of($file, $tokens->pos($parens[array_key_last($parens)])) === LineIndex::of($file, $operator)) {
            return true;
        }

        foreach ($parens as $open) {
            if (isset(self::OWNERS[$tokens->code($open - 1) ?? ''])) {
                return true;
            }
        }

        return false;
    }
}
