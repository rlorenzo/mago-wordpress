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

use function ctype_space;
use function in_array;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;

/**
 * Ports `PEAR.Files.IncludingFile` with the codes WordPress-Core keeps: `BracketsNotRequired`
 * (an error) for `require( 'file.php' )`, and `UseRequire`/`UseRequireOnce` (warnings) for an
 * `include`/`include_once` outside any scope, condition or assignment, where a missing file
 * should stop the script. Like the sniff, any enclosing braced or colon-delimited body (a
 * function too) counts as conditional. All three are fixed as phpcbf fixes them.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class IncludingFileRule implements Rule
{
    private const SNIFF = 'PEAR.Files.IncludingFile';

    /** Statements whose parentheses own the include (phpcs `parenthesis_owner`). */
    private const OWNERS = [
        NodeKind::If,
        NodeKind::IfStatementBodyElseIfClause,
        NodeKind::IfColonDelimitedBodyElseIfClause,
        NodeKind::While,
        NodeKind::DoWhile,
        NodeKind::For,
        NodeKind::Foreach,
        NodeKind::Switch,
        NodeKind::Match,
        NodeKind::LegacyArray,
        NodeKind::Declare,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/including-file',
            name: 'Including file',
            description: 'Reports parentheses around the path of `include`/`require`; the unconditional-include warnings are in `generic/including-file-warning`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        foreach ([
            NodeKind::IncludeConstruct,
            NodeKind::IncludeOnceConstruct,
            NodeKind::RequireConstruct,
            NodeKind::RequireOnceConstruct,
        ] as $kind) {
            foreach ($file->getNodes($kind) as $construct) {
                $this->check($context, $construct);
            }
        }
    }

    private function check(LintContext $context, Node $construct): void
    {
        $file = $context->file;
        $text = $file->getText($construct);
        preg_match('/^\w+/', $text, $match);
        $keyword = $match[0] ?? '';
        $keywordSpan = new Span($construct->span->start, $construct->span->start + strlen($keyword));
        $this->brackets($context, $construct, $keyword);

        $lower = strtolower($keyword);
        if (!in_array($lower, ['include', 'include_once'], strict: true) || self::isConditional($file, $construct)) {
            return;
        }

        $require = $lower === 'include' ? 'require' : 'require_once';
        $this->report->issue(
            $context,
            Issue::new(
                "File is being unconditionally included; use \"{$require}\" instead",
                $keywordSpan,
                'unconditional include',
            )->withHelp("Use `{$require}`, so a missing file stops the script.")->withEdit(TextEdit::replace(
                $keywordSpan,
                $require,
            )),
            [self::SNIFF . ($lower === 'include' ? '.UseRequire' : '.UseRequireOnce')],
        );
    }

    /** `include( 'file' )`: the fix drops the parentheses, keeping a space after the keyword. */
    private function brackets(LintContext $context, Node $construct, string $keyword): void
    {
        $file = $context->file;
        $after = $construct->span->start + strlen($keyword);
        $rest = substr($file->contents, $after, $construct->span->end - $after);
        // phpcs skips whitespace and comments to find the parenthesis, so `require/*x*/('a.php')` counts.
        if (
            preg_match('~^(?:\s+|/\*.*?\*/|(?://|#)[^\n]*)*~s', $rest, $gap) !== 1
            || !str_starts_with(substr($rest, strlen($gap[0])), '(')
        ) {
            return;
        }

        $open = $after + strlen($gap[0]);
        $plainGap = trim($gap[0]) === '';
        $issue = Issue::new(
            "\"{$keyword}\" is a statement not a function; no parentheses are required",
            new Span($construct->span->start, $after),
            'parentheses not needed',
        )->withHelp('Remove the parentheses.');
        foreach ($file->getDescendants($construct, NodeKind::Parenthesized) as $parenthesized) {
            if ($parenthesized->span->start !== $open) {
                continue;
            }

            if (!$plainGap) {
                break; // a comment sits before the parenthesis: report it, but leave the fix to the author
            }

            $spaced = ctype_space($file->contents[$open - 1]);
            $issue = $issue
                ->withEdit(TextEdit::replace(new Span($open, $open + 1), $spaced ? '' : ' '))
                ->withEdit(TextEdit::replace(new Span($parenthesized->span->end - 1, $parenthesized->span->end), ''));
            break;
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.BracketsNotRequired']);
    }

    /**
     * Inside a scope (a braced or colon-delimited body), inside the parentheses of a control
     * structure, or the value of an assignment or array item.
     */
    private static function isConditional(SourceFile $file, Node $construct): bool
    {
        $before = rtrim(substr($file->contents, 0, $construct->span->start));
        if (preg_match('/(?:<<=|>>=|\*\*=|\?\?=|=>|[-+*\/.%&|^]=|(?<![=!<>])=)$/', $before) === 1) {
            return true;
        }

        $child = $construct;
        foreach ($file->getAncestors($construct) as $ancestor) {
            $kind = $ancestor->kind->value;
            if (
                $ancestor->kind === NodeKind::Block
                || str_contains($kind, 'ColonDelimited')
                || in_array(
                    $ancestor->kind,
                    [NodeKind::SwitchCase, NodeKind::SwitchDefaultCase, NodeKind::NamespaceBody],
                    strict: true,
                )
            ) {
                return true;
            }

            if (
                in_array($ancestor->kind, self::OWNERS, strict: true)
                && !str_contains($child->kind->value, 'Body')
                && $child->kind !== NodeKind::Statement
            ) {
                return true;
            }

            $child = $ancestor;
        }

        return false;
    }
}
