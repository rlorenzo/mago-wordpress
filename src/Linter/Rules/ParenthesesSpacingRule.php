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
use Rlorenzo\MagoWordPress\Internal\Calls;
use Rlorenzo\MagoWordPress\Internal\FileCache;
use Rlorenzo\MagoWordPress\Internal\Report;

use function in_array;
use function str_contains;
use function str_repeat;
use function str_replace;
use function strpos;
use function strrpos;
use function strspn;
use function substr;
use function trim;

/**
 * The spaces WordPress wants inside parentheses and brackets, which `mago format` removes and
 * upstream will not add an option for (mago #446, #490): `foo( $a )`, `function f( $a )`,
 * `if ( $x )`, `array( 1 )`, `[ 1 ]` and `$a[ $i ]` (but `$a['key']`, `$a[0]`), and the space before
 * an alternative-syntax colon (`if ( $x ) :`, `else :`).
 *
 * Off by default: it is meant to run after `mago format` as
 * `mago lint --fix --only wordpress/parentheses-spacing`, and checked the same way, since
 * `mago format` undoes it. Only the side of a pair that shares a line with its content is
 * checked, empty pairs are left alone, and nothing inside a string is touched (`"$a[0]"` would
 * change meaning).
 *
 * Covers the single-line part of `PEAR.Functions.FunctionCallSignature`,
 * `Squiz.Functions.FunctionDeclarationArgumentSpacing`,
 * `WordPress.WhiteSpace.ControlStructureSpacing`, `NormalizedArrays.Arrays.ArrayBraceSpacing` and
 * `WordPress.Arrays.ArrayKeySpacingRestrictions`, reporting their message codes so phpcs
 * suppressions still apply.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ParenthesesSpacingRule implements Rule
{
    private const CALL = [
        'PEAR.Functions.FunctionCallSignature.SpaceAfterOpenBracket',
        'PEAR.Functions.FunctionCallSignature.SpaceBeforeCloseBracket',
        'PEAR.Functions.FunctionCallSignature.SpaceAfterOpenBracket',
        'PEAR.Functions.FunctionCallSignature.SpaceBeforeCloseBracket',
    ];

    private const DECLARATION = [
        'Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingAfterOpen',
        'Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingBeforeClose',
        'Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingAfterOpen',
        'Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingBeforeClose',
    ];

    /**
     * Each list: the after-open and before-close codes for a missing space, then for a space that is
     * there but wrong (too much, or a tab), which only some sniffs tell apart.
     */
    private const CONTROL = [
        'WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceAfterOpenParenthesis',
        'WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceBeforeCloseParenthesis',
        'WordPress.WhiteSpace.ControlStructureSpacing.ExtraSpaceAfterOpenParenthesis',
        'WordPress.WhiteSpace.ControlStructureSpacing.ExtraSpaceBeforeCloseParenthesis',
    ];

    private const ARRAY = [
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerSingleLine',
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceBeforeArrayCloserSingleLine',
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerSingleLine',
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceBeforeArrayCloserSingleLine',
    ];

    /** An array whose opener and closer are on different lines (WPCS wants a newline there). */
    private const ARRAY_MULTILINE = [
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerMultiLine',
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceBeforeArrayCloserMultiLine',
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerMultiLine',
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceBeforeArrayCloserMultiLine',
    ];

    private const KEY_SPACED = [
        'WordPress.Arrays.ArrayKeySpacingRestrictions.NoSpacesAroundArrayKeys',
        'WordPress.Arrays.ArrayKeySpacingRestrictions.NoSpacesAroundArrayKeys',
        'WordPress.Arrays.ArrayKeySpacingRestrictions.TooMuchSpaceBeforeKey',
        'WordPress.Arrays.ArrayKeySpacingRestrictions.TooMuchSpaceAfterKey',
    ];

    private const KEY_BARE = [
        'WordPress.Arrays.ArrayKeySpacingRestrictions.SpacesAroundArrayKeys',
        'WordPress.Arrays.ArrayKeySpacingRestrictions.SpacesAroundArrayKeys',
        'WordPress.Arrays.ArrayKeySpacingRestrictions.SpacesAroundArrayKeys',
        'WordPress.Arrays.ArrayKeySpacingRestrictions.SpacesAroundArrayKeys',
    ];

    /** Nodes whose own first and last characters are the pair. */
    private const DELIMITED = [
        'ArgumentList' => self::CALL,
        'PartialArgumentList' => self::CALL,
        'FunctionLikeParameterList' => self::DECLARATION,
        'Array' => self::ARRAY,
    ];

    /** Nodes that start with a keyword and end with the pair's `)` (bar `unset(...);`'s `;`). */
    private const KEYWORD_LED = [
        'IssetConstruct' => self::CALL,
        'EmptyConstruct' => self::CALL,
        'EvalConstruct' => self::CALL,
        'Unset' => self::CALL,
        'ClosureUseClause' => self::DECLARATION,
        'LegacyArray' => self::ARRAY,
        'List' => self::ARRAY,
    ];

    /** Children that are a control structure's body rather than its condition. */
    private const BODIES = [
        NodeKind::IfBody,
        NodeKind::ForeachBody,
        NodeKind::ForBody,
        NodeKind::WhileBody,
        NodeKind::SwitchBody,
        NodeKind::Statement,
        NodeKind::Block,
        NodeKind::MatchArm,
    ];

    private const COLON = 'WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceBetweenStructureColon';

    private const STRINGS = [NodeKind::InterpolatedString, NodeKind::DocumentString, NodeKind::ShellExecuteString];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/parentheses-spacing',
            name: 'Parentheses spacing',
            description: 'Requires one space inside call, declaration, control-structure and array parentheses and brackets, as WordPress-Core does. Off by default; run after `mago format`.',
            defaultLevel: Level::Error,
            defaultEnabled: false,
            targets: [
                NodeKind::ArgumentList,
                NodeKind::PartialArgumentList,
                NodeKind::IssetConstruct,
                NodeKind::EmptyConstruct,
                NodeKind::EvalConstruct,
                NodeKind::Unset,
                NodeKind::FunctionLikeParameterList,
                NodeKind::ClosureUseClause,
                NodeKind::If,
                NodeKind::IfStatementBodyElseIfClause,
                NodeKind::IfColonDelimitedBodyElseIfClause,
                NodeKind::IfColonDelimitedBodyElseClause,
                NodeKind::While,
                NodeKind::DoWhile,
                NodeKind::For,
                NodeKind::Foreach,
                NodeKind::Switch,
                NodeKind::Match,
                NodeKind::TryCatchClause,
                NodeKind::LegacyArray,
                NodeKind::List,
                NodeKind::Array,
                NodeKind::ArrayAccess,
                // Only so the snapshot carries them for inString(): a linter snapshot holds just the targets' subtrees.
                ...self::STRINGS,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $node = $context->node;
        if (in_array($node->kind, self::STRINGS, strict: true) || $this->inString($file, $node->span)) {
            return;
        }

        $kind = $node->kind;
        if ($kind === NodeKind::IfColonDelimitedBodyElseClause) {
            $this->checkColon($context, $node->span->start + 4); // after `else`

            return;
        }

        if ($kind === NodeKind::ArrayAccess) {
            $this->checkArrayAccess($context);

            return;
        }

        $codes = self::DELIMITED[$kind->value] ?? null;
        if ($codes !== null) {
            $this->check($context, $node->span->start, $node->span->end - 1, 1, $codes);

            return;
        }

        $codes = self::KEYWORD_LED[$kind->value] ?? null;
        if ($codes === null) {
            $this->checkControlStructure($context);

            return;
        }

        $open = self::delimiter($file, '(', $node->span->start, $node->span->end);
        $close = self::delimiter($file, ')', $node->span->start, $node->span->end, last: true);
        if ($open !== null && $close !== null) {
            $this->check($context, $open, $close, 1, $codes);
        }
    }

    /**
     * `$a[ $i ]` gets one space each side; a key that is a lone string or integer literal gets none.
     */
    private function checkArrayAccess(LintContext $context): void
    {
        [$array, $key] = $context->file->getChildren($context->node) + [null, null];
        if ($array === null || $key === null) {
            return;
        }

        $file = $context->file;
        $open = self::delimiter($file, '[', $array->span->end, $key->span->start);
        if ($open === null) {
            return;
        }

        $bare = self::isBareKey($file, $key);

        $this->check(
            $context,
            $open,
            $context->node->span->end - 1,
            $bare ? 0 : 1,
            $bare ? self::KEY_BARE : self::KEY_SPACED,
        );
    }

    /**
     * The condition's `(` is the first one after the keyword (after the body for do-while), and
     * its `)` the first one after the last condition child.
     */
    private function checkControlStructure(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        $from = $context->node->span->start;
        $last = null;
        foreach ($children as $child) {
            if ($child->kind === NodeKind::Keyword || $child->kind === NodeKind::Terminator) {
                continue;
            }

            if (in_array($child->kind, self::BODIES, strict: true)) {
                if ($context->node->kind === NodeKind::DoWhile) {
                    $from = $child->span->end;
                }

                continue;
            }

            $last = $child;
        }

        if ($last === null) {
            return; // for (;;)
        }

        $open = self::delimiter($file, '(', $from, $context->node->span->end);
        $close = self::delimiter($file, ')', $last->span->end, $context->node->span->end);
        if ($open === null || $close === null) {
            return;
        }

        $this->check($context, $open, $close, 1, self::CONTROL);
        $this->checkColon($context, $close + 1);
    }

    /**
     * Alternative syntax wants a space before its `:` (`if ( $x ) :`, `else :`), which
     * `mago format` removes.
     */
    private function checkColon(LintContext $context, int $at): void
    {
        if (($context->file->contents[$at] ?? '') !== ':') {
            return;
        }

        $this->report->issue(
            $context,
            Issue::new(
                'Expected 1 space before the alternative syntax ":"; found none.',
                new Span($at - 1, $at + 1),
            )->withEdit(TextEdit::insert($at, ' ')),
            [self::COLON],
        );
    }

    /**
     * @param 0|1 $want
     * @param array{string, string, string, string} $codes
     */
    private function check(LintContext $context, int $open, int $close, int $want, array $codes): void
    {
        $contents = $context->file->contents;
        $inner = substr($contents, $open + 1, $close - $open - 1);
        if (trim($inner) === '') {
            return;
        }

        if ($codes === self::ARRAY && str_contains($inner, "\n")) {
            $codes = self::ARRAY_MULTILINE;
        }

        $after = strspn($contents, characters: " \t", offset: $open + 1);
        if (!in_array($contents[$open + 1 + $after], ["\n", "\r"], strict: true)) {
            $gap = new Span($open + 1, $open + 1 + $after);
            $this->flag($context, $gap, $want, 'after "' . $contents[$open] . '"', [$codes[0], $codes[2]]);
        }

        $before = 0;
        while (in_array($contents[$close - 1 - $before], [' ', "\t"], strict: true)) {
            ++$before;
        }

        if (!in_array($contents[$close - 1 - $before], ["\n", "\r"], strict: true)) {
            $gap = new Span($close - $before, $close);
            $this->flag($context, $gap, $want, 'before "' . $contents[$close] . '"', [$codes[1], $codes[3]]);
        }
    }

    /**
     * Reports a gap that is not exactly `$want` spaces (a tab is not a space).
     *
     * @param 0|1 $want
     * @param array{string, string} $codes for a missing gap, and for one that is there but wrong
     */
    private function flag(LintContext $context, Span $gap, int $want, string $where, array $codes): void
    {
        $text = $context->file->getText($gap);
        if ($text === str_repeat(' ', $want)) {
            return;
        }

        $found = $gap->end - $gap->start;
        $shown = $found === 0 ? 'none' : '"' . str_replace(search: "\t", replace: '\t', subject: $text) . '"';
        $this->report->issue(
            $context,
            Issue::new(
                "Expected {$want} " . ($want === 1 ? 'space' : 'spaces') . " {$where}; found {$shown}.",
                $found === 0 ? new Span($gap->start - 1, $gap->start + 1) : $gap,
            )->withEdit(TextEdit::replace($gap, str_repeat(' ', $want))),
            [$found === 0 ? $codes[0] : $codes[1]],
        );
    }

    /**
     * The first (or last) `$char` in [$from, $to) that is not inside a comment.
     */
    private static function delimiter(SourceFile $file, string $char, int $from, int $to, bool $last = false): ?int
    {
        $text = substr($file->contents, $from, $to - $from);
        $at = $last ? strrpos($text, $char) : strpos($text, $char);
        while ($at !== false && Calls::hasComment($file, new Span($from + $at, $from + $at + 1))) {
            $at = $last ? strrpos(substr($text, offset: 0, length: $at), $char) : strpos($text, $char, $at + 1);
        }

        return $at === false ? null : $from + $at;
    }

    /**
     * Whether an array key is a lone string or integer literal, which takes no spaces. WPCS skips a
     * sign before an integer (`$a[-1]`, `$a[+1]`).
     */
    private static function isBareKey(SourceFile $file, Node $key): bool
    {
        $node = $file->getChildren($key)[0] ?? null;
        $kinds = [NodeKind::LiteralString, NodeKind::LiteralInteger];
        if ($node !== null && $node->kind === NodeKind::UnaryPrefix) {
            [$operator, $operand] = $file->getChildren($node) + [null, null];
            $sign = $operator === null ? '' : trim($file->getText($operator));
            $node = $sign === '-' || $sign === '+' ? $operand : null;
            $kinds = [NodeKind::LiteralInteger];
        }

        // The operand may still be wrapped in an Expression.
        if ($node !== null && $node->kind === NodeKind::Expression) {
            $node = $file->getChildren($node)[0] ?? null;
        }

        $value = $node !== null && $node->kind === NodeKind::Literal ? $file->getChildren($node)[0] ?? null : null;

        return $value !== null && in_array($value->kind, $kinds, strict: true);
    }

    private function inString(SourceFile $file, Span $span): bool
    {
        $spans = FileCache::remember($file, 'string-spans', static function () use ($file): array {
            $spans = [];
            foreach (self::STRINGS as $kind) {
                foreach ($file->getNodes($kind) as $string) {
                    $spans[] = $string->span;
                }
            }

            return $spans;
        });

        foreach ($spans as $string) {
            if ($string->contains($span)) {
                return true;
            }
        }

        return false;
    }
}
