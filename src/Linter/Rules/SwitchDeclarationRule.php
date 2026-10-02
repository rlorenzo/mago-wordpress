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

use function array_filter;
use function array_values;
use function count;
use function end;
use function preg_match;
use function rtrim;
use function substr;
use function trim;

/**
 * Ports the `TerminatingComment` part of `PSR2.ControlStructures.SwitchDeclaration`: a
 * non-empty `case` that falls through to the next `case` or `default` without a comment
 * saying so. As in the sniff, a body ending in `return`, `break`, `continue`, `throw`,
 * `exit` or `goto`, or in an `if`/`else`, `try` or `switch` whose every branch does, is
 * not a fall-through. The sniff's other codes are layout `mago format` produces.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class SwitchDeclarationRule implements Rule
{
    private const CODE = 'PSR2.ControlStructures.SwitchDeclaration.TerminatingComment';

    /** A statement that starts with one of these ends the case (phpcs's case scope closers, and `goto`). */
    private const TERMINATOR = '/^(?:return|break|continue|throw|exit|die|goto)\b/i';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/switch-declaration',
            name: 'Switch declaration',
            description: 'Reports a non-empty `case` that falls through to the next one without a comment.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::SwitchBraceDelimitedBody, NodeKind::SwitchColonDelimitedBody],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $cases = self::cases($file, $context->node);
        foreach ($cases as $position => $case) {
            $next = $cases[$position + 1] ?? null;
            $statements = self::statements($file, $case);
            if ($next === null || $case->kind !== NodeKind::SwitchExpressionCase || $statements === []) {
                continue;
            }

            foreach ($statements as $statement) {
                if (self::startsTerminator($file, $statement)) {
                    continue 2;
                }
            }

            $last = end($statements);
            $gap = trim(substr($file->contents, $last->span->end, $next->span->start - $last->span->end));
            if ($gap !== '' || self::terminates($file, $last)) {
                continue; // a comment before the next case, or a nested terminator
            }

            $this->report->issue(
                $context,
                Issue::new(
                    'There must be a comment when fall-through is intentional in a non-empty case body',
                    $file->getChildren($case)[0]->span,
                    'falls through',
                )->withHelp('End the case with `break;`, or add a `// Fall through.` comment before the next case.'),
                [self::CODE],
            );
        }
    }

    /**
     * The `case`/`default` nodes of a switch body.
     *
     * @return list<Node>
     */
    private static function cases(SourceFile $file, Node $body): array
    {
        $cases = [];
        foreach ($file->getChildren($body) as $child) {
            if ($child->kind === NodeKind::SwitchCase) {
                $cases[] = $file->getChildren($child)[0];
            }
        }

        return $cases;
    }

    /**
     * @return list<Node>
     */
    private static function statements(SourceFile $file, Node $parent): array
    {
        return array_values(array_filter(
            $file->getChildren($parent),
            static fn(Node $node): bool => $node->kind === NodeKind::Statement,
        ));
    }

    private static function startsTerminator(SourceFile $file, Node $statement): bool
    {
        return (
            preg_match(self::TERMINATOR, $file->getText($statement)) === 1
            && substr(rtrim($file->getText($statement)), offset: -1) === ';'
        );
    }

    /** Whether the statement ends every path through it, as `findNestedTerminator()` decides. */
    private static function terminates(SourceFile $file, Node $statement): bool
    {
        if (self::startsTerminator($file, $statement)) {
            return true;
        }

        $inner = $file->getChildren($statement)[0] ?? null;
        if ($inner === null) {
            return false;
        }

        return match ($inner->kind) {
            NodeKind::If => self::ifTerminates($file, $inner),
            NodeKind::Try => self::tryTerminates($file, $inner),
            NodeKind::Switch => self::switchTerminates($file, $inner),
            default => false,
        };
    }

    private static function blockTerminates(SourceFile $file, ?Node $block): bool
    {
        if ($block === null || $block->kind !== NodeKind::Block) {
            return false;
        }

        $statements = self::statements($file, $block);

        return $statements !== [] && self::terminates($file, $statements[count($statements) - 1]);
    }

    /** The block of a clause whose last child is a Statement holding a Block. */
    private static function clauseBlock(SourceFile $file, Node $clause): ?Node
    {
        $children = $file->getChildren($clause);
        $statement = $children[count($children) - 1] ?? null;

        return $statement !== null && $statement->kind === NodeKind::Statement
            ? $file->getChildren($statement)[0] ?? null
            : $statement;
    }

    /**
     * Every branch ends, and there is an `else`. With `else if`, phpcs stops at the inner `if`,
     * so only that `if`'s branches count.
     */
    private static function ifTerminates(SourceFile $file, Node $if): bool
    {
        $body = $file->getChildren($file->getChildren($if)[2] ?? $if)[0] ?? null;
        if ($body === null || $body->kind !== NodeKind::IfStatementBody) {
            return false;
        }

        $parts = $file->getChildren($body);
        $last = $parts[count($parts) - 1] ?? null;
        if ($last === null || $last->kind !== NodeKind::IfStatementBodyElseClause) {
            return false;
        }

        $elseBody = self::clauseBlock($file, $last);
        if ($elseBody !== null && $elseBody->kind === NodeKind::If) {
            return self::ifTerminates($file, $elseBody);
        }

        foreach ($parts as $part) {
            if (!self::blockTerminates($file, self::clauseBlock($file, $part))) {
                return false;
            }
        }

        return true;
    }

    /** The `finally` ends, or the `try` and every `catch` do. */
    private static function tryTerminates(SourceFile $file, Node $try): bool
    {
        $allEnd = true;
        foreach ($file->getChildren($try) as $part) {
            $ends = match ($part->kind) {
                NodeKind::Block => self::blockTerminates($file, $part),
                NodeKind::TryCatchClause, NodeKind::TryFinallyClause => self::blockTerminates($file, self::clauseBlock(
                    $file,
                    $part,
                )),
                default => null,
            };
            if ($part->kind === NodeKind::TryFinallyClause && $ends === true) {
                return true;
            }

            if ($part->kind !== NodeKind::TryFinallyClause && $ends === false) {
                $allEnd = false;
            }
        }

        return $allEnd;
    }

    /** Every non-empty case ends, and there is a `default`. */
    private static function switchTerminates(SourceFile $file, Node $switch): bool
    {
        $body = $file->getChildren($file->getChildren($switch)[2] ?? $switch)[0] ?? null;
        $hasDefault = false;
        foreach ($body === null ? [] : self::cases($file, $body) as $case) {
            if ($case->kind === NodeKind::SwitchDefaultCase) {
                $hasDefault = true;
            }

            $statements = self::statements($file, $case);
            if ($statements !== [] && !self::terminates($file, $statements[count($statements) - 1])) {
                return false;
            }
        }

        return $hasDefault;
    }
}
