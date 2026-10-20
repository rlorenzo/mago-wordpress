<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\Layout;

use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\FileCache;

use function array_keys;
use function array_pop;
use function array_reverse;
use function array_shift;
use function count;
use function end;
use function explode;
use function function_exists;
use function iconv_strlen;
use function implode;
use function in_array;
use function is_array;
use function key;
use function ltrim;
use function max;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * A file's tokens as phpcs 3 hands them to a sniff that reads layout: lines and columns (tabs counted as the
 * `WordPress` ruleset's tab width of 4), the parenthesis, bracket and scope maps, `level` and `conditions`,
 * and the token names phpcs gives (`T_CLOSURE`, `T_FN`, `T_OPEN_SHORT_ARRAY`, `T_PHPCS_IGNORE` ...). It
 * ports `PHP_CodeSniffer\Tokenizers\Tokenizer` (the maps) and the part of `PHP::processAdditional()` that
 * decides scopes; the PHP-token half is {@see LayoutTokenizer}.
 *
 * Doc blocks are cut down: the opening and closing tags, each line's indentation and the rest of the line.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class PhpcsLayout
{
    public const TAB_WIDTH = 4;

    /** @var array<string, true> `Tokens::$emptyTokens` */
    public const EMPTY = [
        'T_WHITESPACE' => true,
        'T_COMMENT' => true,
        'T_DOC_COMMENT' => true,
        'T_DOC_COMMENT_STAR' => true,
        'T_DOC_COMMENT_WHITESPACE' => true,
        'T_DOC_COMMENT_TAG' => true,
        'T_DOC_COMMENT_OPEN_TAG' => true,
        'T_DOC_COMMENT_CLOSE_TAG' => true,
        'T_DOC_COMMENT_STRING' => true,
        'T_PHPCS_ENABLE' => true,
        'T_PHPCS_DISABLE' => true,
        'T_PHPCS_SET' => true,
        'T_PHPCS_IGNORE' => true,
        'T_PHPCS_IGNORE_FILE' => true,
    ];

    /** @var array<string, true> `Tokens::$scopeOpeners` */
    public const SCOPE_OPENERS = [
        'T_CLASS' => true,
        'T_ANON_CLASS' => true,
        'T_INTERFACE' => true,
        'T_TRAIT' => true,
        'T_ENUM' => true,
        'T_NAMESPACE' => true,
        'T_FUNCTION' => true,
        'T_CLOSURE' => true,
        'T_IF' => true,
        'T_SWITCH' => true,
        'T_CASE' => true,
        'T_DECLARE' => true,
        'T_DEFAULT' => true,
        'T_WHILE' => true,
        'T_ELSE' => true,
        'T_ELSEIF' => true,
        'T_FOR' => true,
        'T_FOREACH' => true,
        'T_DO' => true,
        'T_TRY' => true,
        'T_CATCH' => true,
        'T_FINALLY' => true,
        'T_PROPERTY' => true,
        'T_OBJECT' => true,
        'T_USE' => true,
        'T_MATCH' => true,
    ];

    private const PARENTHESIS_OPENERS = [
        'T_ARRAY' => true,
        'T_LIST' => true,
        'T_FUNCTION' => true,
        'T_CLOSURE' => true,
        'T_ANON_CLASS' => true,
        'T_WHILE' => true,
        'T_FOR' => true,
        'T_FOREACH' => true,
        'T_SWITCH' => true,
        'T_IF' => true,
        'T_ELSEIF' => true,
        'T_CATCH' => true,
        'T_DECLARE' => true,
        'T_MATCH' => true,
    ];

    private const ASSIGNMENT = [
        'T_EQUAL' => true,
        'T_AND_EQUAL' => true,
        'T_OR_EQUAL' => true,
        'T_CONCAT_EQUAL' => true,
        'T_DIV_EQUAL' => true,
        'T_MINUS_EQUAL' => true,
        'T_POW_EQUAL' => true,
        'T_MOD_EQUAL' => true,
        'T_MUL_EQUAL' => true,
        'T_PLUS_EQUAL' => true,
        'T_XOR_EQUAL' => true,
        'T_DOUBLE_ARROW' => true,
        'T_SL_EQUAL' => true,
        'T_SR_EQUAL' => true,
        'T_COALESCE_EQUAL' => true,
        'T_ZSR_EQUAL' => true,
    ];

    private const TAB_TOKENS = [
        'T_WHITESPACE' => true,
        'T_COMMENT' => true,
        'T_DOC_COMMENT' => true,
        'T_DOC_COMMENT_WHITESPACE' => true,
        'T_DOC_COMMENT_STRING' => true,
        'T_CONSTANT_ENCAPSED_STRING' => true,
        'T_DOUBLE_QUOTED_STRING' => true,
        'T_START_HEREDOC' => true,
        'T_START_NOWDOC' => true,
        'T_HEREDOC' => true,
        'T_NOWDOC' => true,
        'T_END_HEREDOC' => true,
        'T_END_NOWDOC' => true,
        'T_INLINE_HTML' => true,
        'T_YIELD_FROM' => true,
    ];

    private const IF_ENDS = ['T_CLOSE_CURLY_BRACKET' => true, 'T_ENDIF' => true, 'T_ELSE' => true, 'T_ELSEIF' => true];

    private const CASE_ENDS = [
        'T_BREAK' => true,
        'T_RETURN' => true,
        'T_CONTINUE' => true,
        'T_THROW' => true,
        'T_EXIT' => true,
        'T_GOTO' => true,
    ];

    private const END_SCOPE = [
        'T_CLOSE_CURLY_BRACKET' => true,
        'T_ENDIF' => true,
        'T_ENDFOR' => true,
        'T_ENDFOREACH' => true,
        'T_ENDWHILE' => true,
        'T_ENDSWITCH' => true,
        'T_ENDDECLARE' => true,
        'T_BREAK' => true,
        'T_END_HEREDOC' => true,
        'T_END_NOWDOC' => true,
    ];

    /**
     * The tokenizer's `$scopeOpeners`: start tokens, end tokens, strict, shared, and the scopes it shares with.
     *
     * @var array<string, array{start: array<string, true>, end: array<string, true>, strict: bool, shared: bool, with: array<string, true>}>
     */
    private const SCOPES = [
        'T_IF' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => self::IF_ENDS,
            'strict' => false,
            'shared' => false,
            'with' => ['T_ELSE' => true, 'T_ELSEIF' => true],
        ],
        'T_TRY' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_CATCH' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_FINALLY' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_ELSE' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true, 'T_ENDIF' => true],
            'strict' => false,
            'shared' => false,
            'with' => ['T_IF' => true, 'T_ELSEIF' => true],
        ],
        'T_ELSEIF' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => self::IF_ENDS,
            'strict' => false,
            'shared' => false,
            'with' => ['T_IF' => true, 'T_ELSE' => true],
        ],
        'T_FOR' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true, 'T_ENDFOR' => true],
            'strict' => false,
            'shared' => false,
            'with' => [],
        ],
        'T_FOREACH' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true, 'T_ENDFOREACH' => true],
            'strict' => false,
            'shared' => false,
            'with' => [],
        ],
        'T_INTERFACE' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_FUNCTION' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_CLASS' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_TRAIT' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_ENUM' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_USE' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => false,
            'shared' => false,
            'with' => [],
        ],
        'T_DECLARE' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true, 'T_ENDDECLARE' => true],
            'strict' => false,
            'shared' => false,
            'with' => [],
        ],
        'T_NAMESPACE' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => false,
            'shared' => false,
            'with' => [],
        ],
        'T_WHILE' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true, 'T_ENDWHILE' => true],
            'strict' => false,
            'shared' => false,
            'with' => [],
        ],
        'T_DO' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_SWITCH' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true, 'T_COLON' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true, 'T_ENDSWITCH' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_CASE' => [
            'start' => ['T_COLON' => true, 'T_SEMICOLON' => true],
            'end' => self::CASE_ENDS,
            'strict' => true,
            'shared' => true,
            'with' => ['T_DEFAULT' => true, 'T_CASE' => true, 'T_SWITCH' => true],
        ],
        'T_DEFAULT' => [
            'start' => ['T_COLON' => true, 'T_SEMICOLON' => true],
            'end' => self::CASE_ENDS,
            'strict' => true,
            'shared' => true,
            'with' => ['T_CASE' => true, 'T_SWITCH' => true],
        ],
        'T_MATCH' => [
            'start' => ['T_OPEN_CURLY_BRACKET' => true],
            'end' => ['T_CLOSE_CURLY_BRACKET' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_START_HEREDOC' => [
            'start' => ['T_START_HEREDOC' => true],
            'end' => ['T_END_HEREDOC' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
        'T_START_NOWDOC' => [
            'start' => ['T_START_NOWDOC' => true],
            'end' => ['T_END_NOWDOC' => true],
            'strict' => true,
            'shared' => false,
            'with' => [],
        ],
    ];

    /** @var list<LayoutToken> */
    public array $tokens = [];

    public int $count = 0;

    private function __construct() {}

    /** Files with more tokens than this are skipped: the model costs about 600 bytes a token (a worker has 128M). */
    public const MAX_TOKENS = 100000;

    /** The tokenizer's own lists exhaust a worker on a dense file before the token count is known, so a big file is skipped by size. */
    public const MAX_BYTES = 300000;

    public static function of(SourceFile $file): ?self
    {
        return FileCache::remember($file, 'phpcs-layout', static fn(): ?self => self::build($file->contents));
    }

    public static function release(SourceFile $file): void
    {
        FileCache::forget($file, 'phpcs-layout');
    }

    public static function build(string $source): ?self
    {
        if (strlen($source) > self::MAX_BYTES) {
            return null;
        }

        $eol = preg_match('/\R/', $source, $match) === 1 ? $match[0] : "\n";
        [$codes, $texts] = LayoutTokenizer::tokenize($source, $eol);
        if (count($codes) > self::MAX_TOKENS) {
            return null;
        }

        $layout = new self();
        $layout->positions($codes, $texts, $eol);
        $layout->tokenMap();
        $layout->nestingMap();
        $layout->scopeMap();
        $layout->levelMap();
        $layout->additional();

        return $layout;
    }

    // --- token search, as File::find*() ---

    /**
     * @param array<string, mixed> $types token names
     */
    public function findNext(array $types, int $start, ?int $end = null, bool $exclude = false): ?int
    {
        $end ??= $this->count;
        for ($i = $start; $i < $end && $i < $this->count; $i++) {
            if (isset($types[$this->tokens[$i]->code]) !== $exclude) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $types token names
     */
    public function findPrevious(
        array $types,
        int $start,
        ?int $end = null,
        bool $exclude = false,
        bool $local = false,
    ): ?int {
        $end ??= 0;
        for ($i = $start; $i >= $end; $i--) {
            $token = $this->tokens[$i];
            if (isset($types[$token->code]) !== $exclude) {
                return $i;
            }

            if ($local) {
                if ($token->scopeOpener !== null && $i === $token->scopeCloser) {
                    $i = $token->scopeOpener;
                } elseif ($token->bracketOpener !== null && $i === $token->bracketCloser) {
                    $i = $token->bracketOpener;
                } elseif ($token->parenthesisOpener !== null && $i === $token->parenthesisCloser) {
                    $i = $token->parenthesisOpener;
                } elseif ($token->code === 'T_SEMICOLON') {
                    break;
                }
            }
        }

        return null;
    }

    /**
     * The first token on the line of $start that is not (or, with $exclude false, is) one of the types.
     *
     * @param array<string, mixed> $types
     */
    public function findFirstOnLine(array $types, int $start, bool $exclude = false): int|false
    {
        $found = false;
        $line = $this->tokens[$start]->line;
        for ($i = $start; $i >= 0; $i--) {
            if ($this->tokens[$i]->line < $line) {
                break;
            }

            if (isset($types[$this->tokens[$i]->code]) !== $exclude) {
                $found = $i;
            }
        }

        return $found;
    }

    /**
     * @param array<string, true>|string|null $ignore token names (the keys of a map) that are not stop points
     */
    public function findStartOfStatement(int $start, array|string|null $ignore = null): int
    {
        $startTokens = [
            'T_OPEN_CURLY_BRACKET' => true,
            'T_OPEN_SQUARE_BRACKET' => true,
            'T_OPEN_PARENTHESIS' => true,
            'T_OBJECT' => true,
            'T_OPEN_SHORT_ARRAY' => true,
            'T_OPEN_TAG' => true,
            'T_OPEN_TAG_WITH_ECHO' => true,
        ];
        $endTokens = [
            'T_CLOSE_TAG' => true,
            'T_COLON' => true,
            'T_COMMA' => true,
            'T_DOUBLE_ARROW' => true,
            'T_MATCH_ARROW' => true,
            'T_SEMICOLON' => true,
        ];
        foreach ($ignore === null ? [] : (is_array($ignore) ? array_keys($ignore) : [$ignore]) as $code) {
            $code = (string) $code;
            unset($startTokens[$code], $endTokens[$code]);
        }

        $tokens = $this->tokens;
        if ($tokens[$start]->conditions !== []) {
            $conditions = $tokens[$start]->conditions;
            $lastConditionOwner = end($conditions);
            $matchExpression = key($conditions);
            if (
                $lastConditionOwner === 'T_MATCH'
                && (
                    $tokens[$matchExpression]->nestedParenthesis === []
                    && $tokens[$start]->nestedParenthesis === []
                    || $tokens[$matchExpression]->nestedParenthesis !== []
                    && $tokens[$start]->nestedParenthesis !== []
                    && $tokens[$matchExpression]->nestedParenthesis === $tokens[$start]->nestedParenthesis
                )
            ) {
                $lastComma = null;
                $inNestedExpression = false;
                $opener = (int) $tokens[$matchExpression]->scopeOpener;
                for ($prevMatch = $start; $prevMatch > $opener; $prevMatch--) {
                    if ($prevMatch !== $start && $tokens[$prevMatch]->code === 'T_MATCH_ARROW') {
                        break;
                    }

                    if ($prevMatch !== $start && $tokens[$prevMatch]->code === 'T_COMMA') {
                        $lastComma = $prevMatch;
                        continue;
                    }

                    if (
                        $tokens[$prevMatch]->bracketOpener !== null
                        && $prevMatch === $tokens[$prevMatch]->bracketCloser
                    ) {
                        $prevMatch = $tokens[$prevMatch]->bracketOpener;
                        continue;
                    }

                    if (
                        $tokens[$prevMatch]->parenthesisOpener !== null
                        && $prevMatch === $tokens[$prevMatch]->parenthesisCloser
                    ) {
                        $prevMatch = $tokens[$prevMatch]->parenthesisOpener;
                        continue;
                    }

                    if (
                        $tokens[$prevMatch]->bracketOpener !== null
                        && (int) $tokens[$prevMatch]->bracketCloser > $start
                    ) {
                        $inNestedExpression = true;
                        break;
                    }
                }

                if (!$inNestedExpression) {
                    if ($prevMatch <= $opener) {
                        return $this->findNext(self::EMPTY, $opener + 1, null, true) ?? $start;
                    }

                    if ($lastComma !== null && $lastComma > $prevMatch) {
                        $prevMatch = $lastComma;
                    }

                    return $this->findNext(self::EMPTY, $prevMatch + 1, null, true) ?? $start;
                }
            }
        }

        $lastNotEmpty = $start;
        while (
            isset($endTokens[$tokens[$start]->code])
            || $tokens[$start]->scopeCondition !== null && $start === $tokens[$start]->scopeCloser
        ) {
            if ($tokens[$start]->scopeCondition !== null) {
                $start = $tokens[$start]->scopeCondition;
            } else {
                $start--;
            }
        }

        for ($i = $start; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (isset($startTokens[$token->code]) || isset($endTokens[$token->code])) {
                return $lastNotEmpty;
            }

            if (
                $token->scopeOpener !== null
                && $i === $token->scopeCloser
                && $token->code !== 'T_CLOSE_PARENTHESIS'
                && $token->code !== 'T_END_NOWDOC'
                && $token->code !== 'T_END_HEREDOC'
                && !isset(self::CASE_ENDS[$token->code])
                && $token->code !== 'T_BREAK'
            ) {
                return $lastNotEmpty;
            }

            if ($token->bracketOpener !== null && $i === $token->bracketCloser) {
                $i = $token->bracketOpener;
            } elseif ($token->parenthesisOpener !== null && $i === $token->parenthesisCloser) {
                $i = $token->parenthesisOpener;
            } elseif ($token->code === 'T_CLOSE_USE_GROUP') {
                $group = $this->findPrevious(['T_OPEN_USE_GROUP' => true], $i - 1);
                if ($group !== null) {
                    $i = $group;
                }
            }

            if (!isset(self::EMPTY[$tokens[$i]->code])) {
                $lastNotEmpty = $i;
            }
        }

        return 0;
    }

    // --- building ---

    /**
     * @param list<string> $codes
     * @param list<string> $texts
     */
    private function positions(array $codes, array $texts, string $eol): void
    {
        $line = 1;
        $column = 1;
        $pos = 0;
        $eolLength = strlen($eol);
        $iconv = function_exists('iconv_strlen');
        $tokens = [];
        $docOpen = null;
        foreach ($codes as $i => $code) {
            $text = $texts[$i];
            $token = new LayoutToken($code, $text);
            $token->pos = $pos;
            $token->line = $line;
            $token->column = $column;
            $bytes = strlen($text);
            $pos += $bytes;
            if (isset(self::TAB_TOKENS[$code]) && str_contains($text, "\t")) {
                $this->replaceTabs($token);
                $length = $token->length;
            } elseif ($iconv && preg_match('/[\x80-\xff]/', $text) === 1) {
                $length = self::characters($text);
                $length = $length === false ? $bytes : $length;
            } else {
                $length = $bytes;
            }

            $column += $length;
            $token->length = $length;
            if (str_contains($text, $eol)) {
                ++$line;
                $column = 1;
                $token->length -= $eolLength;
            }

            if ($code === 'T_COMMENT' || $code === 'T_DOC_COMMENT_STRING' || $code === 'T_DOC_COMMENT_TAG') {
                self::annotation($token);
            }

            if ($code === 'T_DOC_COMMENT_OPEN_TAG') {
                $docOpen = $i;
            } elseif ($code === 'T_DOC_COMMENT_CLOSE_TAG' && $docOpen !== null) {
                $tokens[$docOpen]->commentCloser = $i;
            }

            $tokens[] = $token;
        }

        $this->tokens = $tokens;
        $this->count = count($tokens);
    }

    /** The length in characters (`iconv_strlen`), or in bytes for text that is not UTF-8. */
    private static function characters(string $text): int
    {
        if (preg_match('/[\x80-\xff]/', $text) !== 1) {
            return strlen($text);
        }

        $length = preg_match_all('/./su', $text);

        return $length === false ? strlen($text) : $length;
    }

    private function replaceTabs(LayoutToken $token): void
    {
        $column = $token->column;
        $width = self::TAB_WIDTH;
        $text = $token->content;
        $parts = explode("\t", $text);
        $last = count($parts) - 1;
        $new = '';
        $length = 0;
        foreach ($parts as $index => $part) {
            if ($part !== '') {
                $new .= $part;
                $contentLength = self::characters($part);
                $column += $contentLength;
                $length += $contentLength;
            }

            if ($index === $last) {
                break;
            }

            $pad = $width - (($column + $width - 1) % $width);
            $column += $pad;
            $length += $pad;
            $new .= str_repeat(' ', max(0, $pad));
        }

        $token->origContent = $text;
        $token->content = $new;
        $token->length = $length;
    }

    /** `phpcs:ignore`, `phpcs:disable` ... comments get a token name of their own. */
    private static function annotation(LayoutToken $token): void
    {
        $text = rtrim(ltrim($token->origContent ?? $token->content, " \t/*#"), " */\t\r\n");
        $lower = strtolower($text);
        if (!str_starts_with($lower, 'phpcs:') && !str_starts_with($lower, '@phpcs:')) {
            return;
        }

        if ($text[0] === '@') {
            $text = substr($text, 1);
            $lower = strtolower($text);
        }

        $dash = strpos($lower, ' --');
        if ($dash !== false) {
            $text = substr($text, 0, $dash);
            $lower = strtolower($text);
        }

        if (str_starts_with($lower, 'phpcs:set')) {
            $parts = explode(' ', substr($text, 10));
            if (count($parts) >= 2 && count(explode('.', $parts[0])) >= 3) {
                $token->sniffCode = array_shift($parts);
                $token->sniffProperty = array_shift($parts);
                $token->sniffPropertyValue = rtrim(implode(' ', $parts), " */\r\n");
            }

            $token->code = 'T_PHPCS_SET';
        } elseif (str_starts_with($lower, 'phpcs:ignorefile')) {
            $token->code = 'T_PHPCS_IGNORE_FILE';
        } elseif (str_starts_with($lower, 'phpcs:disable')) {
            $token->code = 'T_PHPCS_DISABLE';
        } elseif (str_starts_with($lower, 'phpcs:enable')) {
            $token->code = 'T_PHPCS_ENABLE';
        } elseif (str_starts_with($lower, 'phpcs:ignore')) {
            $token->code = 'T_PHPCS_IGNORE';
        }
    }

    private function tokenMap(): void
    {
        $tokens = $this->tokens;
        $squareOpeners = [];
        $curlyOpeners = [];
        $openers = [];
        $openOwner = null;
        $hasParenthesisOwner = [];
        for ($i = 0; $i < $this->count; $i++) {
            $token = $tokens[$i];
            $code = $token->code;
            if (isset(self::PARENTHESIS_OPENERS[$code])) {
                $token->parenthesisOwner = $i;
                $hasParenthesisOwner[$i] = true;
                $openOwner = $i;
            } elseif ($code === 'T_OPEN_PARENTHESIS') {
                $openers[] = $i;
                $token->parenthesisOpener = $i;
                if ($openOwner !== null) {
                    $tokens[$openOwner]->parenthesisOpener = $i;
                    $token->parenthesisOwner = $openOwner;
                    $openOwner = null;
                }
            } elseif ($code === 'T_CLOSE_PARENTHESIS') {
                if ($openers !== []) {
                    $opener = array_pop($openers);
                    if ($tokens[$opener]->parenthesisOwner !== null) {
                        $owner = $tokens[$opener]->parenthesisOwner;
                        $tokens[$owner]->parenthesisCloser = $i;
                        $token->parenthesisOwner = $owner;
                    }

                    $token->parenthesisOpener = $opener;
                    $token->parenthesisCloser = $i;
                    $tokens[$opener]->parenthesisCloser = $i;
                }
            } elseif ($code === 'T_ATTRIBUTE') {
                $openers[] = $i;
            } elseif ($code === 'T_ATTRIBUTE_END') {
                if ($openers !== []) {
                    array_pop($openers);
                }
            }

            switch ($code) {
                case 'T_OPEN_SQUARE_BRACKET':
                    $squareOpeners[] = $i;
                    break;
                case 'T_OPEN_CURLY_BRACKET':
                    if ($token->scopeCloser === null) {
                        $curlyOpeners[] = $i;
                    }

                    break;
                case 'T_CLOSE_SQUARE_BRACKET':
                    if ($squareOpeners !== []) {
                        $opener = array_pop($squareOpeners);
                        $token->bracketOpener = $opener;
                        $token->bracketCloser = $i;
                        $tokens[$opener]->bracketOpener = $opener;
                        $tokens[$opener]->bracketCloser = $i;
                    }

                    break;
                case 'T_CLOSE_CURLY_BRACKET':
                    if ($curlyOpeners !== [] && $token->scopeOpener === null) {
                        $opener = array_pop($curlyOpeners);
                        $token->bracketOpener = $opener;
                        $token->bracketCloser = $i;
                        $tokens[$opener]->bracketOpener = $opener;
                        $tokens[$opener]->bracketCloser = $i;
                    }

                    break;
                default:
                    break;
            }
        }

        // Unclosed parentheses lose the owner they were given.
        foreach ($openers as $opener) {
            $tokens[$opener]->parenthesisOpener = null;
            $tokens[$opener]->parenthesisOwner = null;
        }
    }

    private function nestingMap(): void
    {
        /** @var array<int, int> $map */
        $map = [];
        $tokens = $this->tokens;
        for ($i = 0; $i < $this->count; $i++) {
            $token = $tokens[$i];
            if ($token->parenthesisOpener !== null && $i === $token->parenthesisOpener) {
                if ($map !== []) {
                    $token->nestedParenthesis = $map;
                }

                if ($token->parenthesisCloser !== null) {
                    $map[$token->parenthesisOpener] = $token->parenthesisCloser;
                }
            } elseif ($token->parenthesisCloser !== null && $i === $token->parenthesisCloser) {
                array_pop($map);
                if ($map !== []) {
                    $token->nestedParenthesis = $map;
                }
            } elseif ($map !== []) {
                $token->nestedParenthesis = $map;
            }
        }
    }

    private function scopeMap(): void
    {
        for ($i = 0; $i < $this->count; $i++) {
            if (isset(self::SCOPES[$this->tokens[$i]->code])) {
                if ($this->tokens[$i]->scopeCondition !== null) {
                    continue;
                }

                $ignore = 0;
                $i = $this->recurseScopeMap($i, 1, $ignore);
            }
        }
    }

    private function recurseScopeMap(int $stackPtr, int $depth, int &$ignore): int
    {
        $tokens = $this->tokens;
        $opener = null;
        $currType = $tokens[$stackPtr]->code;
        $startLine = $tokens[$stackPtr]->line;
        $originalIgnore = $ignore;
        if (isset(self::SCOPES[$currType]['start'][$currType])) {
            $opener = $stackPtr;
        }

        for ($i = $stackPtr + 1; $i < $this->count; $i++) {
            $tokenType = $tokens[$i]->code;
            if (
                ($currType === 'T_IF' || $currType === 'T_ELSE' || $currType === 'T_USE')
                && $opener === null
                && ($tokenType === 'T_SEMICOLON' || $tokenType === 'T_CLOSE_TAG')
            ) {
                return $i;
            }

            if (
                $opener === null
                && $ignore === 0
                && $tokenType === 'T_CLOSE_CURLY_BRACKET'
                && isset(self::SCOPES[$currType]['end'][$tokenType])
            ) {
                return $i - 1;
            }

            if (
                $opener !== null
                && ($tokens[$i]->scopeOpener === null || self::SCOPES[$tokens[$stackPtr]->code]['shared'])
                && isset(self::SCOPES[$currType]['end'][$tokenType])
            ) {
                if ($ignore > 0 && $tokenType === 'T_CLOSE_CURLY_BRACKET') {
                    $ignore--;
                    continue;
                }

                if ($tokens[$opener]->code === 'T_OPEN_CURLY_BRACKET' && $tokenType !== 'T_CLOSE_CURLY_BRACKET') {
                    // Not the end of this scope.
                } else {
                    $scopeCloser = $i;
                    $todo = [$stackPtr, $opener];
                    $validCloser = true;
                    if (
                        ($tokens[$stackPtr]->code === 'T_IF' || $tokens[$stackPtr]->code === 'T_ELSEIF')
                        && ($tokenType === 'T_ELSE' || $tokenType === 'T_ELSEIF')
                    ) {
                        $i = $this->recurseScopeMap($i, $depth + 1, $ignore);
                        if ($tokens[$scopeCloser]->scopeOpener === null) {
                            $validCloser = false;
                        } elseif ($tokens[(int) $tokens[$scopeCloser]->scopeOpener]->code !== $tokens[$opener]->code) {
                            $validCloser = false;
                        }
                    } else {
                        $todo[] = $scopeCloser;
                    }

                    if ($validCloser) {
                        foreach ($todo as $index) {
                            $tokens[$index]->scopeCondition = $stackPtr;
                            $tokens[$index]->scopeOpener = $opener;
                            $tokens[$index]->scopeCloser = $scopeCloser;
                        }

                        if (self::SCOPES[$tokens[$stackPtr]->code]['shared']) {
                            $ignore = $originalIgnore;

                            return $opener;
                        }

                        if ($scopeCloser === $i && isset(self::SCOPES[$tokenType])) {
                            $tokens[$scopeCloser]->scopeCondition = null;

                            return $i - 1;
                        }

                        return $i;
                    }

                    continue;
                }
            }

            if (isset(self::SCOPES[$tokenType])) {
                if ($opener === null) {
                    if ($tokenType === 'T_USE') {
                        continue;
                    }

                    if ($tokenType === 'T_NAMESPACE') {
                        continue;
                    }

                    if ($tokenType === 'T_FUNCTION' && $tokens[$stackPtr]->code !== 'T_FUNCTION') {
                        if ($tokens[$i]->scopeCloser !== null) {
                            $i = $tokens[$i]->scopeCloser;
                            continue;
                        }

                        $i = $this->recurseScopeMap($i, $depth + 1, $ignore);
                        continue;
                    }

                    if ($tokenType === 'T_CLASS') {
                        if ($tokens[$i]->scopeCloser !== null) {
                            $i = $tokens[$i]->scopeCloser;
                            continue;
                        }

                        $i = $this->recurseScopeMap($i, $depth + 1, $ignore);
                        continue;
                    }

                    if (
                        (
                            $tokens[$stackPtr]->code === 'T_IF'
                            || $tokens[$stackPtr]->code === 'T_ELSEIF'
                            || $tokens[$stackPtr]->code === 'T_ELSE'
                        )
                        && ($tokens[$i]->code === 'T_ELSE' || $tokens[$i]->code === 'T_ELSEIF')
                    ) {
                        return $i - 1;
                    }

                    return $stackPtr;
                }

                $isShared = self::SCOPES[$tokenType]['shared'];
                if ($tokens[$i]->scopeCondition !== null) {
                    if (!$isShared && $tokens[$i]->scopeCloser !== null) {
                        $i = $tokens[$i]->scopeCloser;
                    }

                    continue;
                }

                if ($currType === $tokenType && !$isShared && $opener === null) {
                    return $stackPtr;
                }

                $oldIgnore = $ignore;
                if (isset(self::SCOPES[$tokenType]['end']['T_CLOSE_CURLY_BRACKET'])) {
                    $ignore = 0;
                }

                $oldDepth = $depth;
                if ($isShared && isset(self::SCOPES[$tokenType]['with'][$currType])) {
                    $depth--;
                }

                $i = $this->recurseScopeMap($i, $depth + 1, $ignore);
                $depth = $oldDepth;
                if (isset(self::SCOPES[$tokenType]['end']['T_CLOSE_CURLY_BRACKET'])) {
                    $ignore = $oldIgnore;
                }
            }

            if (isset(self::SCOPES[$currType]['start'][$tokenType]) && $opener === null) {
                if ($tokenType === 'T_OPEN_CURLY_BRACKET') {
                    if ($tokens[$stackPtr]->parenthesisCloser !== null && $i < $tokens[$stackPtr]->parenthesisCloser) {
                        $ignore++;
                    } else {
                        for ($x = $i - 1; $x > 0; $x--) {
                            if (isset(self::EMPTY[$tokens[$x]->code])) {
                                continue;
                            }

                            $xCode = $tokens[$x]->code;
                            if (
                                isset(self::ASSIGNMENT[$xCode])
                                || $xCode === 'T_DOLLAR'
                                || $xCode === 'T_VARIABLE'
                                || $xCode === 'T_OBJECT_OPERATOR'
                                || $xCode === 'T_NULLSAFE_OBJECT_OPERATOR'
                                || $xCode === 'T_COMMA'
                                || $xCode === 'T_OPEN_PARENTHESIS'
                            ) {
                                $ignore++;
                            }

                            break;
                        }
                    }
                }

                if ($ignore === 0 || $tokenType !== 'T_OPEN_CURLY_BRACKET') {
                    $openerNested = $tokens[$i]->nestedParenthesis !== [];
                    $ownerNested = $tokens[$stackPtr]->nestedParenthesis !== [];
                    if (
                        $openerNested && !$ownerNested
                        || !$openerNested && $ownerNested
                        || $openerNested && $tokens[$i]->nestedParenthesis !== $tokens[$stackPtr]->nestedParenthesis
                    ) {
                        // The opener is in different parentheses than the owner: not this scope's.
                    } else {
                        $opener = $i;
                    }
                }
            } elseif (
                $tokenType === 'T_SEMICOLON'
                && $opener === null
                && ($tokens[$stackPtr]->parenthesisCloser === null || $i > $tokens[$stackPtr]->parenthesisCloser)
            ) {
                return $i - 1;
            } elseif ($tokenType === 'T_OPEN_PARENTHESIS') {
                if ($tokens[$i]->parenthesisOwner !== null) {
                    $owner = $tokens[$i]->parenthesisOwner;
                    if (isset(self::SCOPE_OPENERS[$tokens[$owner]->code]) && $tokens[$i]->parenthesisCloser !== null) {
                        $startLine = $tokens[$tokens[$i]->parenthesisCloser]->line;
                    }
                }
            } elseif ($tokenType === 'T_OPEN_CURLY_BRACKET' && $opener !== null) {
                $ignore++;
            } elseif ($tokenType === 'T_CLOSE_CURLY_BRACKET' && $ignore > 0) {
                $ignore--;
            } elseif ($opener === null && isset(self::SCOPES[$currType])) {
                if ($tokens[$i]->line >= ($startLine + 30) && !isset(self::EMPTY[$tokens[$i - 1]->code])) {
                    if (!self::SCOPES[$currType]['strict']) {
                        return $stackPtr;
                    }
                }
            } elseif ($opener !== null && $tokenType !== 'T_BREAK' && isset(self::END_SCOPE[$tokenType])) {
                if ($tokens[$i]->scopeCondition === null) {
                    if ($ignore > 0) {
                        $ignore--;
                    } else {
                        foreach ([$stackPtr, $opener] as $index) {
                            $tokens[$index]->scopeCondition = $stackPtr;
                            $tokens[$index]->scopeOpener = $opener;
                            $tokens[$index]->scopeCloser = $i;
                        }

                        return $i - 1;
                    }
                }
            }
        }

        return $stackPtr;
    }

    private function levelMap(): void
    {
        $tokens = $this->tokens;
        $level = 0;
        /** @var array<int, int|string> $conditions */
        $conditions = [];
        $lastOpener = null;
        /** @var array<int, int> $openers */
        $openers = [];
        for ($i = 0; $i < $this->count; $i++) {
            $token = $tokens[$i];
            $token->level = $level;
            $token->conditions = $conditions;
            if ($token->scopeCondition === null) {
                continue;
            }

            if ($token->scopeOpener === $i) {
                $stackPtr = $token->scopeCondition;
                if ($lastOpener !== null && $tokens[$lastOpener]->scopeCloser === $token->scopeCloser) {
                    $thisType = $tokens[$token->scopeCondition]->code;
                    $opener = (int) $tokens[$lastOpener]->scopeCondition;
                    $openerCode = $tokens[$opener]->code;
                    $isShared = isset(self::SCOPES[$thisType]['with'][$openerCode]);
                    $sameEnd = self::firstKey(self::SCOPES[$thisType]['end']) === self::firstKey(
                        self::SCOPES[$openerCode]['end'],
                    );
                    if ($isShared && $sameEnd) {
                        $badToken = $opener;
                        for ($x = $token->scopeCondition; $x <= $i; $x++) {
                            $tokens[$x]->level--;
                            unset($tokens[$x]->conditions[$badToken]);
                        }

                        unset($conditions[$badToken], $openers[$lastOpener]);
                        $level--;
                    }
                }

                $level++;
                $conditions[$stackPtr] = $tokens[$stackPtr]->code;
                $lastOpener = $token->scopeOpener;
                $openers[$lastOpener] = $lastOpener;
            } elseif ($lastOpener !== null && $tokens[$lastOpener]->scopeCloser === $i) {
                foreach (array_reverse($openers) as $opener) {
                    if ($tokens[$opener]->scopeCloser !== $i) {
                        continue;
                    }

                    $oldOpener = array_pop($openers);
                    if ($openers !== []) {
                        $lastOpener = array_pop($openers);
                        $openers[$lastOpener] = $lastOpener;
                    } else {
                        $lastOpener = null;
                    }

                    $oldCondition = (string) array_pop($conditions);
                    $condition = $tokens[$token->scopeCondition]->code;
                    if ($condition !== $oldCondition && !isset(self::SCOPES[$oldCondition]['with'][$condition])) {
                        $badToken = $tokens[(int) $oldOpener]->scopeCondition;
                        for ($x = (int) $oldOpener + 1; $x <= $i; $x++) {
                            $tokens[$x]->level--;
                            unset($tokens[$x]->conditions[$badToken]);
                        }
                    }

                    $level--;
                    $token->level = $level;
                    $token->conditions = $conditions;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $array
     */
    private static function firstKey(array $array): string|int|null
    {
        return array_keys($array)[0] ?? null;
    }

    /**
     * The part of `PHP::processAdditional()` that changes scopes: closures, anonymous classes, arrow
     * functions, short arrays, `match`, and the `case` that opens a brace block.
     */
    private function additional(): void
    {
        $tokens = $this->tokens;
        $numTokens = $this->count;
        for ($i = $numTokens - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if ($token->scopeOpener !== null && $token->scopeCondition === null) {
                $token->scopeCondition = $tokens[$token->scopeOpener]->scopeCondition;
            }

            $code = $token->code;
            if ($code === 'T_FUNCTION') {
                if ($token->scopeOpener !== null) {
                    for ($x = $i + 1; $x < $numTokens; $x++) {
                        if (!isset(self::EMPTY[$tokens[$x]->code]) && $tokens[$x]->code !== 'T_BITWISE_AND') {
                            break;
                        }
                    }

                    if ($tokens[$x]->code === 'T_OPEN_PARENTHESIS') {
                        $token->code = 'T_CLOSURE';
                        for ($y = $token->scopeOpener + 1; $y < (int) $token->scopeCloser; $y++) {
                            if (isset($tokens[$y]->conditions[$i])) {
                                $tokens[$y]->conditions[$i] = 'T_CLOSURE';
                            }
                        }
                    }
                }

                continue;
            }

            if ($code === 'T_CLASS' && $token->scopeOpener !== null) {
                for ($x = $i + 1; $x < $numTokens; $x++) {
                    if (!isset(self::EMPTY[$tokens[$x]->code])) {
                        break;
                    }
                }

                $next = $tokens[$x]->code;
                if (
                    $next === 'T_OPEN_PARENTHESIS'
                    || $next === 'T_OPEN_CURLY_BRACKET'
                    || $next === 'T_EXTENDS'
                    || $next === 'T_IMPLEMENTS'
                ) {
                    $token->code = 'T_ANON_CLASS';
                    if ($next === 'T_OPEN_PARENTHESIS' && $tokens[$x]->parenthesisCloser !== null) {
                        $closer = $tokens[$x]->parenthesisCloser;
                        $token->parenthesisOpener = $x;
                        $token->parenthesisCloser = $closer;
                        $token->parenthesisOwner = $i;
                        $tokens[$x]->parenthesisOwner = $i;
                        $tokens[$closer]->parenthesisOwner = $i;
                    }

                    for ($y = $token->scopeOpener + 1; $y < (int) $token->scopeCloser; $y++) {
                        if (isset($tokens[$y]->conditions[$i])) {
                            $tokens[$y]->conditions[$i] = 'T_ANON_CLASS';
                        }
                    }
                }

                continue;
            }

            if ($code === 'T_FN' && isset($tokens[$i + 1])) {
                $this->arrowFunction($i);
                continue;
            }

            if ($code === 'T_OPEN_SQUARE_BRACKET') {
                if ($token->bracketCloser === null) {
                    continue;
                }

                $this->shortArray($i);
                continue;
            }

            if ($code === 'T_MATCH') {
                $this->matchExpression($i);
                continue;
            }

            if ($code === 'T_BITWISE_OR' || $code === 'T_BITWISE_AND' || $code === 'T_CLOSE_PARENTHESIS') {
                continue;
            }

            if ($code === 'T_STATIC') {
                for ($x = $i - 1; $x > 0; $x--) {
                    if (!isset(self::EMPTY[$tokens[$x]->code])) {
                        break;
                    }
                }

                if ($tokens[$x]->code === 'T_INSTANCEOF') {
                    $token->code = 'T_STRING';
                }

                continue;
            }

            if ($code === 'T_TRUE' || $code === 'T_FALSE' || $code === 'T_NULL' || $code === 'T_EXIT') {
                for ($x = $i + 1; $x < $numTokens; $x++) {
                    if (!isset(self::EMPTY[$tokens[$x]->code])) {
                        break;
                    }
                }

                if ($x !== $numTokens && self::isStringContext($tokens[$x]->code)) {
                    $token->code = 'T_STRING';
                }
            }

            if ($token->code !== 'T_CASE' && $token->code !== 'T_DEFAULT') {
                continue;
            }

            if ($token->scopeOpener === null) {
                continue;
            }

            $this->caseWithBraces($i);
        }
    }

    private static function isStringContext(string $code): bool
    {
        return in_array(
            $code,
            [
                'T_OBJECT_OPERATOR',
                'T_NULLSAFE_OBJECT_OPERATOR',
                'T_FUNCTION',
                'T_CLASS',
                'T_INTERFACE',
                'T_TRAIT',
                'T_ENUM',
                'T_ENUM_CASE',
                'T_EXTENDS',
                'T_IMPLEMENTS',
                'T_ATTRIBUTE',
                'T_NEW',
                'T_CONST',
                'T_NS_SEPARATOR',
                'T_USE',
                'T_NAMESPACE',
                'T_DOUBLE_COLON',
            ],
            true,
        );
    }

    private function arrowFunction(int $i): void
    {
        $tokens = $this->tokens;
        $numTokens = $this->count;
        $token = $tokens[$i];
        for ($x = $i + 1; $x < $numTokens; $x++) {
            if (!isset(self::EMPTY[$tokens[$x]->code]) && $tokens[$x]->code !== 'T_BITWISE_AND') {
                break;
            }
        }

        if (
            isset($tokens[$x])
            && $tokens[$x]->code === 'T_OPEN_PARENTHESIS'
            && $tokens[$x]->parenthesisCloser !== null
        ) {
            $ignore = self::EMPTY
            + [
                'T_ARRAY' => true,
                'T_CALLABLE' => true,
                'T_COLON' => true,
                'T_NAMESPACE' => true,
                'T_NS_SEPARATOR' => true,
                'T_NULL' => true,
                'T_TRUE' => true,
                'T_FALSE' => true,
                'T_NULLABLE' => true,
                'T_PARENT' => true,
                'T_SELF' => true,
                'T_STATIC' => true,
                'T_STRING' => true,
                'T_BITWISE_OR' => true,
                'T_BITWISE_AND' => true,
                'T_OPEN_PARENTHESIS' => true,
                'T_CLOSE_PARENTHESIS' => true,
            ];
            $closer = $tokens[$x]->parenthesisCloser;
            for ($arrow = $closer + 1; $arrow < $numTokens; $arrow++) {
                if (!isset($ignore[$tokens[$arrow]->code])) {
                    break;
                }
            }

            if ($arrow < $numTokens && $tokens[$arrow]->code === 'T_DOUBLE_ARROW') {
                $endTokens = [
                    'T_COLON' => true,
                    'T_COMMA' => true,
                    'T_SEMICOLON' => true,
                    'T_CLOSE_PARENTHESIS' => true,
                    'T_CLOSE_SQUARE_BRACKET' => true,
                    'T_CLOSE_CURLY_BRACKET' => true,
                    'T_CLOSE_SHORT_ARRAY' => true,
                    'T_OPEN_TAG' => true,
                    'T_CLOSE_TAG' => true,
                ];
                $inTernary = false;
                $lastEndToken = null;
                for ($scopeCloser = $arrow + 1; $scopeCloser < $numTokens; $scopeCloser++) {
                    $sc = $tokens[$scopeCloser];
                    if (
                        $sc->scopeCloser !== null
                        && $sc->scopeCondition !== null
                        && $scopeCloser === $sc->scopeCloser
                        && $tokens[$sc->scopeCondition]->code === 'T_MATCH'
                    ) {
                        if ($arrow < $sc->scopeCondition) {
                            continue;
                        }

                        if ($lastEndToken !== null) {
                            $scopeCloser = $lastEndToken;
                            break;
                        }

                        for ($lastNonEmpty = $scopeCloser - 1; $lastNonEmpty > $arrow; $lastNonEmpty--) {
                            if (!isset(self::EMPTY[$tokens[$lastNonEmpty]->code])) {
                                $scopeCloser = $lastNonEmpty;
                                break 2;
                            }
                        }
                    }

                    if (isset($endTokens[$sc->code])) {
                        if (
                            $lastEndToken !== null
                            && (
                                $sc->parenthesisOpener !== null
                                && $sc->parenthesisOpener < $arrow
                                || $sc->bracketOpener !== null
                                && $sc->bracketOpener < $arrow
                            )
                        ) {
                            for ($lastNonEmpty = $scopeCloser - 1; $lastNonEmpty > $arrow; $lastNonEmpty--) {
                                if (!isset(self::EMPTY[$tokens[$lastNonEmpty]->code])) {
                                    $scopeCloser = $lastNonEmpty;
                                    break;
                                }
                            }
                        }

                        break;
                    }

                    if (
                        !$inTernary
                        && $sc->scopeCloser !== null
                        && $sc->scopeCondition !== null
                        && $scopeCloser === $sc->scopeCloser
                        && $tokens[$sc->scopeCondition]->code === 'T_FN'
                    ) {
                        break;
                    }

                    if (
                        $sc->scopeCloser !== null
                        && $sc->code !== 'T_INLINE_ELSE'
                        && $sc->code !== 'T_END_HEREDOC'
                        && $sc->code !== 'T_END_NOWDOC'
                    ) {
                        $scopeCloser = $sc->scopeCloser - 1;
                        continue;
                    }

                    if ($sc->parenthesisCloser !== null) {
                        $scopeCloser = $sc->parenthesisCloser;
                        $lastEndToken = $scopeCloser;
                        continue;
                    }

                    if ($sc->bracketCloser !== null) {
                        $scopeCloser = $sc->bracketCloser;
                        $lastEndToken = $scopeCloser;
                        continue;
                    }

                    if ($sc->code === 'T_INLINE_THEN') {
                        $inTernary = true;
                        continue;
                    }

                    if ($sc->code === 'T_INLINE_ELSE') {
                        if (!$inTernary) {
                            break;
                        }

                        $inTernary = false;
                    }
                }

                if ($scopeCloser !== $numTokens) {
                    $token->scopeCondition = $i;
                    $token->scopeOpener = $arrow;
                    $token->scopeCloser = $scopeCloser;
                    $token->parenthesisOwner = $i;
                    $token->parenthesisOpener = $x;
                    $token->parenthesisCloser = $closer;
                    $tokens[$arrow]->code = 'T_FN_ARROW';
                    $tokens[$arrow]->scopeCondition = $i;
                    $tokens[$arrow]->scopeOpener = $arrow;
                    $tokens[$arrow]->scopeCloser = $scopeCloser;
                    $tokens[$scopeCloser]->scopeCondition = $i;
                    $tokens[$scopeCloser]->scopeOpener = $arrow;
                    $tokens[$scopeCloser]->scopeCloser = $scopeCloser;
                    $tokens[$x]->parenthesisOwner = $i;
                    $tokens[$closer]->parenthesisOwner = $i;
                }
            }
        }

        if ($token->scopeCloser === null) {
            $token->code = 'T_STRING';
        }
    }

    private function shortArray(int $i): void
    {
        $tokens = $this->tokens;
        $token = $tokens[$i];
        $isShortArray = false;
        $allowed = [
            'T_CLOSE_SQUARE_BRACKET' => true,
            'T_CLOSE_CURLY_BRACKET' => true,
            'T_CLOSE_PARENTHESIS' => true,
            'T_VARIABLE' => true,
            'T_OBJECT_OPERATOR' => true,
            'T_NULLSAFE_OBJECT_OPERATOR' => true,
            'T_STRING' => true,
            'T_CONSTANT_ENCAPSED_STRING' => true,
            'T_DOUBLE_QUOTED_STRING' => true,
            'T_CLASS_C' => true,
            'T_DIR' => true,
            'T_FILE' => true,
            'T_FUNC_C' => true,
            'T_LINE' => true,
            'T_METHOD_C' => true,
            'T_NS_C' => true,
            'T_TRAIT_C' => true,
        ];
        for ($x = $i - 1; $x >= 0; $x--) {
            $prev = $tokens[$x];
            if (
                $prev->code === 'T_CLOSE_CURLY_BRACKET'
                && $prev->scopeCondition !== null
                && $tokens[$prev->scopeCondition]->code === 'T_CLASS'
            ) {
                for ($y = $prev->scopeCondition + 1; $y < $this->count; $y++) {
                    if (!isset(self::EMPTY[$tokens[$y]->code])) {
                        break;
                    }
                }

                $yCode = $tokens[$y]->code;
                if (
                    $yCode === 'T_OPEN_PARENTHESIS'
                    || $yCode === 'T_OPEN_CURLY_BRACKET'
                    || $yCode === 'T_EXTENDS'
                    || $yCode === 'T_IMPLEMENTS'
                ) {
                    break;
                }
            }

            if ($prev->scopeOpener !== null) {
                $isShortArray = true;
                break;
            }

            if (!isset(self::EMPTY[$prev->code])) {
                if (
                    $prev->code === 'T_CLOSE_PARENTHESIS'
                    && $prev->parenthesisOwner !== null
                    && isset(self::SCOPE_OPENERS[$tokens[$prev->parenthesisOwner]->code])
                    || !isset($allowed[$prev->code])
                ) {
                    $isShortArray = true;
                }

                break;
            }
        }

        if ($isShortArray) {
            $token->code = 'T_OPEN_SHORT_ARRAY';
            $tokens[(int) $token->bracketCloser]->code = 'T_CLOSE_SHORT_ARRAY';
        }
    }

    private function matchExpression(int $i): void
    {
        $tokens = $this->tokens;
        $token = $tokens[$i];
        if ($token->scopeOpener === null || $token->scopeCloser === null) {
            $token->code = 'T_STRING';
            if ($token->parenthesisOpener !== null && $token->parenthesisCloser !== null) {
                $tokens[$token->parenthesisOpener]->parenthesisOwner = null;
                $tokens[$token->parenthesisCloser]->parenthesisOwner = null;
                $token->parenthesisOpener = null;
                $token->parenthesisCloser = null;
                $token->parenthesisOwner = null;
            }

            return;
        }

        $searchFor =
            [
                'T_OPEN_CURLY_BRACKET' => true,
                'T_OPEN_SQUARE_BRACKET' => true,
                'T_OPEN_PARENTHESIS' => true,
                'T_OPEN_SHORT_ARRAY' => true,
                'T_DOUBLE_ARROW' => true,
            ] + self::SCOPE_OPENERS;
        for ($x = $token->scopeOpener + 1; $x < $token->scopeCloser; $x++) {
            if (!isset($searchFor[$tokens[$x]->code])) {
                continue;
            }

            if ($tokens[$x]->scopeCloser !== null) {
                $x = $tokens[$x]->scopeCloser;
                continue;
            }

            if ($tokens[$x]->parenthesisCloser !== null) {
                $x = $tokens[$x]->parenthesisCloser;
                continue;
            }

            if ($tokens[$x]->bracketCloser !== null) {
                $x = $tokens[$x]->bracketCloser;
                continue;
            }

            if ($tokens[$x]->code === 'T_DOUBLE_ARROW') {
                $tokens[$x]->code = 'T_MATCH_ARROW';
            }
        }
    }

    private function caseWithBraces(int $i): void
    {
        $tokens = $this->tokens;
        $token = $tokens[$i];
        $scopeOpener = (int) $token->scopeOpener;
        $scopeCloser = (int) $token->scopeCloser;
        for ($x = $scopeOpener + 1; $x < $this->count; $x++) {
            if (!isset(self::EMPTY[$tokens[$x]->code])) {
                break;
            }
        }

        if ($tokens[$x]->code === 'T_CASE' || $tokens[$x]->code === 'T_DEFAULT') {
            $token->scopeCloser = $tokens[$x]->scopeCloser;

            return;
        }

        if ($tokens[$x]->code !== 'T_OPEN_CURLY_BRACKET' || $tokens[$x]->scopeCondition !== null) {
            return;
        }

        $newCloser = (int) $tokens[$x]->bracketCloser;
        foreach ([$i, $x, $newCloser] as $index) {
            $tokens[$index]->scopeCondition = $i;
            $tokens[$index]->scopeOpener = $x;
            $tokens[$index]->scopeCloser = $newCloser;
        }

        if ($tokens[$scopeOpener]->scopeCondition === $i) {
            $tokens[$scopeOpener]->scopeCondition = null;
            $tokens[$scopeOpener]->scopeOpener = null;
            $tokens[$scopeOpener]->scopeCloser = null;
        }

        if ($tokens[$scopeCloser]->scopeCondition === $i) {
            $tokens[$scopeCloser]->scopeCondition = null;
            $tokens[$scopeCloser]->scopeOpener = null;
            $tokens[$scopeCloser]->scopeCloser = null;
        } else {
            $condition = (int) $tokens[$scopeCloser]->scopeCondition;
            for ($y = (int) $tokens[$condition]->scopeOpener + 1; $y < $scopeCloser; $y++) {
                if ($tokens[$y]->scopeCloser === $scopeCloser) {
                    $tokens[$y]->scopeCloser = $newCloser;
                }
            }
        }

        $tokens[$x]->bracketOpener = null;
        $tokens[$x]->bracketCloser = null;
        $tokens[$newCloser]->bracketOpener = null;
        $tokens[$newCloser]->bracketCloser = null;
        $tokens[$scopeCloser]->conditions[] = $i;
        for ($y = $newCloser; $y < $scopeCloser; $y++) {
            foreach ($tokens[$y]->conditions as $num => $oldCondition) {
                if ($oldCondition === $token->code) {
                    unset($tokens[$y]->conditions[$num]);
                    break;
                }
            }
        }
    }
}
