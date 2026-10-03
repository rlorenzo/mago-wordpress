<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function ctype_space;
use function in_array;
use function preg_match;
use function rtrim;
use function strlen;
use function strrpos;
use function strspn;
use function substr;

/**
 * Ports `Generic.ControlStructures.InlineControlStructure`: an `if`, `elseif`, `else`,
 * `foreach`, `for`, `while` or `do` whose body is a single statement without braces
 * (`while (...);` and `for (...);` are fine). The fix adds the braces as phpcbf does; like
 * phpcbf, a body that holds another unbraced control structure is left for a second run,
 * and a body closed by a PHP close tag is not fixed.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class InlineControlStructureRule implements Rule
{
    private const CODE = 'Generic.ControlStructures.InlineControlStructure.NotAllowed';

    private const CONTROL = [
        NodeKind::If,
        NodeKind::IfStatementBodyElseIfClause,
        NodeKind::IfStatementBodyElseClause,
        NodeKind::Foreach,
        NodeKind::For,
        NodeKind::While,
        NodeKind::DoWhile,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/inline-control-structure',
            name: 'Inline control structure',
            description: 'Reports a control structure whose body is a single statement without braces.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: self::CONTROL,
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $node = $context->node;
        $body = self::inlineBody($file, $node);
        if ($body === null) {
            return;
        }

        $keyword = $file->getChildren($node)[0];
        $issue = Issue::new('Inline control structures are not allowed', $keyword->span, 'no braces')->withHelp(
            'Wrap the body in braces.',
        );
        foreach (self::fix($file, $node, $keyword, $body) as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::CODE]);
    }

    /** The unbraced body statement, or null when the body is braced, colon-delimited or allowed. */
    private static function inlineBody(SourceFile $file, Node $node): ?Node
    {
        $children = $file->getChildren($node);
        $last = $children[count($children) - 1] ?? null;
        $body = match ($node->kind) {
            NodeKind::If => self::statementBody($file, self::firstChild($file, $children[2] ?? null)),
            NodeKind::DoWhile => $children[1] ?? null,
            NodeKind::IfStatementBodyElseIfClause, NodeKind::IfStatementBodyElseClause => $last,
            default => self::firstChild($file, $last),
        };
        if ($body?->kind !== NodeKind::Statement) {
            return null; // a colon-delimited body
        }

        $inner = self::firstChild($file, $body);
        $allowed = match (true) {
            $inner?->kind === NodeKind::Block => true,
            // `else if` is checked as the `if`; `while (...);` and `for (...);` have no body.
            $node->kind === NodeKind::IfStatementBodyElseClause => $inner?->kind === NodeKind::If,
            $inner === null => in_array($node->kind, [NodeKind::While, NodeKind::For], strict: true),
            default => false,
        };

        return $allowed ? null : $body;
    }

    private static function statementBody(SourceFile $file, ?Node $ifBody): ?Node
    {
        return $ifBody?->kind === NodeKind::IfStatementBody ? self::firstChild($file, $ifBody) : null;
    }

    private static function firstChild(SourceFile $file, ?Node $node): ?Node
    {
        return $node === null ? null : $file->getChildren($node)[0] ?? null;
    }

    /**
     * The edits that add the braces, as phpcbf places them.
     *
     * @return list<TextEdit>
     */
    private static function fix(SourceFile $file, Node $node, Node $keyword, Node $body): array
    {
        $text = $file->getText($body);
        $indent = self::indent($file->contents, $keyword->span->start);
        if ($indent === null || !in_array(substr(rtrim($text), offset: -1), [';', '}'], strict: true)) {
            return []; // a line opening with HTML or an open tag, or a body closed by a close tag
        }

        foreach ($file->getDescendants($body) as $nested) {
            if (in_array($nested->kind, self::CONTROL, strict: true) && self::inlineBody($file, $nested) !== null) {
                return []; // phpcbf fixes the inner one first
            }
        }

        $closer = in_array($node->kind, [NodeKind::IfStatementBodyElseClause, NodeKind::DoWhile], strict: true)
            ? $keyword->span->end
            : (int) strrpos(substr($file->contents, offset: 0, length: $body->span->start), needle: ')') + 1;
        $after = $file->contents[$closer] ?? '';
        $open = TextEdit::insert($closer, $after === ';' || ctype_space($after) ? ' {' : ' { ');

        return (
            $text === ';'
                ? [$open, TextEdit::replace($body->span, '}')]
                : [$open, self::close($file->contents, $body->span->end, $indent)]
        );
    }

    /** The keyword line's indentation, or null when the line opens with inline HTML or an open tag. */
    private static function indent(string $contents, int $offset): ?string
    {
        $lineStart = strrpos(substr($contents, offset: 0, length: $offset), needle: "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $before = substr($contents, $lineStart, $offset - $lineStart);
        $indent = substr($before, offset: 0, length: strspn($before, characters: " \t"));

        return in_array(substr($before, strlen($indent), length: 1), ['<', '?'], strict: true) ? null : $indent;
    }

    /**
     * The closing brace: before a following `else` or `elseif`, otherwise on a new line after
     * the body and any comment that ends its line.
     */
    private static function close(string $contents, int $end, string $indent): TextEdit
    {
        $rest = substr($contents, $end);
        $gap = strspn($rest, characters: " \t\r\n");
        if (preg_match('/^else(if)?\b/i', substr($rest, $gap)) === 1) {
            return TextEdit::insert($end + $gap, '} ');
        }

        $comment = [];
        if (preg_match('/^[ \t]*(?:(?:\/\/|#)[^\r\n]*|\/\*.*?\*\/(?=[ \t]*(?:\r?\n|$)))/', $rest, $comment) === 1) {
            $end += strlen($comment[0]);
        }

        return TextEdit::insert($end, "\n" . $indent . '}');
    }
}
