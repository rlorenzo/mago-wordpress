<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;

use function count;
use function preg_match;
use function rtrim;
use function str_contains;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function substr_count;

/**
 * Helpers over the phpcs-style tokens ({@see PhpcsTokenStream}) for the function layout sniffs:
 * the parentheses, brace and `use` clause of a declaration, `File::findEndOfStatement()`, and the
 * line and width arithmetic phpcs does on whitespace tokens (tabs count to the next multiple of 4).
 *
 * phpcs splits whitespace at every newline and gives the `//` comment its trailing newline; PHP's
 * tokenizer, and so the stream, keeps a run of whitespace as one token. The helpers say what a
 * sniff reads from such a run.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionTokens
{
    public const TAB_WIDTH = 4;

    private const END_OF_STATEMENT = [
        'T_COLON' => true,
        'T_COMMA' => true,
        'T_DOUBLE_ARROW' => true,
        'T_SEMICOLON' => true,
        'T_CLOSE_PARENTHESIS' => true,
        'T_CLOSE_SQUARE_BRACKET' => true,
        'T_CLOSE_CURLY_BRACKET' => true,
        'T_CLOSE_SHORT_ARRAY' => true,
        'T_OPEN_TAG' => true,
        'T_CLOSE_TAG' => true,
    ];

    private const CLOSERS = [
        'T_CLOSE_PARENTHESIS' => true,
        'T_CLOSE_SQUARE_BRACKET' => true,
        'T_CLOSE_CURLY_BRACKET' => true,
        'T_CLOSE_SHORT_ARRAY' => true,
        'T_OPEN_TAG' => true,
        'T_CLOSE_TAG' => true,
    ];

    private const COMMENTS = ['T_COMMENT' => true, 'T_DOC_COMMENT' => true];

    private function __construct() {}

    /**
     * The `(` and `)` of a `function`, closure or `fn` token, or NULL for `use function` and the like.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return null|array{int, int}
     */
    public static function parentheses(array $tokens, int $at): ?array
    {
        $open = PhpcsTokenStream::next($tokens, $at);
        if ($open !== null && $tokens[$open]->code === 'T_BITWISE_AND') {
            $open = PhpcsTokenStream::next($tokens, $open);
        }

        if ($open !== null && $tokens[$open]->code !== 'T_OPEN_PARENTHESIS' && $tokens[$at]->code === 'T_FUNCTION') {
            $open = PhpcsTokenStream::next($tokens, $open);
        }

        if ($open === null || $tokens[$open]->code !== 'T_OPEN_PARENTHESIS') {
            return null;
        }

        $close = $tokens[$open]->closer;

        return $close === null ? null : [$open, $close];
    }

    /**
     * The `use` keyword, `(` and `)` of a closure's `use` clause, or NULL.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return null|array{int, int, int}
     */
    public static function useClause(array $tokens, int $close, ?int $scopeOpener): ?array
    {
        if ($scopeOpener === null) {
            return null;
        }

        for ($i = $close + 1; $i < $scopeOpener; $i++) {
            if ($tokens[$i]->code !== 'T_USE') {
                continue;
            }

            $open = $i;
            while (++$open < $scopeOpener && $tokens[$open]->code !== 'T_OPEN_PARENTHESIS') {
                // The clause's own parenthesis.
            }

            $end = $open < $scopeOpener ? $tokens[$open]->closer : null;

            return $end === null ? null : [$i, $open, $end];
        }

        return null;
    }

    /**
     * `File::findEndOfStatement()`, optionally without `:` as an end.
     *
     * @param list<PhpcsToken> $tokens
     */
    public static function endOfStatement(array $tokens, int $start, bool $colonEnds = true): int
    {
        $last = $start;
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $code = $tokens[$i]->code;
            if ($i !== $start && isset(self::END_OF_STATEMENT[$code]) && ($colonEnds || $code !== 'T_COLON')) {
                return isset(self::CLOSERS[$code]) ? $last : $i;
            }

            $scope = $tokens[$i]->scopeCloser;
            if ($scope !== null) {
                if ($code === 'T_FN') {
                    $last = $scope;
                    $i = $scope - 1;
                    continue;
                }

                if ($i === $start) {
                    return $scope;
                }

                $i = $scope;
            } elseif ($tokens[$i]->closer !== null && $code !== 'T_ATTRIBUTE') {
                // The sniff does not skip attributes.
                $i = $tokens[$i]->closer;
            }

            if (!isset(PhpcsTokenStream::EMPTY[$tokens[$i]->code])) {
                $last = $i;
            }
        }

        return $count - 1;
    }

    /** The line a token ends on: a comment, string or heredoc line may span lines, a trailing newline does not count. */
    public static function endLine(PhpcsToken $token): int
    {
        $text = $token->text;
        if (!str_contains($text, "\n")) {
            return $token->line;
        }

        $inner = substr($text, 0, strlen($text) - (substr($text, -1) === "\n" ? 1 : 0));

        return $token->line + substr_count($inner, "\n");
    }

    /** Whether a run of whitespace has a line break. */
    public static function hasNewline(PhpcsToken $token): bool
    {
        return str_contains($token->text, "\n");
    }

    /**
     * The display width of a whitespace token that has no newline, as phpcs gives its `length` with a
     * tab width of 4: each tab reaches the next multiple of 4 from the column the token starts at.
     */
    public static function width(PhpcsToken $token, string $source): int
    {
        $text = $token->text;
        if (!str_contains($text, "\t")) {
            return strlen($text);
        }

        $column = self::column($source, $token->pos);
        $width = 0;
        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            $width += $text[$i] === "\t" ? self::TAB_WIDTH - (($column + $width) % self::TAB_WIDTH) : 1;
        }

        return $width;
    }

    /** The width of leading whitespace read from column 0. */
    public static function indentWidth(string $whitespace): int
    {
        $width = 0;
        for ($i = 0, $length = strlen($whitespace); $i < $length; $i++) {
            $width += $whitespace[$i] === "\t" ? self::TAB_WIDTH - ($width % self::TAB_WIDTH) : 1;
        }

        return $width;
    }

    /** The zero-based display column of a byte offset. */
    public static function column(string $source, int $offset): int
    {
        $newline = $offset === 0 ? false : strrpos($source, "\n", $offset - strlen($source) - 1);
        $start = $newline === false ? 0 : $newline + 1;
        $column = 0;
        for ($i = $start; $i < $offset; $i++) {
            $column += $source[$i] === "\t" ? self::TAB_WIDTH - ($column % self::TAB_WIDTH) : 1;
        }

        return $column;
    }

    /** The first line break a whitespace run holds, as phpcs gives it to the previous token, and what follows the last. */
    public static function collapseBlankLines(string $whitespace): string
    {
        $first = strpos($whitespace, "\n");
        $last = strrpos($whitespace, "\n");

        return $first === false || $last === false
            ? $whitespace
            : substr($whitespace, 0, $first + 1) . substr($whitespace, $last + 1);
    }

    /**
     * The previous token that is not whitespace (comments count), or NULL.
     *
     * @param list<PhpcsToken> $tokens
     */
    public static function previousNonWhitespace(array $tokens, int $from, int $floor = 0): ?int
    {
        for ($i = $from - 1; $i >= $floor; $i--) {
            if ($tokens[$i]->code !== 'T_WHITESPACE') {
                return $i;
            }
        }

        return null;
    }

    /**
     * The next token that is not whitespace (comments count), or NULL.
     *
     * @param list<PhpcsToken> $tokens
     */
    public static function nextNonWhitespace(array $tokens, int $from, ?int $end = null): ?int
    {
        $end ??= count($tokens);
        for ($i = $from + 1; $i < $end; $i++) {
            if ($tokens[$i]->code !== 'T_WHITESPACE') {
                return $i;
            }
        }

        return null;
    }

    public static function isComment(PhpcsToken $token): bool
    {
        return isset(self::COMMENTS[$token->code]);
    }

    /** A `// phpcs:ignore` style comment, which phpcs gives a token type of its own. */
    public static function isPhpcsAnnotation(PhpcsToken $token): bool
    {
        return (
            isset(self::COMMENTS[$token->code])
            && preg_match('~^(?://|\#|/\*+)\s*@?phpcs:(?:ignore|ignoreFile|disable|enable|set)\b~i', $token->text) === 1
        );
    }

    /**
     * The first or last of the tokens phpcs makes of a run of whitespace (each ends at a line break, the
     * last may be the indentation after it): its byte range, its `length` (tabs to the next multiple of
     * 4, the line break not counted) and whether it is a line break alone.
     *
     * @return array{int, int, int, bool}
     */
    public static function piece(PhpcsToken $whitespace, string $source, bool $last): array
    {
        $text = $whitespace->text;
        $first = strpos($text, "\n");
        if ($last) {
            $newline = strrpos(substr($text, 0, strlen($text) - (substr($text, -1) === "\n" ? 1 : 0)), "\n");
            $from = $newline === false ? 0 : $newline + 1;
            $to = strlen($text);
        } else {
            $from = 0;
            $to = $first === false ? strlen($text) : $first + 1;
        }

        $segment = substr($text, $from, $to - $from);
        $body = rtrim($segment, "\r\n");
        $column = $from === 0 ? self::column($source, $whitespace->pos) : 0;
        $length = 0;
        for ($i = 0, $size = strlen($body); $i < $size; $i++) {
            $length += $body[$i] === "\t" ? self::TAB_WIDTH - (($column + $length) % self::TAB_WIDTH) : 1;
        }

        return [$whitespace->pos + $from, $whitespace->pos + $to, $length, $body === '' && $segment !== ''];
    }

    /** The line break of the file. */
    public static function eol(string $source): string
    {
        return str_contains($source, "\r\n") ? "\r\n" : "\n";
    }

    /**
     * @param list<TextEdit> $edits
     */
    public static function withEdits(Issue $issue, array $edits): Issue
    {
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        return $issue;
    }
}
