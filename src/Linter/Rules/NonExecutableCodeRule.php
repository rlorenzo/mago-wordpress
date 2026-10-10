<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\FileCache;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_filter;
use function array_key_last;
use function array_search;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function in_array;
use function rtrim;
use function strlen;
use function strtolower;
use function substr_count;
use function trim;

/**
 * Ports `Squiz.PHP.NonExecutableCode`: code after a `return`, `break`, `continue`, `throw`,
 * `exit`/`die` or `goto` statement in the same block (or `case`), one warning per line as the
 * sniff reports, skipping nested function, closure and class bodies; and a bare `return;` as
 * the last statement of a function or closure (`ReturnNotRequired`). A `throw` or `exit` inside
 * an expression (`$a ?? throw ...`, `f() or exit`) and an unbraced `if ($a) return;` are not
 * statements of a block, so they never start a report, as in the sniff.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class NonExecutableCodeRule implements Rule
{
    private const SNIFF = 'Squiz.PHP.NonExecutableCode';

    /** Statement holders that take a single, unbraced statement: no block to be unreachable in. */
    private const SINGLE = [
        NodeKind::IfStatementBody,
        NodeKind::IfStatementBodyElseIfClause,
        NodeKind::IfStatementBodyElseClause,
        NodeKind::ForBody,
        NodeKind::ForeachBody,
        NodeKind::WhileBody,
        NodeKind::DoWhile,
        NodeKind::DeclareBody,
    ];

    /** Bodies the sniff jumps over: their code runs when called, not here. */
    private const SKIPPED = [
        NodeKind::Function,
        NodeKind::Closure,
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    private const IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG];

    private const BRACKETS = ['(', ')', '[', ']', '{', '}', ';'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/non-executable-code',
            name: 'Non-executable code',
            description: 'Reports code after a `return`, `break`, `continue`, `throw`, `exit` or `goto` in the same block, and a bare `return;` that ends a function.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach (NodeIndex::ofKinds($file, $context->node, [
            NodeKind::Return,
            NodeKind::Break,
            NodeKind::Continue,
            NodeKind::Goto,
            NodeKind::Throw,
            NodeKind::ExitConstruct,
            NodeKind::DieConstruct,
        ]) as $terminal) {
            $this->check($context, $terminal);
        }
    }

    private function check(LintContext $context, Node $terminal): void
    {
        $file = $context->file;
        $statement = self::statement($file, $terminal);
        $list = $statement === null ? null : $file->getParent($statement);
        if ($statement === null || $list === null || in_array($list->kind, self::SINGLE, strict: true)) {
            return;
        }

        $siblings = $file->getChildren($list);
        $at = array_search($statement, $siblings, strict: true);
        $following = $at === false ? [] : array_slice($siblings, $at + 1);
        $topLevel = in_array($list->kind, [NodeKind::Program, NodeKind::NamespaceImplicitBody], strict: true);
        if ($terminal->kind === NodeKind::Break && $topLevel) {
            return;
        }

        if ($terminal->kind === NodeKind::Return && $this->returnNotRequired($context, $terminal, $list, $following)) {
            return;
        }

        $following = array_values(array_filter(
            $following,
            static fn(Node $node): bool => $node->kind === NodeKind::Statement,
        ));
        if ($following === []) {
            return;
        }

        $end = $topLevel ? strlen($file->contents) : $following[array_key_last($following)]->span->end;
        $this->unreachable($context, $terminal, $statement, $end);
    }

    /** The statement $terminal starts, or NULL when it sits inside an expression. */
    private static function statement(SourceFile $file, Node $terminal): ?Node
    {
        $node = $terminal;
        while (true) {
            $parent = $file->getParent($node);
            if ($parent === null) {
                return null;
            }

            if ($parent->kind === NodeKind::Statement) {
                return $parent;
            }

            // The sniff lets only `xor` through of the boolean operators before a `throw`.
            $children = $file->getChildren($parent);
            $xor =
                $parent->kind === NodeKind::Binary
                && $terminal->kind === NodeKind::Throw
                && strtolower($file->getText($children[1] ?? $parent)) === 'xor'
                && ($children[2] ?? null) === $node;
            if (
                !$xor
                && (
                    !in_array(
                        $parent->kind,
                        [NodeKind::Expression, NodeKind::ExpressionStatement, NodeKind::Construct],
                        strict: true,
                    )
                    || $children[0] !== $node
                )
            ) {
                return null;
            }

            $node = $parent;
        }
    }

    /** @param list<Node> $following */
    private function returnNotRequired(LintContext $context, Node $return, Node $list, array $following): bool
    {
        $file = $context->file;
        $owner = $file->getParent($list);
        if (
            $list->kind !== NodeKind::Block
            || $owner === null
            || !in_array($owner->kind, [NodeKind::MethodBody, NodeKind::Function, NodeKind::Closure], strict: true)
            || $following !== []
            || $file->getFirstDescendant($return, NodeKind::Expression) !== null
        ) {
            return false;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Empty return statement not required here',
                new Span($return->span->start, $return->span->start + 6),
            ),
            [self::SNIFF . '.ReturnNotRequired'],
        );

        return true;
    }

    private function unreachable(LintContext $context, Node $terminal, Node $statement, int $end): void
    {
        $file = $context->file;
        $from = $statement->span->end;

        $skips = [];
        foreach (self::SKIPPED as $kind) {
            foreach ($file->getDescendants($file->getParent($statement) ?? $statement, $kind) as $node) {
                if ($node->span->start >= $from) {
                    $skips[] = $node->span;
                }
            }
        }

        $type = self::type($terminal);
        $line = self::line($file, $terminal->span->start);
        $message = "Code after the {$type} statement on line {$line} cannot be executed";
        $lastLine = self::line($file, $from - 1);
        $tokens = self::tokens($file);
        for ($index = self::firstAfter($tokens, $from);; ++$index) {
            $token = $tokens[$index] ?? null;
            if ($token === null || $token->pos >= $end) {
                break;
            }
            if (
                in_array($token->id, self::IGNORED, strict: true)
                || in_array($token->text, self::BRACKETS, strict: true)
            ) {
                continue;
            }

            // phpcs splits inline HTML into one token per line.
            $at = 0;
            foreach ($token->id === T_INLINE_HTML ? explode("\n", $token->text) : [$token->text] as $piece) {
                $start = $at;
                $at += strlen($piece) + 1;
                $offset = $token->pos + $start;
                if (trim($piece) === '' || self::skipped($skips, $offset)) {
                    continue;
                }

                $lastLine = $this->report($context, $offset, $piece, $lastLine, $message);
            }
        }
    }

    /**
     * The file's tokens, produced once for all of its terminal statements.
     *
     * @return list<PhpToken>
     */
    private static function tokens(SourceFile $file): array
    {
        return FileCache::remember(
            $file,
            'non-executable-code-tokens',
            static fn(): array => PhpToken::tokenize($file->contents),
        );
    }

    /**
     * Index of the first token that starts at or after $offset.
     *
     * @param list<PhpToken> $tokens
     */
    private static function firstAfter(array $tokens, int $offset): int
    {
        $low = 0;
        $high = count($tokens);
        while ($low < $high) {
            $middle = ($low + $high) >> 1;
            $before = $tokens[$middle]->pos < $offset;
            $low = $before ? $middle + 1 : $low;
            $high = $before ? $high : $middle;
        }

        return $low;
    }

    /** Reports the piece when it starts a line after $lastLine; returns the new last line. */
    private function report(LintContext $context, int $offset, string $piece, int $lastLine, string $message): int
    {
        $pieceLine = self::line($context->file, $offset);
        if ($pieceLine <= $lastLine) {
            return $lastLine;
        }

        $this->report->issue(
            $context,
            Issue::new($message, new Span($offset, $offset + strlen(rtrim($piece)))),
            [self::SNIFF . '.Unreachable'],
        );

        return $pieceLine;
    }

    /** @param list<Span> $skips */
    private static function skipped(array $skips, int $offset): bool
    {
        foreach ($skips as $span) {
            if ($offset >= $span->start && $offset < $span->end) {
                return true;
            }
        }

        return false;
    }

    private static function type(Node $terminal): string
    {
        return match ($terminal->kind) {
            NodeKind::Return => 'RETURN',
            NodeKind::Break => 'BREAK',
            NodeKind::Continue => 'CONTINUE',
            NodeKind::Goto => 'GOTO',
            NodeKind::Throw => 'THROW',
            default => 'EXIT',
        };
    }

    private static function line(SourceFile $file, int $offset): int
    {
        return substr_count($file->contents, needle: "\n", offset: 0, length: $offset) + 1;
    }
}
