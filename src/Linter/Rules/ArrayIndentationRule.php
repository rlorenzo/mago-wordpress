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
use Rlorenzo\MagoWordPress\Internal\Layout\LayoutToken;
use Rlorenzo\MagoWordPress\Internal\Layout\PhpcsLayout;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_key_last;
use function floor;
use function ltrim;
use function max;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * Ports `WordPress.Arrays.ArrayIndentation` (`tabIndent` on, the tab width 4 of the ruleset): the items of a
 * multi-line array are indented one level deeper than the line the array opens on, and the closer is aligned
 * with that line. It follows the sniff statement for statement on the phpcs token model ({@see PhpcsLayout}),
 * with `PassedParameters::getParameters()` for the items, and `Arrays::isShortArray()` reduced to the cases
 * that matter (a short list is `[...] =`, a `foreach` target, or inside one). The fix writes the indentation
 * phpcbf writes and moves the other lines of a multi-line item with the item; it leaves out phpcbf's later
 * passes. Like `generic/scope-indent`, it skips a file past the layout model's size limit.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:halstead
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class ArrayIndentationRule implements Rule
{
    private const SNIFF = 'WordPress.Arrays.ArrayIndentation';

    private const TAB_WIDTH = 4;

    private const WS = ['T_WHITESPACE' => true, 'T_DOC_COMMENT_WHITESPACE' => true];

    private const WS_COMMENT = ['T_WHITESPACE' => true, 'T_DOC_COMMENT_WHITESPACE' => true, 'T_COMMENT' => true];

    private const IGNORED = [
        'T_END_HEREDOC' => true,
        'T_HEREDOC' => true,
        'T_END_NOWDOC' => true,
        'T_NOWDOC' => true,
        'T_INLINE_HTML' => true,
    ];

    private const STRINGS = ['T_CONSTANT_ENCAPSED_STRING' => true, 'T_DOUBLE_QUOTED_STRING' => true];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/array-indentation',
            name: 'Array indentation',
            description: 'Requires the items of a multi-line array to be indented one level deeper than the line it opens on, and the closer aligned with that line.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $layout = PhpcsLayout::of($context->file);
        if ($layout === null) {
            return;
        }

        $skipTo = -1;
        $tokens = $layout->tokens;
        foreach ($tokens as $stackPtr => $token) {
            if ($token->code !== 'T_ARRAY' && $token->code !== 'T_OPEN_SHORT_ARRAY') {
                continue;
            }

            if ($skipTo > $stackPtr) {
                continue;
            }

            $skip = $this->process($context, $layout, $stackPtr);
            if ($skip !== null) {
                $skipTo = $skip;
            }
        }
    }

    /**
     * The sniff's `process_token()`.
     *
     * @return int|null the closer, when the sniff skips the rest of the array
     */
    private function process(LintContext $context, PhpcsLayout $layout, int $stackPtr): ?int
    {
        $tokens = $layout->tokens;
        $token = $tokens[$stackPtr];
        if ($token->code === 'T_ARRAY') {
            $opener = $token->parenthesisOpener;
            $closer = $opener === null ? null : $tokens[$opener]->parenthesisCloser;
        } else {
            $opener = $stackPtr;
            $closer = $token->bracketCloser;
        }

        if ($opener === null || $closer === null || $tokens[$opener]->line === $tokens[$closer]->line) {
            return null;
        }

        if ($token->code === 'T_OPEN_SHORT_ARRAY' && self::isShortList($layout, $stackPtr, $closer)) {
            return null;
        }

        $openerLineSpaces = self::indentationSize($tokens, $opener);
        $closerLineSpaces = $tokens[$closer]->column - 1;
        if ($closerLineSpaces !== $openerLineSpaces) {
            $message = sprintf(
                'Array closer not aligned correctly; expected %s space(s) but found %s',
                $openerLineSpaces,
                $closerLineSpaces,
            );
            $onOwnLine =
                $closerLineSpaces === 0
                || $tokens[$closer - 1]->code === 'T_WHITESPACE' && $tokens[$closer - 1]->column === 1;
            // Otherwise only reported, not fixed: the closer has to be moved to its own line first.
            $this->add(
                $context,
                $layout,
                $closer,
                $message,
                'CloseBraceNotAligned',
                $onOwnLine ? self::indentString($openerLineSpaces) : null,
            );
        }

        $items = self::items($layout, $stackPtr, $opener, $closer);
        if ($items === []) {
            return null;
        }

        $expectedSpaces = $openerLineSpaces + self::TAB_WIDTH;
        $expectedIndent = self::indentString($expectedSpaces);
        $endOfPreviousItem = $opener;
        foreach ($items as [$start, $end]) {
            $endOfThisItem = $end + 1;
            $firstContent = $layout->findNext(self::WS, $start, $endOfThisItem, true);
            if (
                $firstContent !== null
                && $tokens[$firstContent]->code === 'T_COMMENT'
                && $tokens[$firstContent]->line === $tokens[$endOfPreviousItem]->line
            ) {
                $firstContent = $layout->findNext(self::WS_COMMENT, $firstContent + 1, $endOfThisItem, true);
            }

            if ($firstContent === null) {
                $endOfPreviousItem = $endOfThisItem;
                continue;
            }

            // A mixed multi-line/single-line array is left to ArrayDeclarationSpacing.
            if ($tokens[$firstContent]->line === $tokens[$endOfPreviousItem]->line) {
                return $closer;
            }

            if ($tokens[$firstContent]->column !== 1) {
                $i = $firstContent;
                while ($tokens[--$i]->column !== 1) {
                }

                if ($tokens[$i]->code !== 'T_WHITESPACE') {
                    $endOfPreviousItem = $endOfThisItem;
                    continue;
                }
            }

            $foundSpaces = $tokens[$firstContent]->column - 1;
            if ($foundSpaces !== $expectedSpaces) {
                $this->add(
                    $context,
                    $layout,
                    $firstContent,
                    sprintf(
                        'Array item not aligned correctly; expected %s spaces but found %s',
                        $expectedSpaces,
                        $foundSpaces,
                    ),
                    'ItemNotAligned',
                    $expectedIndent,
                );
            }

            if ($tokens[$firstContent]->line !== $tokens[$end]->line) {
                $this->multiLineItem(
                    $context,
                    $layout,
                    $firstContent,
                    $end,
                    $foundSpaces,
                    $expectedSpaces,
                    $expectedIndent,
                );
            }

            $endOfPreviousItem = $endOfThisItem;
        }

        return null;
    }

    /** The part of the loop for an item that spans lines. */
    private function multiLineItem(
        LintContext $context,
        PhpcsLayout $layout,
        int $firstContent,
        int $itemEnd,
        int $foundSpaces,
        int $expectedSpaces,
        string $expectedIndent,
    ): void {
        $tokens = $layout->tokens;
        $endOfThisItem = $itemEnd + 1;
        for ($ptr = $firstContent + 1; $ptr <= $itemEnd; $ptr++) {
            if (
                $tokens[$firstContent]->line !== $tokens[$ptr]->line
                && $tokens[$ptr]->column === 1
                && !self::ignoreToken($tokens[$ptr])
            ) {
                break;
            }
        }

        $firstOnLine2 = $layout->findNext(self::WS, $ptr, $endOfThisItem, true);
        if ($firstOnLine2 === null) {
            // Only ignored tokens on the later lines: the comma after the item may be on a line of its own.
            $comma = $tokens[$endOfThisItem] ?? null;
            if (
                $comma !== null
                && $tokens[$itemEnd]->line !== $comma->line
                && $comma->code === 'T_COMMA'
                && ($comma->column - 1) !== $expectedSpaces
            ) {
                $this->add(
                    $context,
                    $layout,
                    $endOfThisItem,
                    sprintf(
                        'Comma after multi-line array item not aligned correctly; expected %s spaces, but found %s',
                        $expectedSpaces,
                        $comma->column - 1,
                    ),
                    'MultiLineArrayItemCommaNotAligned',
                    $expectedIndent,
                );
            }

            return;
        }

        $foundOnLine2 = self::indentationSize($tokens, $firstOnLine2);
        $expectedOnLine2 = $expectedSpaces;
        if ($foundSpaces < $foundOnLine2) {
            $expectedOnLine2 += $foundOnLine2 - $foundSpaces;
        }

        if ($foundOnLine2 === $expectedOnLine2) {
            return;
        }

        $edits = $this->lineEdits($layout, $firstOnLine2, $itemEnd, $endOfThisItem, $expectedOnLine2, $foundOnLine2);
        // phpcs checks the comma after the item only while fixing, so it is fixed but not reported.
        $comma = $tokens[$endOfThisItem] ?? null;
        if (
            $comma !== null
            && $tokens[$itemEnd]->line !== $comma->line
            && $comma->code === 'T_COMMA'
            && ($comma->column - 1) !== $expectedSpaces
        ) {
            $edits[] = self::fixEdit($tokens, $endOfThisItem, $expectedIndent);
        }

        $this->issue(
            $context,
            $layout,
            $firstOnLine2,
            sprintf(
                'Multi-line array item not aligned correctly; expected %s spaces, but found %s',
                $expectedOnLine2,
                $foundOnLine2,
            ),
            'MultiLineArrayItemNotAligned',
            $edits,
        );
    }

    /**
     * The fix for the second and later lines of an item.
     *
     * @return list<TextEdit>
     */
    private function lineEdits(
        PhpcsLayout $layout,
        int $firstOnLine2,
        int $itemEnd,
        int $endOfThisItem,
        int $expectedOnLine2,
        int $foundOnLine2,
    ): array {
        $tokens = $layout->tokens;
        $edits = [self::lineEdit($tokens, $firstOnLine2, self::indentString($expectedOnLine2))];
        for ($i = $firstOnLine2 + 1; $i <= $itemEnd; $i++) {
            if ($tokens[$i]->column !== 1) {
                if ($tokens[$i]->line === $tokens[$itemEnd]->line) {
                    break;
                }

                continue;
            }

            $firstOnLine = $layout->findNext(self::WS, $i, $endOfThisItem, true);
            if ($firstOnLine === null) {
                break;
            }

            if (self::ignoreToken($tokens[$firstOnLine])) {
                $i = $firstOnLine;
                continue;
            }

            $foundOnLine = self::indentationSize($tokens, $firstOnLine);
            $expectedOnLine = max($expectedOnLine2 + ($foundOnLine - $foundOnLine2), 0);
            if ($foundOnLine !== $expectedOnLine) {
                $edits[] = self::lineEdit($tokens, $firstOnLine, self::indentString($expectedOnLine));
            }

            $i = $firstOnLine;
        }

        return $edits;
    }

    /**
     * The edit for a line's indentation: a comment that starts the line carries its own indentation.
     *
     * @param list<LayoutToken> $tokens
     */
    private static function lineEdit(array $tokens, int $ptr, string $indent): TextEdit
    {
        $token = $tokens[$ptr];
        if ($token->column === 1 && $token->code === 'T_COMMENT') {
            return TextEdit::replace(self::span($token), $indent . ltrim($token->content));
        }

        return self::fixEdit($tokens, $ptr, $indent);
    }

    /**
     * `fix_alignment_error()`: the indentation goes before a token at column 1, else replaces the token before.
     *
     * @param list<LayoutToken> $tokens
     */
    private static function fixEdit(array $tokens, int $ptr, string $indent): TextEdit
    {
        $token = $tokens[$ptr];
        if ($token->column === 1) {
            return TextEdit::insert($token->pos, $indent);
        }

        return TextEdit::replace(self::span($tokens[$ptr - 1]), $indent);
    }

    private static function span(LayoutToken $token): Span
    {
        return new Span($token->pos, $token->pos + strlen($token->origContent ?? $token->content));
    }

    /** `add_array_alignment_error()`; without an indent the error is reported and not fixed. */
    private function add(
        LintContext $context,
        PhpcsLayout $layout,
        int $ptr,
        string $message,
        string $code,
        ?string $indent,
    ): void {
        $this->issue(
            $context,
            $layout,
            $ptr,
            $message,
            $code,
            $indent === null ? [] : [self::fixEdit($layout->tokens, $ptr, $indent)],
        );
    }

    /** @param list<TextEdit> $edits */
    private function issue(
        LintContext $context,
        PhpcsLayout $layout,
        int $ptr,
        string $message,
        string $code,
        array $edits,
    ): void {
        $issue = Issue::new($message, self::span($layout->tokens[$ptr]));
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }

    /**
     * `PassedParameters::getParameters()` for an array: each item's first and last token (the last is the token
     * before the comma, whitespace and comments included).
     *
     * @return list<array{int, int}>
     */
    private static function items(PhpcsLayout $layout, int $stackPtr, int $opener, int $closer): array
    {
        $tokens = $layout->tokens;
        $next = $layout->findNext(PhpcsLayout::EMPTY, $opener + 1, null, true);
        if ($next === null || $next === $closer) {
            return [];
        }

        $items = [];
        $start = $opener + 1;
        for ($i = $opener + 1; $i <= $closer; $i++) {
            $token = $tokens[$i];
            $code = $token->code;
            if ($i !== $closer && $code !== 'T_COMMA') {
                if (
                    ($code === 'T_OPEN_SHORT_ARRAY' || $code === 'T_OPEN_SQUARE_BRACKET')
                    && $token->bracketCloser !== null
                ) {
                    $i = $token->bracketCloser;
                } elseif ($code === 'T_OPEN_PARENTHESIS' && $token->parenthesisCloser !== null) {
                    $i = $token->parenthesisCloser;
                } elseif (
                    isset(PhpcsLayout::SCOPE_OPENERS[$code])
                    && $token->scopeCondition === $i
                    && $token->scopeCloser !== null
                ) {
                    $i = $token->scopeCloser;
                } elseif ($code === 'T_DOC_COMMENT_OPEN_TAG' && $token->commentCloser !== null) {
                    $i = $token->commentCloser;
                } elseif ($code === 'T_ATTRIBUTE') {
                    $end = $layout->findNext(['T_ATTRIBUTE_END' => true], $i + 1, $closer);
                    $i = $end ?? $i;
                }

                continue;
            }

            $items[] = [$start, $i - 1];
            if ($layout->findNext(PhpcsLayout::EMPTY, $i + 1, $closer, true) === null) {
                break;
            }

            $start = $i + 1;
        }

        return $items;
    }

    /**
     * Whether the short array is a destructuring target: followed by `=`, after `as` in a `foreach`, or nested
     * in one.
     */
    private static function isShortList(PhpcsLayout $layout, int $opener, int $closer): bool
    {
        $tokens = $layout->tokens;
        while (true) {
            $closerNext = $layout->findNext(PhpcsLayout::EMPTY, $closer + 1, null, true);
            if ($closerNext !== null && $tokens[$closerNext]->code === 'T_EQUAL') {
                return true;
            }

            $prev = $layout->findPrevious(PhpcsLayout::EMPTY, $opener - 1, null, true);
            if ($prev !== null && self::followsForeachAs($layout, $prev)) {
                return true;
            }

            // The enclosing bracket, if the array is an item of a short array.
            $parent = self::enclosingShortArray($layout, $opener);
            if ($parent === null) {
                return false;
            }

            $opener = $parent;
            $closer = (int) $tokens[$parent]->bracketCloser;
        }
    }

    private static function followsForeachAs(PhpcsLayout $layout, int $prev): bool
    {
        $tokens = $layout->tokens;
        $code = $tokens[$prev]->code;
        if ($code !== 'T_AS' && $code !== 'T_DOUBLE_ARROW') {
            return false;
        }

        $token = $tokens[$prev];
        $open = $token->nestedParenthesis === [] ? null : array_key_last($token->nestedParenthesis);
        $owner = $open === null ? null : $tokens[$open]->parenthesisOwner;
        if ($owner === null || $tokens[$owner]->code !== 'T_FOREACH') {
            return false;
        }

        if ($code === 'T_AS') {
            return true;
        }

        // `=>` counts after the `as` only.
        for ($x = $prev - 1; $x > $open; $x--) {
            if ($tokens[$x]->code === 'T_AS' && $tokens[$x]->nestedParenthesis === $token->nestedParenthesis) {
                return true;
            }
        }

        return false;
    }

    private static function enclosingShortArray(PhpcsLayout $layout, int $opener): ?int
    {
        $tokens = $layout->tokens;
        for ($x = $opener - 1; $x >= 0; $x--) {
            $token = $tokens[$x];
            if (
                ($token->code === 'T_CLOSE_SHORT_ARRAY' || $token->code === 'T_CLOSE_SQUARE_BRACKET')
                && $token->bracketOpener !== null
            ) {
                $x = $token->bracketOpener;
                continue;
            }

            if ($token->code === 'T_CLOSE_PARENTHESIS' && $token->parenthesisOpener !== null) {
                $x = $token->parenthesisOpener;
                continue;
            }

            if (
                $token->code === 'T_OPEN_PARENTHESIS'
                || $token->code === 'T_OPEN_CURLY_BRACKET'
                || $token->code === 'T_SEMICOLON'
            ) {
                return null;
            }

            if ($token->code === 'T_OPEN_SHORT_ARRAY') {
                return $x;
            }

            if ($token->code === 'T_OPEN_SQUARE_BRACKET') {
                return null;
            }
        }

        return null;
    }

    /** `ignore_token()`: heredocs, inline HTML, and later lines of a multi-line string. */
    private static function ignoreToken(LayoutToken $token): bool
    {
        if (isset(self::IGNORED[$token->code])) {
            return true;
        }

        if (isset(self::STRINGS[$token->code])) {
            return (
                $token->content === "'"
                || $token->content === '"'
                || ($token->content[0] ?? '') !== "'"
                && ($token->content[0] ?? '') !== '"'
            );
        }

        return false;
    }

    /**
     * `get_indentation_size()`: the width of the indentation of the token's line.
     *
     * @param list<LayoutToken> $tokens
     */
    private static function indentationSize(array $tokens, int $ptr): int
    {
        while ($ptr > 0 && $tokens[$ptr]->column !== 1) {
            $ptr--;
        }

        $token = $tokens[$ptr];
        if ($token->code === 'T_WHITESPACE' || $token->code === 'T_DOC_COMMENT_WHITESPACE') {
            return $token->length;
        }

        if ($token->code === 'T_COMMENT') {
            // A later line of a block comment holds its indentation.
            return strlen($token->content) - strlen(ltrim($token->content));
        }

        return 0;
    }

    /** `get_indentation_string()`: tabs, then the spaces that are left. */
    private static function indentString(int $spaces): string
    {
        if ($spaces <= 0) {
            return '';
        }

        return (
            str_repeat("\t", max(0, (int) floor($spaces / self::TAB_WIDTH)))
            . str_repeat(' ', max(0, $spaces % self::TAB_WIDTH))
        );
    }
}
