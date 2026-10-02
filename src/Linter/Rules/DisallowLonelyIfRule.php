<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_slice;
use function count;
use function preg_match;
use function rtrim;
use function strlen;
use function strrpos;
use function strspn;
use function substr;
use function trim;

/**
 * Ports `Universal.ControlStructures.DisallowLonelyIf`: an `else` block whose only statement
 * is an `if` (with its own `elseif`/`else` chain), which reads better as `elseif`. Like the
 * sniff, an unbraced `else` or inner `if` is skipped. The fix covers the brace form, as
 * phpcbf does, and leaves code with comments around the inner `if` alone; the
 * alternative-syntax form (`else: if (...): ... endif; endif;`) is reported without a fix.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class DisallowLonelyIfRule implements Rule
{
    private const CODE = 'Universal.ControlStructures.DisallowLonelyIf.Found';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-lonely-if',
            name: 'Disallow lonely if',
            description: 'Reports an `else` block whose only statement is an `if`; use `elseif`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::IfStatementBodyElseClause, NodeKind::IfColonDelimitedBodyElseClause],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        $keyword = $children[0] ?? null;
        if ($keyword === null) {
            return;
        }

        $block = null;
        if ($context->node->kind === NodeKind::IfStatementBodyElseClause) {
            $block = self::child($file, $children[1] ?? null, NodeKind::Block);
            $statements = $block === null ? [] : $file->getChildren($block);
        } else {
            $statements = array_slice($children, offset: 1);
        }

        $inner = count($statements) === 1 ? self::child($file, $statements[0], NodeKind::If) : null;
        if ($inner === null || !self::hasScopes($file, $inner)) {
            return;
        }

        $issue = Issue::new(
            'If control structure block found as the only statement within an "else" block. Use elseif instead.',
            $keyword->span,
            'lonely if',
        )->withHelp('Merge the `else` and the `if` into `elseif`.');
        foreach ($block === null ? [] : self::fix($file, $keyword, $block, $inner) as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::CODE]);
    }

    private static function child(SourceFile $file, ?Node $node, NodeKind $kind): ?Node
    {
        $child = $node === null ? null : $file->getChildren($node)[0] ?? null;

        return $child?->kind === $kind ? $child : null;
    }

    /** Whether the `if`, and every `elseif`/`else` after it, has braces or a colon body. */
    private static function hasScopes(SourceFile $file, Node $if): bool
    {
        $body = $file->getChildren($file->getChildren($if)[2] ?? $if)[0] ?? null;
        if ($body === null || $body->kind !== NodeKind::IfStatementBody) {
            return $body?->kind === NodeKind::IfColonDelimitedBody;
        }

        foreach ($file->getChildren($body) as $part) {
            $parts = $file->getChildren($part);
            $statement = $part->kind === NodeKind::Statement ? $part : $parts[count($parts) - 1] ?? null;
            $else = self::child($file, $statement, NodeKind::If);
            $braced = self::child($file, $statement, NodeKind::Block) !== null;
            if (!$braced && ($part->kind !== NodeKind::IfStatementBodyElseClause || $else === null)) {
                return false;
            }

            if ($else !== null && !self::hasScopes($file, $else)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Turns `else { if (...) { ... } }` into `elseif (...) { ... }` as phpcbf does: the inner
     * `if` line and its closing brace go, the outer braces stay.
     *
     * @return list<TextEdit>
     * @mago-expect lint:halstead
     */
    private static function fix(SourceFile $file, Node $else, Node $block, Node $inner): array
    {
        $contents = $file->contents;
        $ifBody = self::child($file, $file->getChildren($inner)[2] ?? null, NodeKind::IfStatementBody);
        $innerOpen = self::child(
            $file,
            $ifBody === null ? null : $file->getChildren($ifBody)[0] ?? null,
            NodeKind::Block,
        );
        $ifKeyword = $file->getChildren($inner)[0] ?? null;
        $between = static fn(int $from, int $to): string => substr($contents, $from, $to - $from);
        if (
            $innerOpen === null
            || $ifKeyword === null
            || trim($between($else->span->end, $block->span->start)) !== ''
            || trim($between($block->span->start + 1, $inner->span->start)) !== ''
            || trim($between($inner->span->end, $block->span->end - 1)) !== ''
            || preg_match('~/\*|//|#~', $between($ifKeyword->span->end, $innerOpen->span->start)) === 1
        ) {
            return []; // a comment the fix would move or drop
        }

        $condition = rtrim($between($ifKeyword->span->end, $innerOpen->span->start));

        // The inner `if` line goes whole when it has a line of its own.
        $lineStart = (int) strrpos(substr($contents, offset: 0, length: $inner->span->start), needle: "\n") + 1;
        $from = $lineStart > $block->span->start ? $lineStart : $inner->span->start;
        $to = $innerOpen->span->start + 1;
        $to += strspn($contents, characters: " \t", offset: $to);
        $newline = [];
        $to += preg_match('/\G\r?\n/', $contents, $newline, offset: $to) === 1 ? strlen($newline[0]) : 0;

        $closer = $inner->span->end - 1;
        $kept = strlen(rtrim(substr($contents, offset: 0, length: $closer)));

        return [
            TextEdit::replace(new Span($else->span->start, $block->span->start), 'elseif' . $condition . ' '),
            TextEdit::delete(new Span($from, $to)),
            TextEdit::delete(new Span($kept, $closer + 1)),
        ];
    }
}
