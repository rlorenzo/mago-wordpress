<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;

use function array_filter;
use function array_values;
use function count;
use function in_array;
use function str_ends_with;

/**
 * Ports `WordPress.CodeAnalysis.AssignmentInTernaryCondition`.
 *
 * WPCS only checks a ternary it can bound with parentheses: either its
 * condition is itself parenthesized, or the whole ternary, together with any
 * assignments it is the value of, fills a pair of parentheses, as in
 * `$mode = ( $a = 'on' ? 'on' : 'off' );`. In the second shape everything
 * between the opening parenthesis and the `?` is scanned. The round
 * parentheses of a single-argument call or of a control structure's
 * condition count as well. Each qualifying assignment to a variable, array
 * element or property is reported once.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class AssignmentInTernaryConditionRule implements Rule
{
    private const SNIFF = 'WordPress.CodeAnalysis.AssignmentInTernaryCondition';

    private const ASSIGNABLE_KINDS = [
        NodeKind::Variable,
        NodeKind::ArrayAccess,
        NodeKind::ArrayAppend,
        NodeKind::PropertyAccess,
        NodeKind::NullSafePropertyAccess,
        NodeKind::StaticPropertyAccess,
    ];

    private const PARENTHESIZED_CONDITION_KINDS = [
        NodeKind::Parenthesized,
        NodeKind::If,
        NodeKind::IfStatementBodyElseIfClause,
        NodeKind::IfColonDelimitedBodyElseIfClause,
        NodeKind::While,
        NodeKind::DoWhile,
        NodeKind::Switch,
        NodeKind::Match,
    ];

    /**
     * Ancestors crossed between a ternary and its enclosing parentheses.
     */
    private const ENCLOSED_SPAN_KINDS = [
        NodeKind::Expression,
        NodeKind::Assignment,
        NodeKind::Binary,
        NodeKind::UnaryPrefix,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/assignment-in-ternary-condition',
            name: 'Assignment in ternary condition',
            description: 'Detects a variable assignment inside a parenthesized ternary condition, which is usually meant to be a comparison.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // The program is the target so that every ternary's ancestors are in
        // the snapshot; the enclosing parentheses decide whether it is checked.
        foreach (NodeIndex::ofKind($context->file, $context->node, NodeKind::Conditional) as $ternary) {
            $this->lintTernary($context, $ternary);
        }
    }

    private function lintTernary(LintContext $context, Node $ternary): void
    {
        $file = $context->file;
        $children = $file->getChildren($ternary);
        $condition = Values::unwrap($file, $children[0] ?? $ternary);

        foreach ($this->conditionAssignments($file, $ternary, $condition) as $assignment) {
            $left = Values::unwrap($file, $file->getChildren($assignment)[0] ?? $assignment);
            if (!in_array($left->kind, self::ASSIGNABLE_KINDS, strict: true)) {
                // A call or other non-assignable expression on the left is a fatal
                // error, not a comparison-vs-assignment mistake; WPCS ignores it too.
                continue;
            }

            $this->report->issue(
                $context,
                Issue::new(
                    'Variable assignment found within a condition. Did you mean to do a comparison?',
                    $assignment->span,
                )->withHelp('Use a comparison operator (e.g. `==`), or move the assignment outside the ternary.'),
                [self::SNIFF . '.FoundInTernaryCondition'],
            );
        }
    }

    /**
     * @return list<Node>
     */
    private function conditionAssignments(SourceFile $file, Node $ternary, Node $condition): array
    {
        if ($condition->kind === NodeKind::Parenthesized) {
            $inner = $file->getChildren($condition)[0] ?? null;

            return $inner === null ? [] : $this->assignments($file, Values::unwrap($file, $inner));
        }

        if (str_ends_with($file->getText($condition), ')')) {
            // WPCS scans only the parentheses that close the condition, such as a
            // call's arguments; this port leaves those unchecked.
            return [];
        }

        return $this->enclosedAssignments($file, $ternary, $condition->span->end);
    }

    /**
     * The assignments before the `?` when the ternary, together with the
     * assignments it is the value of, is the whole content of a pair of
     * parentheses; otherwise none, because WPCS cannot find where the
     * condition starts.
     *
     * WPCS scans every token between the opening parenthesis and the `?`, so
     * the walk also crosses binary and unary-prefix operators (`&&`, `or`,
     * `!`, `==`, `+`, ...) that join an assignment to the rest of that span.
     *
     * @return list<Node>
     */
    private function enclosedAssignments(SourceFile $file, Node $ternary, int $conditionEnd): array
    {
        $top = $ternary;
        $parent = $file->getParent($top);
        while ($parent !== null && in_array($parent->kind, self::ENCLOSED_SPAN_KINDS, strict: true)) {
            $top = $parent;
            $parent = $file->getParent($top);
        }

        if ($parent === null || !$this->isEnclosedByParentheses($file, $parent)) {
            return [];
        }

        return array_values(array_filter(
            $this->assignments($file, $top),
            static fn(Node $assignment): bool => $assignment->span->start < $conditionEnd,
        ));
    }

    /**
     * Whether an expression directly under this node is alone inside round
     * parentheses. A control structure's only direct expression child is its
     * condition.
     */
    private function isEnclosedByParentheses(SourceFile $file, Node $parent): bool
    {
        if (in_array($parent->kind, self::PARENTHESIZED_CONDITION_KINDS, strict: true)) {
            return true;
        }

        if ($parent->kind !== NodeKind::PositionalArgument && $parent->kind !== NodeKind::NamedArgument) {
            return false;
        }

        $argument = $file->getParent($parent);
        $list = $argument === null ? null : $file->getParent($argument);

        return $list !== null && $list->kind === NodeKind::ArgumentList && count($file->getChildren($list)) === 1;
    }

    /**
     * Every assignment in the subtree, including the root itself, so a
     * chained assignment such as `$a = $b = 'on'` yields both.
     *
     * @return list<Node>
     */
    private function assignments(SourceFile $file, Node $node): array
    {
        $found = $node->kind === NodeKind::Assignment ? [$node] : [];

        foreach ($file->getDescendants($node, NodeKind::Assignment) as $descendant) {
            $found[] = $descendant;
        }

        return $found;
    }
}
