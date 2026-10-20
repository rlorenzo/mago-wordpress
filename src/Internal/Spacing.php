<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function in_array;
use function max;
use function str_contains;
use function str_repeat;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function substr_count;

/**
 * Helpers for the whitespace sniffs, over {@see PhpcsTokenStream} tokens. They emulate what phpcs
 * sees where PHP's tokens differ: phpcs splits whitespace after each line break (so the first
 * piece ends at the first `\n`), expands tabs to the tab width of the WordPress ruleset, and
 * a `//` comment swallows its line break.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class Spacing
{
    public const TAB_WIDTH = 4;

    /** `Tokens::$castTokens` (as PHP names them). */
    public const CASTS = [
        'T_INT_CAST' => true,
        'T_DOUBLE_CAST' => true,
        'T_STRING_CAST' => true,
        'T_ARRAY_CAST' => true,
        'T_OBJECT_CAST' => true,
        'T_BOOL_CAST' => true,
        'T_UNSET_CAST' => true,
        'T_VOID_CAST' => true,
    ];

    private function __construct() {}

    /**
     * The file's phpcs-style tokens, shared with the other token-stream rules.
     *
     * @return list<PhpcsToken>
     */
    public static function tokens(LintContext $context): array
    {
        $file = $context->file;

        return FileCache::remember(
            $file,
            'phpcs-token-stream',
            static fn(): array => PhpcsTokenStream::fromSource($file->contents),
        );
    }

    /** @param list<PhpcsToken> $tokens */
    public static function isWs(array $tokens, int $i): bool
    {
        return isset($tokens[$i]) && $tokens[$i]->code === 'T_WHITESPACE';
    }

    /** `Tokens::$emptyTokens`: whitespace and comments. */
    public static function isEmpty(PhpcsToken $token): bool
    {
        return $token->code === 'T_WHITESPACE' || $token->code === 'T_COMMENT' || $token->code === 'T_DOC_COMMENT';
    }

    /** A `//` or `#` comment, which phpcs makes include its line break. */
    public static function isLineComment(PhpcsToken $token): bool
    {
        return $token->code === 'T_COMMENT' && ($token->text[0] === '#' || ($token->text[1] ?? '') === '/');
    }

    /**
     * The whitespace token right before token $i, as phpcs has it: none when a line comment is
     * the previous token and the whitespace is only its line break.
     *
     * @param list<PhpcsToken> $tokens
     */
    public static function wsBefore(array $tokens, int $i): ?PhpcsToken
    {
        if (!self::isWs($tokens, $i - 1)) {
            return null;
        }

        $ws = $tokens[$i - 1];
        if (
            ($ws->text === "\n" || $ws->text === "\r\n")
            && isset($tokens[$i - 2])
            && self::isLineComment($tokens[$i - 2])
        ) {
            return null;
        }

        return $ws;
    }

    /** Whether the whitespace has a line break: phpcs would report the next token on another line. */
    public static function hasNewline(PhpcsToken $ws): bool
    {
        return str_contains($ws->text, "\n");
    }

    /**
     * The whitespace's first phpcs token with tabs expanded: up to and including the first line break.
     */
    public static function firstPiece(string $source, PhpcsToken $ws): string
    {
        $text = $ws->text;
        $newline = strpos($text, "\n");
        if ($newline !== false) {
            $text = substr($text, 0, $newline + 1);
        }

        return self::expand($source, $text, $ws->pos);
    }

    /** The whitespace's last phpcs token (after its last line break) with tabs expanded. */
    public static function lastPiece(PhpcsToken $ws): string
    {
        $newline = strrpos($ws->text, "\n");

        return $newline === false ? '' : self::expand('', substr($ws->text, $newline + 1), 0);
    }

    /** Width of the whitespace on one line, tabs counting to the next tab stop. */
    public static function width(string $source, PhpcsToken $ws): int
    {
        return strlen(self::expand($source, $ws->text, $ws->pos));
    }

    /** Replaces each tab by the spaces to the next tab stop, from the column $pos has in $source. */
    public static function expand(string $source, string $text, int $pos): string
    {
        if (!str_contains($text, "\t")) {
            return $text;
        }

        $column = 0;
        if ($source !== '' && $pos > 0) {
            $newline = strrpos($source, "\n", $pos - strlen($source) - 1);
            $start = $newline === false ? 0 : $newline + 1;
            $prefix = substr($source, $start, $pos - $start);
            for ($k = 0, $length = strlen($prefix); $k < $length; $k++) {
                $column += $prefix[$k] === "\t" ? self::TAB_WIDTH - ($column % self::TAB_WIDTH) : 1;
            }
        }

        $out = '';
        for ($k = 0, $length = strlen($text); $k < $length; $k++) {
            if ($text[$k] === "\t") {
                $step = self::TAB_WIDTH - ($column % self::TAB_WIDTH);
                $out .= str_repeat(' ', max(0, $step));
                $column += $step;
                continue;
            }

            $out .= $text[$k];
            $column = $text[$k] === "\n" ? 0 : $column + 1;
        }

        return $out;
    }

    /**
     * The gap on one side of token $i: 0 without whitespace, `'newline'` when it holds a line
     * break, else its width.
     *
     * @param list<PhpcsToken> $tokens
     */
    public static function gap(string $source, array $tokens, int $i, bool $after): int|string
    {
        $ws = $after ? $tokens[$i + 1] ?? null : self::wsBefore($tokens, $i);
        if ($ws === null || $ws->code !== 'T_WHITESPACE') {
            return 0;
        }

        // phpcs: the token after the whitespace piece (or the one before it) is on another line.
        $other = $tokens[$after ? $i + 2 : $i - 2] ?? null;
        if (
            self::hasNewline($ws)
            || $other !== null && ($after ? $other->line : self::endLine($other)) !== $tokens[$i]->line
        ) {
            return 'newline';
        }

        return self::width($source, $ws);
    }

    /** The last line of a token: phpcs splits a multi-line string or comment, so what follows is on its last piece's line. */
    public static function endLine(PhpcsToken $token): int
    {
        return $token->line + substr_count($token->text, "\n");
    }

    /** The span of a token. */
    public static function span(PhpcsToken $token): Span
    {
        return new Span($token->pos, $token->pos + strlen($token->text));
    }

    /** Replaces a token's text. */
    public static function replace(PhpcsToken $token, string $with): TextEdit
    {
        return TextEdit::replace(self::span($token), $with);
    }

    /**
     * Reports at the token, with the edits that fix it.
     *
     * @param non-empty-list<string> $codes
     * @param list<TextEdit> $edits
     * @mago-expect lint:excessive-parameter-list
     */
    public static function report(
        Report $report,
        LintContext $context,
        PhpcsToken $at,
        string $message,
        array $codes,
        array $edits = [],
    ): void {
        $issue = Issue::new($message, self::span($at));
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $report->issue($context, $issue, $codes);
    }

    /** Whether the code is one of the given. */
    public static function is(?PhpcsToken $token, string ...$codes): bool
    {
        return $token !== null && in_array($token->code, $codes, strict: true);
    }

    /**
     * Whether the `(` opens the parameters of a function, closure or arrow function.
     *
     * @param list<PhpcsToken> $t
     */
    public static function isFunctionParenthesis(array $t, int $open): bool
    {
        $prev = PhpcsTokenStream::previous($t, $open);
        if ($prev !== null && $t[$prev]->code === 'T_BITWISE_AND') {
            $prev = PhpcsTokenStream::previous($t, $prev);
        }

        if ($prev === null) {
            return false;
        }

        $code = $t[$prev]->code;
        if ($code === 'T_CLOSURE' || $code === 'T_FN' || $code === 'T_FUNCTION') {
            return true;
        }

        if ($code !== 'T_STRING') {
            return false;
        }

        $before = PhpcsTokenStream::previous($t, $prev);
        if ($before !== null && $t[$before]->code === 'T_BITWISE_AND') {
            $before = PhpcsTokenStream::previous($t, $before);
        }

        return $before !== null && $t[$before]->code === 'T_FUNCTION';
    }
}
