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
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\Report;
use WeakMap;

use function in_array;
use function str_repeat;
use function strpos;
use function strrpos;
use function strspn;
use function substr;
use function trim;

/**
 * The spaces WordPress wants inside parentheses and brackets, which `mago format` removes and
 * upstream will not add an option for (mago #446, #490): `foo( $a )`, `function f( $a )`,
 * `if ( $x )`, `array( 1 )`, `[ 1 ]` and `$a[ $i ]` (but `$a['key']`, `$a[0]`).
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
 */
final class ParenthesesSpacingRule implements Rule
{
    private const CALL = [
        'PEAR.Functions.FunctionCallSignature.SpaceAfterOpenBracket',
        'PEAR.Functions.FunctionCallSignature.SpaceBeforeCloseBracket',
    ];

    private const DECLARATION = [
        'Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingAfterOpen',
        'Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingBeforeClose',
    ];

    private const CONTROL = [
        'WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceAfterOpenParenthesis',
        'WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceBeforeCloseParenthesis',
    ];

    private const ARRAY = [
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerSingleLine',
        'NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceBeforeArrayCloserSingleLine',
    ];

    private const KEY_SPACED = [
        'WordPress.Arrays.ArrayKeySpacingRestrictions.NoSpacesAroundArrayKeys',
        'WordPress.Arrays.ArrayKeySpacingRestrictions.NoSpacesAroundArrayKeys',
    ];

    private const KEY_BARE = [
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

    private const STRINGS = [NodeKind::InterpolatedString, NodeKind::DocumentString, NodeKind::ShellExecuteString];

    /** @var WeakMap<SourceFile, list<Span>> */
    private WeakMap $stringSpans;

    public function __construct(
        private readonly Report $report,
    ) {
        $this->stringSpans = new WeakMap();
    }

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

        $text = $file->getText($node);
        $open = strpos($text, needle: '(');
        $close = strrpos($text, needle: ')');
        if ($open !== false && $close !== false) {
            $this->check($context, $node->span->start + $open, $node->span->start + $close, 1, $codes);
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

        $open = strpos($context->file->contents, needle: '[', offset: $array->span->end);
        if ($open === false) {
            return;
        }

        $literal = $context->file->getChildren($key)[0] ?? null;
        $value =
            $literal !== null && $literal->kind === NodeKind::Literal
                ? $context->file->getChildren($literal)[0] ?? null
                : null;
        $bare =
            $value !== null
            && in_array($value->kind, [NodeKind::LiteralString, NodeKind::LiteralInteger], strict: true);

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

        $open = strpos($file->contents, needle: '(', offset: $from);
        $close = strpos($file->contents, needle: ')', offset: $last->span->end);
        if ($open === false || $close === false) {
            return;
        }

        $this->check($context, $open, $close, 1, self::CONTROL);
    }

    /**
     * @param 0|1 $want
     * @param array{string, string} $codes after-open and before-close message codes
     */
    private function check(LintContext $context, int $open, int $close, int $want, array $codes): void
    {
        $contents = $context->file->contents;
        if (trim(substr($contents, $open + 1, $close - $open - 1)) === '') {
            return;
        }

        $after = strspn($contents, characters: " \t", offset: $open + 1);
        if (!in_array($contents[$open + 1 + $after], ["\n", "\r"], strict: true) && $after !== $want) {
            $this->flag(
                $context,
                new Span($open + 1, $open + 1 + $after),
                $want,
                'after "' . $contents[$open] . '"',
                $codes[0],
            );
        }

        $before = 0;
        while (in_array($contents[$close - 1 - $before], [' ', "\t"], strict: true)) {
            ++$before;
        }

        if (!in_array($contents[$close - 1 - $before], ["\n", "\r"], strict: true) && $before !== $want) {
            $this->flag(
                $context,
                new Span($close - $before, $close),
                $want,
                'before "' . $contents[$close] . '"',
                $codes[1],
            );
        }
    }

    /**
     * @param 0|1 $want
     */
    private function flag(LintContext $context, Span $gap, int $want, string $where, string $code): void
    {
        $found = $gap->end - $gap->start;
        $this->report->issue(
            $context,
            Issue::new(
                "Expected {$want} " . ($want === 1 ? 'space' : 'spaces') . " {$where}; found {$found}.",
                $found === 0 ? new Span($gap->start - 1, $gap->start + 1) : $gap,
            )->withEdit(TextEdit::replace($gap, str_repeat(' ', $want))),
            [$code],
        );
    }

    private function inString(SourceFile $file, Span $span): bool
    {
        if (($this->stringSpans[$file] ?? null) === null) {
            $spans = [];
            foreach (self::STRINGS as $kind) {
                foreach ($file->getNodes($kind) as $string) {
                    $spans[] = $string->span;
                }
            }

            $this->stringSpans[$file] = $spans;
        }

        foreach ($this->stringSpans[$file] as $string) {
            if ($string->contains($span)) {
                return true;
            }
        }

        return false;
    }
}
