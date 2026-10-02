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

use function array_key_last;
use function in_array;
use function strtolower;
use function strtoupper;
use function ucfirst;

/**
 * Ports `Generic.CodeAnalysis.EmptyStatement`: an `if`, `elseif`, `else`, loop, `try`,
 * `catch`, `finally`, `switch` or `match` whose body holds nothing but whitespace and
 * comments. Braced and colon-delimited bodies count; an unbraced body (`if ($a);`) has no
 * scope to the sniff. Reported at the keyword that owns the body.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class EmptyStatementRule implements Rule
{
    private const COLON_BODIES = [
        NodeKind::ForColonDelimitedBody,
        NodeKind::ForeachColonDelimitedBody,
        NodeKind::WhileColonDelimitedBody,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/empty-statement',
            name: 'Empty statement',
            description: 'Reports a control structure (`if`, `else`, a loop, `try`/`catch`, `switch`, `match`) with an empty body.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [
                NodeKind::If,
                NodeKind::For,
                NodeKind::Foreach,
                NodeKind::While,
                NodeKind::DoWhile,
                NodeKind::Try,
                NodeKind::Switch,
                NodeKind::Match,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $node = $context->node;
        $keyword = self::keyword($file, $node);
        foreach ($file->getChildren($node) as $child) {
            match ($child->kind) {
                NodeKind::IfBody => $this->ifBody($context, $keyword, $file->getChildren($child)[0] ?? null),
                NodeKind::ForBody,
                NodeKind::ForeachBody,
                NodeKind::WhileBody,
                NodeKind::Statement,
                NodeKind::Block,
                    => $this->body($context, $keyword, $child),
                NodeKind::TryCatchClause, NodeKind::TryFinallyClause => $this->body(
                    $context,
                    self::keyword($file, $child),
                    self::lastChild($file, $child),
                ),
                NodeKind::SwitchBody => $this->emptyIf(
                    $context,
                    $keyword,
                    self::hasNo(
                        $file,
                        $file->getChildren($child)[0] ?? $child,
                        [
                            NodeKind::SwitchCase,
                        ],
                    ),
                ),
                default => null,
            };
        }

        if ($node->kind === NodeKind::Match) {
            $this->emptyIf($context, $keyword, self::hasNo($file, $node, [NodeKind::MatchArm]));
        }
    }

    private function ifBody(LintContext $context, ?Node $keyword, ?Node $body): void
    {
        $file = $context->file;
        if ($body === null) {
            return;
        }

        if ($body->kind === NodeKind::IfColonDelimitedBody) {
            $this->emptyIf($context, $keyword, self::hasNo($file, $body, [NodeKind::Statement]));
        }

        foreach ($file->getChildren($body) as $index => $child) {
            match ($child->kind) {
                NodeKind::Statement => $index === 0 && $body->kind === NodeKind::IfStatementBody
                    ? $this->body($context, $keyword, $child)
                    : null,
                NodeKind::IfStatementBodyElseIfClause, NodeKind::IfStatementBodyElseClause => $this->body(
                    $context,
                    self::keyword($file, $child),
                    self::lastChild($file, $child),
                ),
                NodeKind::IfColonDelimitedBodyElseIfClause, NodeKind::IfColonDelimitedBodyElseClause => $this->emptyIf(
                    $context,
                    self::keyword($file, $child),
                    self::hasNo($file, $child, [NodeKind::Statement]),
                ),
                default => null,
            };
        }
    }

    /** A loop body, a statement or a block: empty when it is a block, or colon body, with no statements. */
    private function body(LintContext $context, ?Node $keyword, ?Node $body): void
    {
        $file = $context->file;
        while (
            $body !== null
            && in_array(
                $body->kind,
                [
                    NodeKind::ForBody,
                    NodeKind::ForeachBody,
                    NodeKind::WhileBody,
                    NodeKind::Statement,
                ],
                strict: true,
            )
        ) {
            $body = $file->getChildren($body)[0] ?? null;
        }

        if ($body === null) {
            return;
        }

        if ($body->kind === NodeKind::Block) {
            $this->emptyIf($context, $keyword, $file->getChildren($body) === []);
        } elseif (in_array($body->kind, self::COLON_BODIES, strict: true)) {
            $this->emptyIf($context, $keyword, self::hasNo($file, $body, [NodeKind::Statement]));
        }
    }

    private function emptyIf(LintContext $context, ?Node $keyword, bool $empty): void
    {
        if (!$empty || $keyword === null) {
            return;
        }

        $name = strtoupper($context->file->getText($keyword));
        $this->report->issue(
            $context,
            Issue::new("Empty {$name} statement detected", $keyword->span)->withHelp(
                'Remove the statement, or add the code it was meant to hold.',
            ),
            ['Generic.CodeAnalysis.EmptyStatement.Detected' . ucfirst(strtolower($name))],
        );
    }

    /** @param list<NodeKind> $kinds */
    private static function hasNo(SourceFile $file, Node $parent, array $kinds): bool
    {
        foreach ($file->getChildren($parent) as $child) {
            if (in_array($child->kind, $kinds, strict: true)) {
                return false;
            }
        }

        return true;
    }

    private static function keyword(SourceFile $file, Node $node): ?Node
    {
        foreach ($file->getChildren($node) as $child) {
            if ($child->kind === NodeKind::Keyword) {
                return $child;
            }
        }

        return null;
    }

    private static function lastChild(SourceFile $file, Node $node): ?Node
    {
        $children = $file->getChildren($node);

        return $children[array_key_last($children) ?? 0] ?? null;
    }
}
