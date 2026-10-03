<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use PhpToken;

use function array_pop;
use function count;
use function explode;
use function in_array;
use function preg_match;
use function preg_split;
use function str_contains;
use function strlen;
use function strtolower;
use function strtoupper;

use const PREG_SPLIT_NO_EMPTY;

/**
 * A file's tokens as phpcs 3 sees them, for the sniffs that walk tokens rather than a
 * syntax tree (`WordPress.Security.EscapeOutput`), with matched brackets and scopes (PhpcsToken).
 *
 * Differences from PHP's own tokenizer reproduced here: names are split at `\`,
 * `true`/`false`/`null` get their own codes, a double-quoted string with interpolation is one
 * `T_DOUBLE_QUOTED_STRING`, heredoc bodies are one token per line, `?`/`:` of a ternary are
 * `T_INLINE_THEN`/`T_INLINE_ELSE`, `[` is a short array unless it follows something
 * indexable, `=>` in a match body is `T_MATCH_ARROW`, and `function (`/`new class` are
 * `T_CLOSURE`/`T_ANON_CLASS`.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PhpcsTokenStream
{
    /** Token ids of the `"` and `` ` `` that open an interpolated string. */
    private const ID_DOUBLE_QUOTE = 34;

    private const ID_BACKTICK = 96;

    public const EMPTY = ['T_WHITESPACE' => true, 'T_COMMENT' => true, 'T_DOC_COMMENT' => true];

    private const CHARS = [
        '(' => 'T_OPEN_PARENTHESIS',
        ')' => 'T_CLOSE_PARENTHESIS',
        '[' => 'T_OPEN_SQUARE_BRACKET',
        ']' => 'T_CLOSE_SQUARE_BRACKET',
        '{' => 'T_OPEN_CURLY_BRACKET',
        '}' => 'T_CLOSE_CURLY_BRACKET',
        ';' => 'T_SEMICOLON',
        ',' => 'T_COMMA',
        '.' => 'T_STRING_CONCAT',
        '?' => 'T_INLINE_THEN',
        ':' => 'T_COLON',
        '!' => 'T_BOOLEAN_NOT',
        '+' => 'T_PLUS',
        '-' => 'T_MINUS',
        '*' => 'T_MULTIPLY',
        '/' => 'T_DIVIDE',
        '%' => 'T_MODULUS',
        '^' => 'T_BITWISE_XOR',
        '|' => 'T_BITWISE_OR',
        '&' => 'T_BITWISE_AND',
        '<' => 'T_LESS_THAN',
        '>' => 'T_GREATER_THAN',
        '=' => 'T_EQUAL',
        '~' => 'T_BITWISE_NOT',
        '@' => 'T_ASPERAND',
        '$' => 'T_DOLLAR',
    ];

    private const RENAMED = [
        'T_PAAMAYIM_NEKUDOTAYIM' => 'T_DOUBLE_COLON',
        'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG' => 'T_BITWISE_AND',
        'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' => 'T_BITWISE_AND',
    ];

    /** Tokens after which `[` indexes rather than opens a short array. */
    private const INDEXABLE = [
        'T_VARIABLE' => true,
        'T_CLOSE_SQUARE_BRACKET' => true,
        'T_CLOSE_SHORT_ARRAY' => true,
        'T_CLOSE_PARENTHESIS' => true,
        'T_CLOSE_CURLY_BRACKET' => true,
        'T_STRING' => true,
        'T_CONSTANT_ENCAPSED_STRING' => true,
        'T_DOUBLE_QUOTED_STRING' => true,
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_STATIC' => true,
        'T_CLASS_C' => true,
        'T_DIR' => true,
        'T_FILE' => true,
        'T_FUNC_C' => true,
        'T_LINE' => true,
        'T_METHOD_C' => true,
        'T_NS_C' => true,
        'T_TRAIT_C' => true,
    ];

    /** Tokens after which `?` is a nullable type, not a ternary. */
    private const BEFORE_NULLABLE = [
        'T_OPEN_PARENTHESIS' => true,
        'T_COMMA' => true,
        'T_COLON' => true,
        'T_PUBLIC' => true,
        'T_PROTECTED' => true,
        'T_PRIVATE' => true,
        'T_READONLY' => true,
        'T_STATIC' => true,
        'T_VAR' => true,
        'T_CONST' => true,
    ];

    private function __construct() {}

    /**
     * @return list<PhpcsToken>
     *
     * @mago-expect lint:halstead
     */
    public static function fromSource(string $source): array
    {
        $raw = PhpToken::tokenize($source);
        $count = count($raw);
        $tokens = [];
        for ($i = 0; $i < $count; $i++) {
            $token = $raw[$i];
            $text = $token->text;
            if ($token->id === self::ID_DOUBLE_QUOTE || $token->id === self::ID_BACKTICK) {
                // One token for the whole interpolated string, as phpcs reports it at its start.
                $content = $text;
                while (++$i < $count && $raw[$i]->id !== $token->id) {
                    $content .= $raw[$i]->text;
                }

                $content .= $i < $count ? $text : '';
                $tokens[] = new PhpcsToken(
                    $text === '"' ? 'T_DOUBLE_QUOTED_STRING' : 'T_BACKTICK',
                    $content,
                    $token->pos,
                    $token->line,
                );
                continue;
            }

            if ($token->id === T_START_HEREDOC) {
                $nowdoc = str_contains($text, "'");
                $tokens[] = new PhpcsToken(
                    $nowdoc ? 'T_START_NOWDOC' : 'T_START_HEREDOC',
                    $text,
                    $token->pos,
                    $token->line,
                );
                $body = '';
                $embeds = [];
                $bodyPos = $token->pos + strlen($text);
                while (++$i < $count && $raw[$i]->id !== T_END_HEREDOC) {
                    if (in_array($raw[$i]->id, [T_VARIABLE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], strict: true)) {
                        $embeds[] = strlen($body);
                    }

                    $body .= $raw[$i]->text;
                }

                $offset = 0;
                $parts = preg_split('/(?<=\n)/', $body, flags: PREG_SPLIT_NO_EMPTY);
                foreach ($parts === false ? [] : $parts as $index => $part) {
                    $line = new PhpcsToken(
                        $nowdoc ? 'T_NOWDOC' : 'T_HEREDOC',
                        $part,
                        $bodyPos + $offset,
                        $token->line + 1 + $index,
                    );
                    foreach ($embeds as $at) {
                        $line->embed = $line->embed || $at >= $offset && $at < ($offset + strlen($part));
                    }

                    $tokens[] = $line;
                    $offset += strlen($part);
                }

                if ($i < $count) {
                    $tokens[] = new PhpcsToken(
                        $nowdoc ? 'T_END_NOWDOC' : 'T_END_HEREDOC',
                        $raw[$i]->text,
                        $raw[$i]->pos,
                        $raw[$i]->line,
                    );
                }

                continue;
            }

            if (in_array($token->id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], strict: true)) {
                $pos = $token->pos;
                foreach (explode('\\', $text) as $index => $part) {
                    if ($index > 0) {
                        $tokens[] = new PhpcsToken('T_NS_SEPARATOR', '\\', $pos++, $token->line);
                    }

                    if ($part !== '') {
                        $code = match (true) {
                            $index === 0 && strtolower($part) === 'namespace' => 'T_NAMESPACE',
                            // `\exit()`/`\die()`, callable as functions since PHP 8.4.
                            $token->id === T_NAME_FULLY_QUALIFIED
                                && in_array(strtolower($text), ['\\exit', '\\die'], strict: true)
                                => 'T_EXIT',
                            default => 'T_STRING',
                        };
                        $tokens[] = new PhpcsToken($code, $part, $pos, $token->line);
                        $pos += strlen($part);
                    }
                }

                continue;
            }

            $code = $token->id < 256 ? self::CHARS[$text] ?? $text : $token->getTokenName() ?? $text;
            $tokens[] = new PhpcsToken(self::RENAMED[$code] ?? $code, $text, $token->pos, $token->line);
        }

        return self::structure($tokens);
    }

    /**
     * Matches brackets and classifies the context-dependent tokens.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<PhpcsToken>
     *
     * @mago-expect lint:halstead
     */
    private static function structure(array $tokens): array
    {
        $count = count($tokens);
        /** @var list<int> $stack */
        $stack = [];
        $ternaries = [];
        $parens = 0;
        $prev = null;
        for ($i = 0; $i < $count; $i++) {
            $code = $tokens[$i]->code;
            $tokens[$i]->parens = $parens;
            $prevCode = $prev === null ? '' : $tokens[$prev]->code;
            if (
                $code !== 'T_STRING'
                && preg_match('/^[a-z_]+$/i', $tokens[$i]->content) === 1
                && in_array(
                    $prevCode,
                    ['T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON', 'T_FUNCTION', 'T_CONST'],
                    strict: true,
                )
            ) {
                // A keyword used as a member or function name (`function echo()`, `Foo::class`).
                $code = 'T_STRING';
                $tokens[$i]->code = $code;
            }

            switch ($code) {
                case 'T_STRING':
                    $lower = strtolower($tokens[$i]->content);
                    $isMember = in_array(
                        $prevCode,
                        ['T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON', 'T_FUNCTION', 'T_CONST'],
                        strict: true,
                    );
                    if (!$isMember && ($lower === 'true' || $lower === 'false' || $lower === 'null')) {
                        $tokens[$i]->code = 'T_' . strtoupper($lower);
                    }

                    break;
                case 'T_INLINE_THEN':
                    if ((self::BEFORE_NULLABLE[$prevCode] ?? null) !== null) {
                        $tokens[$i]->code = 'T_NULLABLE';
                        break;
                    }

                    $ternaries[] = count($stack);

                    break;
                case 'T_COLON':
                    if ($ternaries !== [] && $ternaries[count($ternaries) - 1] === count($stack)) {
                        array_pop($ternaries);
                        $tokens[$i]->code = 'T_INLINE_ELSE';
                    }

                    break;
                case 'T_OPEN_SQUARE_BRACKET':
                    if ((self::INDEXABLE[$prevCode] ?? null) === null) {
                        $tokens[$i]->code = 'T_OPEN_SHORT_ARRAY';
                    }

                    $stack[] = $i;
                    break;
                case 'T_OPEN_PARENTHESIS':
                    $parens++;
                    $stack[] = $i;
                    break;
                case 'T_OPEN_CURLY_BRACKET':
                case 'T_ATTRIBUTE':
                    $stack[] = $i;
                    break;
                case 'T_CLOSE_PARENTHESIS':
                case 'T_CLOSE_SQUARE_BRACKET':
                case 'T_CLOSE_CURLY_BRACKET':
                    $expected = match ($code) {
                        'T_CLOSE_PARENTHESIS' => ['T_OPEN_PARENTHESIS'],
                        'T_CLOSE_SQUARE_BRACKET' => ['T_OPEN_SQUARE_BRACKET', 'T_OPEN_SHORT_ARRAY', 'T_ATTRIBUTE'],
                        default => ['T_OPEN_CURLY_BRACKET'],
                    };
                    $open = $stack[count($stack) - 1] ?? null;
                    if ($open !== null && in_array($tokens[$open]->code, $expected, strict: true)) {
                        array_pop($stack);
                        while ($ternaries !== [] && $ternaries[count($ternaries) - 1] > count($stack)) {
                            array_pop($ternaries);
                        }

                        $tokens[$open]->closer = $i;
                        $tokens[$i]->opener = $open;
                        if ($tokens[$open]->code === 'T_OPEN_SHORT_ARRAY') {
                            $tokens[$i]->code = 'T_CLOSE_SHORT_ARRAY';
                        }

                        if ($code === 'T_CLOSE_PARENTHESIS') {
                            $parens--;
                            $tokens[$i]->parens = $parens;
                        }
                    }

                    break;
            }

            if ((self::EMPTY[$tokens[$i]->code] ?? null) === null) {
                $prev = $i;
            }
        }

        return self::scopes($tokens);
    }

    /**
     * Sets `scopeCloser` on the keywords whose scope the sniffs skip, turns `function (` into
     * `T_CLOSURE` and `=>` in a match body into `T_MATCH_ARROW`.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<PhpcsToken>
     *
     * @mago-expect lint:halstead
     */
    private static function scopes(array $tokens): array
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $code = $tokens[$i]->code;
            if ($code === 'T_CLASS' && self::isAnonymousClass($tokens, $i)) {
                $code = 'T_ANON_CLASS';
                $tokens[$i]->code = $code;
            }

            if ($code === 'T_FUNCTION') {
                $next = self::next($tokens, $i);
                $next = $next !== null && $tokens[$next]->code === 'T_BITWISE_AND' ? self::next($tokens, $next) : $next;
                if ($next !== null && $tokens[$next]->code === 'T_OPEN_PARENTHESIS') {
                    $code = 'T_CLOSURE';
                    $tokens[$i]->code = $code;
                }
            }

            if ($code === 'T_FN') {
                $tokens[$i]->scopeCloser = self::arrowFunctionEnd($tokens, $i);
                continue;
            }

            if (!in_array(
                $code,
                [
                    'T_FUNCTION',
                    'T_CLOSURE',
                    'T_CLASS',
                    'T_ANON_CLASS',
                    'T_INTERFACE',
                    'T_TRAIT',
                    'T_ENUM',
                    'T_MATCH',
                    'T_TRY',
                ],
                strict: true,
            )) {
                continue;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                if ($tokens[$j]->closer !== null && $tokens[$j]->code === 'T_OPEN_PARENTHESIS') {
                    $j = $tokens[$j]->closer;
                    continue;
                }

                if ($tokens[$j]->code === 'T_SEMICOLON') {
                    break;
                }

                if ($tokens[$j]->code !== 'T_OPEN_CURLY_BRACKET') {
                    continue;
                }

                if ($tokens[$j]->closer !== null) {
                    $tokens[$i]->scopeCloser = $tokens[$j]->closer;
                    $tokens[$i]->scopeOpener = $j;
                    if ($code === 'T_MATCH') {
                        self::matchArrows($tokens, $j);
                    }
                }

                break;
            }
        }

        return $tokens;
    }

    /**
     * Whether `class` follows `new`, past attributes and `readonly`.
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function isAnonymousClass(array $tokens, int $class): bool
    {
        $prev = self::previous($tokens, $class);
        while ($prev !== null) {
            $code = $tokens[$prev]->code;
            if (
                $code === 'T_CLOSE_SQUARE_BRACKET'
                && $tokens[$prev]->opener !== null
                && $tokens[$tokens[$prev]->opener]->code === 'T_ATTRIBUTE'
            ) {
                $prev = self::previous($tokens, $tokens[$prev]->opener);
                continue;
            }

            if ($code !== 'T_READONLY') {
                return $code === 'T_NEW';
            }

            $prev = self::previous($tokens, $prev);
        }

        return false;
    }

    /**
     * @param list<PhpcsToken> $tokens
     */
    private static function matchArrows(array &$tokens, int $opener): void
    {
        for ($k = $opener + 1; $k < (int) $tokens[$opener]->closer; $k++) {
            if ($tokens[$k]->closer !== null) {
                $k = $tokens[$k]->closer;
                continue;
            }

            if ($tokens[$k]->code === 'T_FN') {
                // The arrow function's `=>` is not a match arrow.
                $k = self::arrowFunctionEnd($tokens, $k);
                continue;
            }

            if ($tokens[$k]->code === 'T_DOUBLE_ARROW') {
                $tokens[$k]->code = 'T_MATCH_ARROW';
            }
        }
    }

    /**
     * The last token of an arrow function's expression.
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function arrowFunctionEnd(array $tokens, int $fn): int
    {
        $count = count($tokens);
        $arrow = $fn;
        while (++$arrow < $count && $tokens[$arrow]->code !== 'T_DOUBLE_ARROW') {
            if ($tokens[$arrow]->closer === null) {
                continue;
            }

            $arrow = $tokens[$arrow]->closer;
        }

        $last = $arrow;
        for ($i = $arrow + 1; $i < $count; $i++) {
            if ($tokens[$i]->closer !== null) {
                $i = $tokens[$i]->closer;
                $last = $i;
                continue;
            }

            if (in_array(
                $tokens[$i]->code,
                [
                    'T_SEMICOLON',
                    'T_COMMA',
                    'T_CLOSE_PARENTHESIS',
                    'T_CLOSE_SQUARE_BRACKET',
                    'T_CLOSE_SHORT_ARRAY',
                    'T_CLOSE_CURLY_BRACKET',
                    'T_CLOSE_TAG',
                ],
                strict: true,
            )) {
                break;
            }

            if ((self::EMPTY[$tokens[$i]->code] ?? null) === null) {
                $last = $i;
            }
        }

        return $last;
    }

    /**
     * @param list<PhpcsToken> $tokens
     */
    public static function next(array $tokens, int $from, ?int $end = null): ?int
    {
        $end ??= count($tokens);
        for ($i = $from + 1; $i < $end; $i++) {
            if ((self::EMPTY[$tokens[$i]->code] ?? null) === null) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<PhpcsToken> $tokens
     */
    public static function previous(array $tokens, int $from): ?int
    {
        for ($i = $from - 1; $i >= 0; $i--) {
            if ((self::EMPTY[$tokens[$i]->code] ?? null) === null) {
                return $i;
            }
        }

        return null;
    }
}
