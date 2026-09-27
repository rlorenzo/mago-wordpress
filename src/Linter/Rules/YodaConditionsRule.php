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
use Rlorenzo\MagoWordPress\Internal\Values;

use function in_array;
use function strtolower;
use function trim;

/**
 * Ports `WordPress.PHP.YodaConditions`.
 *
 * WPCS scans tokens backward from the operator to decide whether the left
 * side is variable-like and forward from the operator to decide whether the
 * right side is variable-like, bailing out on a trailing/leading `)`. The
 * AST already separates the two operands, so this port walks each operand's
 * outermost node instead of tokens: {@see self::leftIsVariableSide()} mirrors
 * the backward scan (which token would be found first, scanning from the
 * operator towards the left), {@see self::rightIsExempt()} mirrors the
 * forward scan (cast, then `self`/`parent`/`static` `::`, then a variable).
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class YodaConditionsRule implements Rule
{
    private const COMPARISON_OPERATORS = ['==', '!=', '<>', '===', '!=='];

    private const CAST_KEYWORDS = [
        'int',
        'integer',
        'bool',
        'boolean',
        'float',
        'double',
        'real',
        'string',
        'binary',
        'array',
        'object',
        'unset',
    ];

    private const HIERARCHY_KEYWORDS = ['self', 'parent', 'static'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/yoda-conditions',
            name: 'Yoda conditions',
            description: 'Reports a comparison with a variable, array element or property on the left and a literal or constant on the right.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Binary],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        $left = $children[0] ?? null;
        $operator = $children[1] ?? null;
        $right = $children[2] ?? null;

        if ($left === null || $operator === null || $right === null) {
            return;
        }

        if (!in_array(trim($file->getText($operator)), self::COMPARISON_OPERATORS, strict: true)) {
            return;
        }

        if (!$this->leftIsVariableSide($file, $left) || $this->rightIsExempt($file, $right)) {
            return;
        }

        $context->report(Issue::new(
            'Comparison has the variable on the left and the literal or constant on the right.',
            $context->node->span,
        )->withHelp('Swap the operands so the constant or literal comes first, e.g. `true === $foo`.'));
    }

    /**
     * Whether the operand's outermost node is the kind of thing WPCS's
     * backward token scan would land on first: a variable, an array
     * element, or a property/static-property chain that bottoms out in
     * one. A call, instantiation or parenthesized group ends in `)`,
     * which WPCS treats as "not our concern" (bail, no report); a bare
     * literal or constant never reaches a variable token at all. Both
     * fall through to `false` here, since neither needs a report.
     */
    private function leftIsVariableSide(SourceFile $file, ?Node $node): bool
    {
        if ($node === null) {
            return false;
        }

        $node = Values::unwrap($file, $node);

        return match ($node->kind) {
            NodeKind::Variable, NodeKind::ArrayAccess, NodeKind::ArrayAppend, NodeKind::StaticPropertyAccess => true,
            NodeKind::PropertyAccess, NodeKind::NullSafePropertyAccess => $this->propertyChainIsVariableSide(
                $file,
                $node,
            ),
            NodeKind::ClassConstantAccess => $this->leftIsVariableSide($file, $file->getChildren($node)[0] ?? null),
            NodeKind::UnaryPrefix => $this->leftIsVariableSide($file, $file->getChildren($node)[1] ?? null),
            NodeKind::UnaryPostfix => $this->leftIsVariableSide($file, $file->getChildren($node)[0] ?? null),
            default => false,
        };
    }

    /**
     * `$obj->$prop` ends in the dynamic property's own variable token; a
     * fixed property name (`$obj->prop`) does not, so the scan continues
     * into the object expression.
     */
    private function propertyChainIsVariableSide(SourceFile $file, Node $node): bool
    {
        $children = $file->getChildren($node);
        $selector = $children[1] ?? null;
        $selectorValue = $selector !== null ? $file->getChildren($selector)[0] ?? null : null;

        if ($selectorValue !== null && $selectorValue->kind === NodeKind::Variable) {
            return true;
        }

        return $this->leftIsVariableSide($file, $children[0] ?? null);
    }

    /**
     * Whether the operand is exempt because WPCS's forward scan (one
     * optional cast, then one optional `self`/`parent`/`static` `::`)
     * lands on a variable token.
     */
    private function rightIsExempt(SourceFile $file, Node $node): bool
    {
        $node = Values::unwrap($file, $node);

        if ($node->kind === NodeKind::UnaryPrefix) {
            $children = $file->getChildren($node);
            $operator = trim($file->getText($children[0] ?? $node), characters: "() \t\n\r\0\x0B");
            if (in_array(strtolower($operator), self::CAST_KEYWORDS, strict: true)) {
                $node = $children[1] ?? $node;
            }
        }

        // WPCS looks only at the first token after the operator, so for
        // `$a === $b . '.x'` the leftmost operand of the concatenation decides.
        while ($node->kind === NodeKind::Binary) {
            $node = Values::unwrap($file, $file->getChildren($node)[0] ?? $node);
        }

        return $this->headIsVariable($file, $node);
    }

    /**
     * Whether the node's leading token, per WPCS's rules, is a variable:
     * directly, or through a `self`/`parent`/`static` `::` that always
     * leads into one (a static property). Any other kind of `X::Y` never
     * counts, even when `Y` happens to be a variable-valued property,
     * because WPCS only recognizes the hierarchy keywords, not an
     * arbitrary class name or expression.
     */
    private function headIsVariable(SourceFile $file, ?Node $node): bool
    {
        if ($node === null) {
            return false;
        }

        $node = Values::unwrap($file, $node);

        return match ($node->kind) {
            NodeKind::Variable => true,
            NodeKind::StaticPropertyAccess,
            NodeKind::StaticMethodCall,
            NodeKind::ClassConstantAccess,
                => $this->staticHeadIsVariable($file, $node),
            NodeKind::ArrayAccess,
            NodeKind::PropertyAccess,
            NodeKind::NullSafePropertyAccess,
            NodeKind::FunctionCall,
            NodeKind::MethodCall,
            NodeKind::NullSafeMethodCall,
                => $this->headIsVariable($file, $file->getChildren($node)[0] ?? null),
            default => false,
        };
    }

    private function staticHeadIsVariable(SourceFile $file, Node $node): bool
    {
        $children = $file->getChildren($node);
        $classPart = $children[0] ?? null;
        if ($classPart === null) {
            return false;
        }

        $classPart = Values::unwrap($file, $classPart);

        if (
            $classPart->kind === NodeKind::Keyword
            && in_array(strtolower($file->getText($classPart)), self::HIERARCHY_KEYWORDS, strict: true)
        ) {
            return Values::unwrap($file, $children[1] ?? $node)->kind === NodeKind::Variable;
        }

        return $this->headIsVariable($file, $classPart);
    }
}
