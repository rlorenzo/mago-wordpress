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
use Rlorenzo\MagoWordPress\Internal\FunctionTokens;
use Rlorenzo\MagoWordPress\Internal\KernighanRitchieBrace;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function in_array;
use function intdiv;
use function max;
use function str_contains;
use function str_repeat;
use function strlen;
use function strpos;
use function strrpos;
use function strspn;
use function substr;

/**
 * Ports `Squiz.Functions.MultiLineFunctionDeclaration`, which runs `PEAR.Functions.FunctionDeclaration`
 * with a wider idea of "multi-line" (any parameter on another line than the previous one, or the first
 * one on another line than the keyword). For every function and closure: one space after `function`
 * and around `use`, none before `(` or a `;`. A single-line declaration gets the brace checks
 * (`ContentAfterBrace`; a closure also `BraceOnNewLine` and `SpaceBeforeBrace`) and `CloseBracketNewLine`.
 * A multi-line one: its parentheses' lines, one parameter per line, indented one level past the
 * declaration, no blank lines, `{` on the line of `)` with one space before it.
 *
 * The `BraceOnSameLine`, `BraceSpacing` and `BraceIndent` codes, which want the brace on a line of its
 * own, are off in WordPress-Core and not reported.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class MultiLineFunctionDeclarationRule implements Rule
{
    private const SNIFF = 'Squiz.Functions.MultiLineFunctionDeclaration';

    private const INDENT = 4;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/multi-line-function-declaration',
            name: 'Multi-line function declaration',
            description: 'Checks the spacing of a function declaration: one space after `function` and around `use`, one parameter per line in a multi-line declaration, and the brace on the line of the closing parenthesis.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        foreach ($tokens as $index => $token) {
            if ($token->code !== 'T_FUNCTION' && $token->code !== 'T_CLOSURE') {
                continue;
            }

            $parentheses = FunctionTokens::parentheses($tokens, $index);
            if ($parentheses === null) {
                continue;
            }

            $use = $token->code === 'T_CLOSURE'
                ? FunctionTokens::useClause($tokens, $parentheses[1], $token->scopeOpener)
                : null;
            $this->spacing($context, $tokens, $index, $parentheses, $use);
            if ($this->isMultiLine($tokens, $index, $parentheses[0], $use)) {
                $this->multiLine($context, $tokens, $index, $parentheses, $use);
            } else {
                $this->singleLine($context, $tokens, $index, $parentheses);
            }
        }
    }

    /**
     * The spaces after `function`, before `(`, before a `;` and around `use`.
     *
     * @param list<PhpcsToken> $tokens
     * @param array{int, int} $parentheses
     * @param null|array{int, int, int} $use
     */
    private function spacing(LintContext $context, array $tokens, int $function, array $parentheses, ?array $use): void
    {
        $source = $context->file->contents;
        $keyword = $tokens[$function];
        $after = $tokens[$function + 1];
        $spaces = 0;
        $piece = null;
        if ($after->code === 'T_WHITESPACE') {
            $piece = FunctionTokens::piece($after, $source, last: false);
            $spaces = $piece[3] ? -1 : $piece[2];
        }

        if ($spaces !== 1) {
            $found = $spaces === -1 ? 'newline' : (string) $spaces;
            $this->emit(
                $context,
                'SpaceAfterFunction',
                "Expected 1 space after FUNCTION keyword; {$found} found",
                $keyword->pos,
                $piece === null
                    ? [TextEdit::insert($keyword->pos + strlen($keyword->text), ' ')]
                    : [TextEdit::replace(new Span($piece[0], $piece[1]), ' ')],
            );
        }

        [$open, $close] = $parentheses;
        if ($keyword->code === 'T_FUNCTION') {
            $this->beforeParenthesis($context, $tokens, $open);
            if ($keyword->scopeOpener === null) {
                $this->beforeSemicolon($context, $tokens, $close);
            }
        }

        if ($use !== null) {
            $this->aroundUse($context, $tokens, $use[0]);
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function beforeParenthesis(LintContext $context, array $tokens, int $open): void
    {
        $before = $tokens[$open - 1];
        if ($before->code !== 'T_WHITESPACE') {
            return;
        }

        $piece = FunctionTokens::piece($before, $context->file->contents, last: true);
        $found = $piece[3] ? 'newline' : (string) $piece[2];
        $this->emit(
            $context,
            'SpaceBeforeOpenParen',
            "Expected 0 spaces before opening parenthesis; {$found} found",
            $tokens[$open]->pos,
            [TextEdit::delete(new Span($piece[0], $piece[1]))],
        );
    }

    /** @param list<PhpcsToken> $tokens */
    private function beforeSemicolon(LintContext $context, array $tokens, int $close): void
    {
        for ($end = $close, $count = count($tokens); $end < $count; $end++) {
            if ($tokens[$end]->code !== 'T_SEMICOLON') {
                continue;
            }

            $before = $tokens[$end - 1];
            if ($before->code === 'T_WHITESPACE') {
                $piece = FunctionTokens::piece($before, $context->file->contents, last: true);
                $found = $piece[3] ? 'newline' : (string) $piece[2];
                $this->emit(
                    $context,
                    'SpaceBeforeSemicolon',
                    "Expected 0 spaces before semicolon; {$found} found",
                    $tokens[$end]->pos,
                    [TextEdit::delete(new Span($piece[0], $piece[1]))],
                );
            }

            return;
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function aroundUse(LintContext $context, array $tokens, int $use): void
    {
        $source = $context->file->contents;
        $keyword = $tokens[$use];
        $after = $tokens[$use + 1];
        $piece = $after->code === 'T_WHITESPACE' ? FunctionTokens::piece($after, $source, last: false) : null;
        $length = $piece === null ? 0 : $piece[2];
        if ($length !== 1) {
            $this->emit(
                $context,
                'SpaceAfterUse',
                "Expected 1 space after USE keyword; found {$length}",
                $keyword->pos,
                $piece === null
                    ? [TextEdit::insert($keyword->pos + strlen($keyword->text), ' ')]
                    : [TextEdit::replace(new Span($piece[0], $piece[1]), ' ')],
            );
        }

        $before = $tokens[$use - 1];
        $piece = $before->code === 'T_WHITESPACE' ? FunctionTokens::piece($before, $source, last: true) : null;
        $length = $piece === null ? 0 : $piece[2];
        if ($length !== 1) {
            $this->emit(
                $context,
                'SpaceBeforeUse',
                "Expected 1 space before USE keyword; found {$length}",
                $keyword->pos,
                $piece === null
                    ? [TextEdit::insert($keyword->pos, ' ')]
                    : [TextEdit::replace(new Span($piece[0], $piece[1]), ' ')],
            );
        }
    }

    /**
     * Multi-line as this sniff defines it: the first parameter is not on the line of the keyword, or
     * a later one is not on the line of the comma before it, or `)` is not on the line of the last.
     * A closure's `use` clause is looked at the same way.
     *
     * @param list<PhpcsToken> $tokens
     * @param null|array{int, int, int} $use
     */
    private function isMultiLine(array $tokens, int $function, int $open, ?array $use): bool
    {
        $brackets = [[$function, $open]];
        if ($use !== null) {
            $brackets[] = [$use[0], $use[1]];
        }

        foreach ($brackets as [$owner, $bracket]) {
            $next = PhpcsTokenStream::next($tokens, $bracket);
            if ($next === null || $tokens[$next]->line !== $tokens[$owner]->line) {
                return true;
            }

            $close = $tokens[$bracket]->closer;
            if ($close === null) {
                continue;
            }

            $end = FunctionTokens::endOfStatement($tokens, $bracket + 1);
            while ($tokens[$end]->code === 'T_COMMA') {
                $next = PhpcsTokenStream::next($tokens, $end, $close);
                if ($next === null) {
                    continue 2;
                }

                if ($tokens[$next]->line !== $tokens[$end]->line) {
                    return true;
                }

                $end = FunctionTokens::endOfStatement($tokens, $next);
            }

            $next = PhpcsTokenStream::next($tokens, $end, $close);
            if ($next !== null && $tokens[$next]->line !== $tokens[$end]->line) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<PhpcsToken> $tokens
     * @param array{int, int} $parentheses
     */
    private function singleLine(LintContext $context, array $tokens, int $function, array $parentheses): void
    {
        if ($tokens[$function]->code === 'T_CLOSURE') {
            KernighanRitchieBrace::check($this->report, $context, $tokens, $function, self::SNIFF, contentAfter: true);
        } else {
            KernighanRitchieBrace::contentAfter(
                $this->report,
                $context,
                $tokens,
                $function,
                self::SNIFF,
                closeTagExempt: false,
            );
        }

        [$open, $close] = $parentheses;
        $prev = FunctionTokens::previousNonWhitespace($tokens, $close, $open);
        if ($prev === null || $tokens[$prev]->line === $tokens[$close]->line) {
            return;
        }

        $edits = [];
        if (!FunctionTokens::isComment($tokens[$prev])) {
            for ($i = $close - 1; $i > $open && $tokens[$i]->code === 'T_WHITESPACE'; $i--) {
                $edits[] = TextEdit::delete(new Span($tokens[$i]->pos, $tokens[$i]->pos + strlen($tokens[$i]->text)));
            }
        }

        $this->emit(
            $context,
            'CloseBracketNewLine',
            'There must not be a newline before the closing parenthesis of a single-line function declaration',
            $tokens[$close]->pos,
            $edits,
        );
    }

    /**
     * @param list<PhpcsToken> $tokens
     * @param array{int, int} $parentheses
     * @param null|array{int, int, int} $use
     */
    private function multiLine(
        LintContext $context,
        array $tokens,
        int $function,
        array $parentheses,
        ?array $use,
    ): void {
        [$open, $close] = $parentheses;
        $last = $use === null ? $close : $use[2];
        $this->closingLine($context, $tokens, $open, $close, 'CloseBracketLine', 'function');
        if ($use !== null) {
            $this->closingLine($context, $tokens, $use[1], $use[2], 'UseCloseBracketLine', 'use');
        }

        $this->indentation($context, $tokens, $function, $open, $close, $last);
        $this->openingBrace($context, $tokens, $function, $last);
        $this->firstParameterAndCommas($context, $tokens, $open, 'function', '');
        if ($use !== null) {
            $this->firstParameterAndCommas($context, $tokens, $use[1], 'use', 'Use');
        }
    }

    /**
     * @param list<PhpcsToken> $tokens
     *
     * @mago-expect lint:excessive-parameter-list
     */
    private function closingLine(
        LintContext $context,
        array $tokens,
        int $open,
        int $close,
        string $code,
        string $type,
    ): void {
        $prev = FunctionTokens::previousNonWhitespace($tokens, $close);
        if (
            $tokens[$close]->line === $tokens[$open]->line
            || $prev === null
            || FunctionTokens::endLine($tokens[$prev]) !== $tokens[$close]->line
        ) {
            return;
        }

        $this->emit(
            $context,
            $code,
            "The closing parenthesis of a multi-line {$type} declaration must be on a new line",
            $tokens[$close]->pos,
            [TextEdit::insert($tokens[$close]->pos, FunctionTokens::eol($context->file->contents))],
        );
    }

    /**
     * Each line between the parentheses is indented one level past the declaration, and none is
     * blank. A nested call, array or attribute has indentation rules of its own and is skipped.
     *
     * @param list<PhpcsToken> $tokens
     * @mago-expect lint:excessive-parameter-list
     */
    private function indentation(
        LintContext $context,
        array $tokens,
        int $function,
        int $open,
        int $paramsClose,
        int $last,
    ): void {
        $source = $context->file->contents;
        $lineStart = $tokens[$function]->pos;
        $newline = strrpos($source, "\n", $lineStart - strlen($source) - 1);
        $lineStart = $newline === false || $lineStart === 0 ? 0 : $newline + 1;
        $leading = '';
        for ($at = $lineStart; isset($source[$at]) && ($source[$at] === ' ' || $source[$at] === "\t"); $at++) {
            $leading .= $source[$at];
        }

        $functionIndent = FunctionTokens::indentWidth($leading);
        $tabs = str_contains($leading, "\t");
        $lastLine = $tokens[$open]->line;
        for ($i = $open + 1; $i < $last; $i++) {
            $token = $tokens[$i];
            if ($token->code === 'T_WHITESPACE') {
                $this->whitespaceLines($context, $tokens, $i, $lastLine, $functionIndent, $tabs, [$paramsClose, $last]);
                continue;
            }

            if ($token->line !== $lastLine) {
                $expected = $i === $paramsClose ? $functionIndent : $functionIndent + self::INDENT;
                if ($expected !== 0) {
                    $this->emit(
                        $context,
                        'Indent',
                        "Multi-line function declaration not indented correctly; expected {$expected} spaces but found 0",
                        $token->pos,
                        [TextEdit::insert($token->pos, self::indent($expected, $tabs))],
                    );
                }

                $lastLine = $token->line;
            }

            if ($token->code === 'T_DOC_COMMENT') {
                $this->docCommentLines($context, $token, $functionIndent + self::INDENT, $tabs);
            }

            $lastLine = max($lastLine, FunctionTokens::endLine($token));
            $skip = match (true) {
                $token->closer === null => null,
                $token->code === 'T_OPEN_PARENTHESIS' => self::afterUse($tokens, $i) ? null : $token->closer,
                $token->code === 'T_OPEN_SHORT_ARRAY', $token->code === 'T_ATTRIBUTE' => $token->closer,
                default => null,
            };
            if ($skip !== null) {
                $i = $skip;
                $lastLine = $tokens[$i]->line;
            }
        }
    }

    /**
     * The lines a run of whitespace starts: a blank one is reported, one with indentation is checked.
     *
     * @param list<PhpcsToken> $tokens
     * @param array{int, int} $closers
     * @mago-expect lint:excessive-parameter-list
     */
    private function whitespaceLines(
        LintContext $context,
        array $tokens,
        int $at,
        int &$lastLine,
        int $functionIndent,
        bool $tabs,
        array $closers,
    ): void {
        $whitespace = $tokens[$at];
        $text = $whitespace->text;
        $newline = strpos($text, "\n");
        if ($newline === false) {
            return;
        }

        // The first piece ends the line the run starts on; each one after it is the first token of a line.
        $offset = $newline + 1;
        $line = $whitespace->line + 1;
        while (($end = strpos($text, "\n", $offset)) !== false) {
            $this->emit(
                $context,
                'EmptyLine',
                'Blank lines are not allowed in a multi-line function declaration',
                $whitespace->pos + $offset,
                [TextEdit::delete(new Span($whitespace->pos + $offset, $whitespace->pos + $end + 1))],
            );
            $offset = $end + 1;
            ++$line;
        }

        $indent = substr($text, $offset);
        if ($indent === '') {
            return;
        }

        $expected = in_array($at + 1, $closers, strict: true) ? $functionIndent : $functionIndent + self::INDENT;
        $found = FunctionTokens::indentWidth($indent);
        if ($expected !== $found) {
            $this->emit(
                $context,
                'Indent',
                "Multi-line function declaration not indented correctly; expected {$expected} spaces but found {$found}",
                $whitespace->pos + $offset,
                [
                    TextEdit::replace(
                        new Span($whitespace->pos + $offset, $whitespace->pos + strlen($text)),
                        self::indent($expected, $tabs),
                    ),
                ],
            );
        }

        $lastLine = $line;
    }

    /**
     * The lines of a doc comment after its first: the whitespace before the `*` belongs to the
     * comment and is one space deeper than the comment's own indentation.
     */
    private function docCommentLines(LintContext $context, PhpcsToken $comment, int $base, bool $tabs): void
    {
        $text = $comment->text;
        $offset = 0;
        while (($newline = strpos($text, "\n", $offset)) !== false) {
            $offset = $newline + 1;
            $indent = substr($text, $offset, strspn($text, " \t", $offset));
            $expected = $indent === '' ? $base : $base + 1;
            $found = FunctionTokens::indentWidth($indent);
            if ($expected === $found) {
                continue;
            }

            $this->emit(
                $context,
                'Indent',
                "Multi-line function declaration not indented correctly; expected {$expected} spaces but found {$found}",
                $comment->pos + $offset,
                [
                    TextEdit::replace(
                        new Span($comment->pos + $offset, $comment->pos + $offset + strlen($indent)),
                        self::indent($expected, $tabs),
                    ),
                ],
            );
        }
    }

    /**
     * Whether the `(` follows `use`.
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function afterUse(array $tokens, int $paren): bool
    {
        $prev = PhpcsTokenStream::previous($tokens, $paren);

        return $prev !== null && $tokens[$prev]->code === 'T_USE';
    }

    private static function indent(int $width, bool $tabs): string
    {
        return $tabs
            ? str_repeat("\t", max(0, intdiv($width, FunctionTokens::TAB_WIDTH)))
            . str_repeat(' ', max(0, $width % FunctionTokens::TAB_WIDTH))
            : str_repeat(' ', max(0, $width));
    }

    /**
     * The brace of a multi-line declaration is on the line of the last `)` (`NewlineBeforeOpenBrace`),
     * one space after it (`SpaceBeforeOpenBrace`).
     *
     * @param list<PhpcsToken> $tokens
     */
    private function openingBrace(LintContext $context, array $tokens, int $function, int $close): void
    {
        $opener = $tokens[$function]->scopeOpener;
        if ($opener === null) {
            return;
        }

        if ($tokens[$opener]->line !== $tokens[$close]->line) {
            $prev = self::previousCode($tokens, $opener, $close);
            $sameLine = $prev === null || $tokens[$prev]->line === $tokens[$opener]->line;
            // A return type that ends on the line of the brace cannot be fixed.
            $this->emit(
                $context,
                'NewlineBeforeOpenBrace',
                'The closing parenthesis and the opening brace of a multi-line function declaration must be on the same line',
                $tokens[$opener]->pos,
                $prev === null || $sameLine ? [] : self::moveBrace($tokens, $prev, $opener),
            );
            if (!$sameLine) {
                return;
            }
        }

        $before = $tokens[$opener - 1];
        $piece = $before->code === 'T_WHITESPACE'
            ? FunctionTokens::piece($before, $context->file->contents, last: true)
            : null;
        $length = $piece === null ? 0 : $piece[2];
        if ($length === 1) {
            return;
        }

        $this->emit(
            $context,
            'SpaceBeforeOpenBrace',
            "There must be a single space between the closing parenthesis/return type and the opening brace of a multi-line function declaration; found {$length} spaces",
            $piece === null ? $before->pos : $piece[0],
            [
                $piece === null
                    ? TextEdit::insert($tokens[$opener]->pos, ' ')
                    : TextEdit::replace(new Span($piece[0], $piece[1]), ' '),
            ],
        );
    }

    /**
     * The previous non-empty token before $from, not looking before $floor.
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function previousCode(array $tokens, int $from, int $floor): ?int
    {
        for ($i = $from - 1; $i >= $floor; $i--) {
            if (!isset(PhpcsTokenStream::EMPTY[$tokens[$i]->code])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The fix for a brace on a line of its own after a multi-line declaration: it joins the line of
     * the end of the declaration, and a line it leaves empty goes.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<TextEdit>
     */
    private static function moveBrace(array $tokens, int $prev, int $opener): array
    {
        $edits = [TextEdit::insert($tokens[$prev]->pos + strlen($tokens[$prev]->text), ' {')];
        $before = FunctionTokens::previousNonWhitespace($tokens, $opener);
        $after = FunctionTokens::nextNonWhitespace($tokens, $opener);
        $brace = $tokens[$opener];
        if (
            $before !== null
            && $after !== null
            && $tokens[$before]->line < $brace->line
            && $tokens[$after]->line > $brace->line
        ) {
            // The brace has a line to itself: the indentation before it, the brace and its line break go.
            $space = $tokens[$opener - 1];
            $from = $brace->pos;
            if ($space->code === 'T_WHITESPACE') {
                $last = strrpos($space->text, "\n");
                $from = $space->pos + ($last === false ? 0 : $last + 1);
            }

            $next = $tokens[$opener + 1];
            $to = $brace->pos + 1;
            if ($next->code === 'T_WHITESPACE') {
                $newline = strpos($next->text, "\n");
                $to = $next->pos + ($newline === false ? strlen($next->text) : $newline + 1);
            }

            $edits[] = TextEdit::delete(new Span($from, $to));

            return $edits;
        }

        $edits[] = TextEdit::delete(new Span($brace->pos, $brace->pos + 1));
        $next = $tokens[$opener + 1];
        if ($next->code === 'T_WHITESPACE' && $after !== null && $tokens[$after]->line === $brace->line) {
            $edits[] = TextEdit::delete(new Span($next->pos, $next->pos + strlen($next->text)));
        }

        return $edits;
    }

    /**
     * `FirstParamSpacing`: the first parameter of a multi-line list is on the line after `(`;
     * `OneParamPerLine`: no parameter shares a line with the comma before it.
     *
     * @param list<PhpcsToken> $tokens
     */
    private function firstParameterAndCommas(
        LintContext $context,
        array $tokens,
        int $open,
        string $type,
        string $prefix,
    ): void {
        $close = $tokens[$open]->closer;
        if ($close === null) {
            return;
        }

        $eol = FunctionTokens::eol($context->file->contents);
        $next = PhpcsTokenStream::next($tokens, $open);
        if (
            $tokens[$close]->line !== $tokens[$open]->line
            && $next !== null
            && $tokens[$next]->line === $tokens[$open]->line
        ) {
            $this->emit(
                $context,
                $prefix . 'FirstParamSpacing',
                "The first parameter of a multi-line {$type} declaration must be on the line after the opening bracket",
                $tokens[$next]->pos,
                [TextEdit::insert($tokens[$open]->pos + 1, $eol)],
            );
        }

        for ($i = $open + 1; $i < $close; $i++) {
            if ($tokens[$i]->closer !== null) {
                $i = $tokens[$i]->closer;
                continue;
            }

            if ($tokens[$i]->code !== 'T_COMMA') {
                continue;
            }

            $next = PhpcsTokenStream::next($tokens, $i);
            if ($next !== null && $tokens[$next]->line === $tokens[$i]->line) {
                $this->emit(
                    $context,
                    $prefix . 'OneParamPerLine',
                    "Multi-line {$type} declarations must define one parameter per line",
                    $tokens[$next]->pos,
                    [TextEdit::insert($tokens[$i]->pos + 1, $eol)],
                );
            }
        }
    }

    /** @param list<TextEdit> $edits */
    private function emit(LintContext $context, string $code, string $message, int $pos, array $edits): void
    {
        $this->report->issue(
            $context,
            FunctionTokens::withEdits(Issue::new($message, new Span($pos, $pos + 1)), $edits),
            [self::SNIFF . '.' . $code],
        );
    }
}
