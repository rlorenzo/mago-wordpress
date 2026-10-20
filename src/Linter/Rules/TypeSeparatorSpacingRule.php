<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\SpacesFixer;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function count;
use function in_array;
use function intdiv;
use function strlen;

/**
 * Ports `Universal.Operators.TypeSeparatorSpacing` (`UnionTypeSpacesBefore/After`,
 * `IntersectionTypeSpacesBefore/After`, `DNFOpenSpacesBefore/After`, `DNFCloseSpacesBefore/After`):
 * no space around the `|` and `&` of a type, and around the parentheses of a DNF type, except one
 * space before a DNF `(` that starts a type and after a `)` that ends it. The type separators
 * are found from the syntax tree, their neighbours from the shared token stream.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class TypeSeparatorSpacingRule implements Rule
{
    private const SNIFF = 'Universal.Operators.TypeSeparatorSpacing';

    private const MODIFIERS = [
        'T_PUBLIC' => true,
        'T_PUBLIC_SET' => true,
        'T_PROTECTED' => true,
        'T_PROTECTED_SET' => true,
        'T_PRIVATE' => true,
        'T_PRIVATE_SET' => true,
        'T_STATIC' => true,
        'T_VAR' => true,
        'T_READONLY' => true,
        'T_FINAL' => true,
        'T_ABSTRACT' => true,
    ];

    private const HINTS = [
        NodeKind::Hint,
        NodeKind::UnionHint,
        NodeKind::IntersectionHint,
        NodeKind::ParenthesizedHint,
        NodeKind::NullableHint,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/type-separator-spacing',
            name: 'Type separator spacing',
            description: 'Forbids space around the `|` and `&` of union and intersection types and inside the parentheses of DNF types.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $nodes = NodeIndex::ofKinds(
            $file,
            $context->node,
            [NodeKind::UnionHint, NodeKind::IntersectionHint, NodeKind::ParenthesizedHint],
        );
        if ($nodes === []) {
            return;
        }

        $tokens = Spacing::tokens($context);
        foreach ($nodes as $node) {
            if (self::inCatch($file, $node)) {
                continue; // phpcs leaves the `|` of a multi-catch a bitwise operator
            }

            if ($node->kind === NodeKind::ParenthesizedHint) {
                $open = self::at($tokens, $node->span->start);
                $close = $open === null ? null : $tokens[$open]->closer;
                if ($open !== null && $close !== null) {
                    $this->separator($context, $tokens, $open, 'DNF parenthesis open', 'DNFOpen');
                    $this->separator($context, $tokens, $close, 'DNF parenthesis close', 'DNFClose');
                }

                continue;
            }

            $children = $file->getChildren($node);
            $left = $children[0] ?? null;
            if ($left === null) {
                continue;
            }

            $index = self::at($tokens, $left->span->end);
            $operator = $index === null ? null : self::firstNonEmpty($tokens, $index);
            if ($operator === null) {
                continue;
            }

            [$type, $code] = $node->kind === NodeKind::UnionHint
                ? ['union', 'UnionType']
                : ['intersection', 'IntersectionType'];
            $this->separator($context, $tokens, $operator, $type, $code);
        }
    }

    private static function inCatch(SourceFile $file, Node $node): bool
    {
        $parent = $node->parentId === null ? null : $file->getNode($node->parentId);
        while ($parent !== null && in_array($parent->kind, self::HINTS, strict: true)) {
            $parent = $parent->parentId === null ? null : $file->getNode($parent->parentId);
        }

        return $parent !== null && $parent->kind === NodeKind::TryCatchClause;
    }

    /** @param list<PhpcsToken> $tokens */
    private function separator(LintContext $context, array $tokens, int $at, string $type, string $code): void
    {
        $token = $tokens[$at];
        $prev = PhpcsTokenStream::previous($tokens, $at);
        $next = PhpcsTokenStream::next($tokens, $at);
        if ($prev === null || $next === null) {
            return;
        }

        $open = $token->code === 'T_OPEN_PARENTHESIS';
        $expected = 0;
        $skip = false;
        $before = $tokens[$prev];
        if ($open) {
            if ($before->code === 'T_COLON' || $before->code === 'T_CONST' || isset(self::MODIFIERS[$before->code])) {
                $expected = 1; // start of a return, property or constant type
            }

            if ($before->code === 'T_OPEN_PARENTHESIS' || $before->code === 'T_COMMA') {
                // Start of a parameter type: any indent when it starts a line.
                $skip = $before->line !== $token->line;
                $expected = 1;
            }
        }

        if (
            $before->code === 'T_BITWISE_OR'
            || $before->code === 'T_BITWISE_AND'
            || $before->code === 'T_CLOSE_PARENTHESIS' && !$open && $token->code !== 'T_CLOSE_PARENTHESIS'
        ) {
            $skip = true; // two adjacent separators: the first reports the gap
        }

        if (!$skip) {
            SpacesFixer::check(
                $context,
                $this->report,
                $before->pos + strlen($before->text),
                $token->pos,
                $expected,
                'Expected %1$s before the ' . $type . ' type separator. Found: %2$s',
                self::SNIFF . '.' . $code . 'SpacesBefore',
                Spacing::span($token),
            );
        }

        $after = $tokens[$next];
        $expected = 0;
        if (
            $token->code === 'T_CLOSE_PARENTHESIS'
            && ($after->code === 'T_OPEN_CURLY_BRACKET' || $after->code === 'T_VARIABLE' || $after->code === 'T_STRING')
        ) {
            $expected = 1; // the end of a return, parameter, property or constant type
        }

        SpacesFixer::check(
            $context,
            $this->report,
            $token->pos + strlen($token->text),
            $after->pos,
            $expected,
            'Expected %1$s after the ' . $type . ' type separator. Found: %2$s',
            self::SNIFF . '.' . $code . 'SpacesAfter',
            Spacing::span($token),
        );
    }

    /**
     * The index of the token starting at or after $pos.
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function at(array $tokens, int $pos): ?int
    {
        $low = 0;
        $high = count($tokens);
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($tokens[$mid]->pos < $pos) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low < count($tokens) ? $low : null;
    }

    /**
     * @param list<PhpcsToken> $tokens
     */
    private static function firstNonEmpty(array $tokens, int $from): ?int
    {
        for ($k = $from, $count = count($tokens); $k < $count; $k++) {
            if (!Spacing::isEmpty($tokens[$k])) {
                return $k;
            }
        }

        return null;
    }
}
