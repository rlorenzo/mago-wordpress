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

use function in_array;
use function strtolower;

/**
 * Ports `Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence`: `&&`, `||`, `and`,
 * `or` and `xor` mixed in one expression without parentheses. Each operator whose
 * preceding operator at the same nesting level differs is reported, as the sniff does.
 *
 * The sniff also reports across a match arm's comma-separated conditions and into an
 * arrow function body (both marked "debatable" in its tests); this rule does not,
 * because each is a separate expression in the syntax tree.
 */
final class RequireExplicitBooleanOperatorPrecedenceRule implements Rule
{
    private const CODE = 'Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence.MissingParentheses';

    private const OPERATORS = ['&&', '||', 'and', 'or', 'xor'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/require-explicit-boolean-operator-precedence',
            name: 'Require explicit boolean operator precedence',
            description: 'Reports different boolean operators mixed in one expression without parentheses, such as `$a && $b || $c`; add parentheses to make the intended precedence explicit.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Binary],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $binary = $context->node;
        $expression = $file->getParent($binary);
        // Only the top of a chain: a boolean Binary that is not an operand of another one.
        $parent = $expression === null ? null : $file->getParent($expression);
        if (self::operator($file, $binary) === null || self::operator($file, $parent) !== null) {
            return;
        }

        $previous = null;
        foreach (self::chain($file, $binary) as $operator) {
            $text = strtolower($file->getText($operator));
            if ($previous !== null && $previous !== $text) {
                $this->report->issue(
                    $context,
                    Issue::new(
                        'Mixing different binary boolean operators within an expression without using parentheses to clarify precedence is not allowed.',
                        $operator->span,
                        "`{$text}` follows `{$previous}` without parentheses",
                    )->withHelp('Wrap the sub-expression that should bind first in parentheses.'),
                    [self::CODE],
                );
            }

            $previous = $text;
        }
    }

    /**
     * The chain's operator nodes in source order, through operands that are themselves
     * unparenthesized boolean binaries.
     *
     * @return list<Node>
     */
    private static function chain(SourceFile $file, Node $binary): array
    {
        [$left, $operator, $right] = $file->getChildren($binary);

        return [...self::operandChain($file, $left), $operator, ...self::operandChain($file, $right)];
    }

    /**
     * @return list<Node>
     */
    private static function operandChain(SourceFile $file, Node $expression): array
    {
        $operand = $file->getChildren($expression)[0] ?? null;

        return $operand === null || self::operator($file, $operand) === null ? [] : self::chain($file, $operand);
    }

    /** The lowercased operator of a boolean Binary; null for anything else. */
    private static function operator(SourceFile $file, ?Node $node): ?string
    {
        // A Binary's children are the left Expression, the BinaryOperator and the right Expression.
        $operator = $node !== null && $node->kind === NodeKind::Binary ? $file->getChildren($node)[1] ?? null : null;
        $text = $operator === null ? '' : strtolower($file->getText($operator));

        return in_array($text, self::OPERATORS, strict: true) ? $text : null;
    }
}
