<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_filter;
use function array_key_last;
use function array_push;
use function array_slice;
use function array_values;
use function end;

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

    private const CONTROLS = ['If' => true, 'Foreach' => true, 'For' => true, 'While' => true];

    private const BODIES = ['IfBody' => true, 'ForeachBody' => true, 'ForBody' => true, 'WhileBody' => true];

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
            foreach (self::entries($file, $file->getChildren($container)) as [$node, $first, $end, $flush]) {
                $items = $node === null
                    ? []
                    : array_values(array_filter(
                        AssignmentStatement::of($file, $node),
                        fn(array $item): bool => !self::inGroupingParentheses($file, $item['operator']->start),
                    ));
                if ($flush || $node !== null && $items === [] || $last !== null && ($first - $last) > 1) {
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

                if ($node !== null && $items !== [] && $block !== [] && StatementGaps::splits($file, $node)) {
                    $blocks[] = $block;
                    $block = [];
                }

                $last = $end;
            }

            if ($block !== []) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * The statements of a container as the sniff walks them, as [statement or null, first line, last
     * line, ends the block]. phpcs gives a control structure without braces no scope, so the
     * statements in its bodies are in the same block as the ones around it, with the header and
     * `else` tokens between them (null statements) only subject to the blank-line test.
     *
     * @param list<Node> $children
     *
     * @return list<array{?Node, int, int, bool}>
     */
    private static function entries(SourceFile $file, array $children): array
    {
        $entries = [];
        foreach ($children as $child) {
            array_push($entries, ...self::expand($file, $child));
        }

        return $entries;
    }

    /** @return list<array{?Node, int, int, bool}> */
    private static function expand(SourceFile $file, Node $statement): array
    {
        $whole = [[
            $statement,
            LineIndex::of($file, $statement->span->start),
            LineIndex::of($file, $statement->span->end - 1),
            false,
        ]];
        $control = $statement->kind === NodeKind::Statement ? $file->getChildren($statement)[0] ?? null : null;
        if ($control === null || !isset(self::CONTROLS[$control->kind->value])) {
            return $whole;
        }

        $parts = $file->getChildren($control);
        $bodyNode = end($parts);
        if ($bodyNode === false || !isset(self::BODIES[$bodyNode->kind->value])) {
            return $whole; // the colon syntax
        }

        $body = $file->getChildren($bodyNode)[0] ?? null;
        if ($control->kind === NodeKind::If) {
            $clauses = $body !== null && $body->kind === NodeKind::IfStatementBody ? $file->getChildren($body) : [];
            if ($clauses === []) {
                return $whole;
            }

            // The header and statement, then each `elseif` and `else` clause with its statement.
            $sequence = [[$control, array_slice($parts, 0, -1), $clauses[0]]];
            foreach (array_slice($clauses, 1) as $clause) {
                $inner = $file->getChildren($clause);
                $sequence[] = [$clause, array_slice($inner, 0, -1), $inner[array_key_last($inner)]];
            }
        } else {
            if ($body === null || $body->kind !== NodeKind::Statement) {
                return $whole;
            }

            $sequence = [[$control, array_slice($parts, 0, -1), $body]];
        }

        $entries = [];
        foreach ($sequence as [$owner, $header, $bodyStatement]) {
            $headerEnd = $header === [] ? $owner->span->start : end($header)->span->end - 1;
            $entries[] = [
                null,
                LineIndex::of($file, $owner->span->start),
                LineIndex::of($file, $headerEnd),
                $control->kind === NodeKind::For || self::assigns($file, $header),
            ];
            $inner = $file->getChildren($bodyStatement)[0] ?? null;
            if ($bodyStatement->kind !== NodeKind::Statement || $inner === null || $inner->kind === NodeKind::Block) {
                // A body with braces is a scope of its own: the block ends.
                $entries[] = [
                    null,
                    LineIndex::of($file, $bodyStatement->span->start),
                    LineIndex::of($file, $control->span->end - 1),
                    true,
                ];

                return $entries;
            }

            array_push($entries, ...self::expand($file, $bodyStatement));
        }

        return $entries;
    }

    /** @param list<Node> $header */
    private static function assigns(SourceFile $file, array $header): bool
    {
        foreach ($header as $node) {
            if ($file->getFirstDescendant($node, NodeKind::Assignment) !== null) {
                return true;
            }
        }

        return false;
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
