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
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Values;

use function in_array;

/**
 * Ports `WordPress.CodeAnalysis.AssignmentInTernaryCondition`.
 *
 * WPCS only flags an assignment when the ternary's condition is wrapped in
 * parentheses, using a token-adjacency scan that can double-count an
 * assignment shared between two ternaries in the same paren group (its own
 * fixture has lines with 2 or 3 reports from a single assignment). This
 * port checks a simpler, still faithful rule: does the ternary's own
 * condition unwrap to a `Parenthesized` node, and does that node's subtree
 * contain an assignment to a variable, array element or property. Each
 * qualifying assignment is reported once.
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

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/assignment-in-ternary-condition',
            name: 'Assignment in ternary condition',
            description: 'Detects a variable assignment inside a parenthesized ternary condition, which is usually meant to be a comparison.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Conditional],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        $condition = Values::unwrap($file, $children[0] ?? $context->node);

        if ($condition->kind !== NodeKind::Parenthesized) {
            // WPCS itself does not check a ternary condition without parentheses.
            return;
        }

        $inner = $file->getChildren($condition)[0] ?? null;
        if ($inner === null) {
            return;
        }

        foreach ($this->assignments($file, Values::unwrap($file, $inner)) as $assignment) {
            $left = Values::unwrap($file, $file->getChildren($assignment)[0] ?? $assignment);
            if (!in_array($left->kind, self::ASSIGNABLE_KINDS, strict: true)) {
                // A call or other non-assignable expression on the left is a fatal
                // error, not a comparison-vs-assignment mistake; WPCS ignores it too.
                continue;
            }

            Report::issue(
                $context,
                Issue::new(
                    'Variable assignment found within a condition. Did you mean to do a comparison?',
                    $assignment->span,
                )->withHelp('Use a comparison operator (e.g. `==`), or move the assignment outside the ternary.'),
                [self::SNIFF],
            );
        }
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
