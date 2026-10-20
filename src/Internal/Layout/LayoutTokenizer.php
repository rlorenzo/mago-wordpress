<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\Layout;

use PhpToken;

use function array_pop;
use function count;
use function explode;
use function in_array;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strspn;
use function strtolower;
use function substr;

use const T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG;
use const T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
use const T_ATTRIBUTE;
use const T_CASE;
use const T_COMMENT;
use const T_CONST;
use const T_CURLY_OPEN;
use const T_DEFAULT;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_DOUBLE_ARROW;
use const T_ENCAPSED_AND_WHITESPACE;
use const T_END_HEREDOC;
use const T_ENUM;
use const T_FN;
use const T_FUNCTION;
use const T_MATCH;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_NEW;
use const T_NS_SEPARATOR;
use const T_START_HEREDOC;
use const T_STATIC;
use const T_STRING;
use const T_SWITCH;
use const T_USE;
use const T_WHITESPACE;
use const T_YIELD_FROM;

/**
 * The first half of {@see PhpcsLayout}: PHP's own tokens turned into the phpcs 3 token names and splits
 * (`PHP_CodeSniffer\Tokenizers\PHP::tokenize()`), for PHP 8.1 and later: names split at `\`, a double-quoted
 * string, a heredoc line and any token with line breaks one token per line, `?` and `:` told apart,
 * `T_MATCH`, `T_PARAM_NAME`, goto labels and the rest that decides how scopes are found.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class LayoutTokenizer
{
    private const EMPTY = [
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

    private const CHARS = [
        '{' => 'T_OPEN_CURLY_BRACKET',
        '}' => 'T_CLOSE_CURLY_BRACKET',
        '[' => 'T_OPEN_SQUARE_BRACKET',
        ']' => 'T_CLOSE_SQUARE_BRACKET',
        '(' => 'T_OPEN_PARENTHESIS',
        ')' => 'T_CLOSE_PARENTHESIS',
        ':' => 'T_COLON',
        '.' => 'T_STRING_CONCAT',
        ';' => 'T_SEMICOLON',
        '=' => 'T_EQUAL',
        '*' => 'T_MULTIPLY',
        '/' => 'T_DIVIDE',
        '+' => 'T_PLUS',
        '-' => 'T_MINUS',
        '%' => 'T_MODULUS',
        '^' => 'T_BITWISE_XOR',
        '&' => 'T_BITWISE_AND',
        '|' => 'T_BITWISE_OR',
        '~' => 'T_BITWISE_NOT',
        '<' => 'T_LESS_THAN',
        '>' => 'T_GREATER_THAN',
        '!' => 'T_BOOLEAN_NOT',
        ',' => 'T_COMMA',
        '@' => 'T_ASPERAND',
        '$' => 'T_DOLLAR',
        '`' => 'T_BACKTICK',
        '?' => 'T_INLINE_THEN',
    ];

    /** Contexts in which a keyword is always a name (`PHP::$tstringContexts`). */
    private const STRING_CONTEXTS = [
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_FUNCTION' => true,
        'T_CLASS' => true,
        'T_INTERFACE' => true,
        'T_TRAIT' => true,
        'T_ENUM' => true,
        'T_ENUM_CASE' => true,
        'T_EXTENDS' => true,
        'T_IMPLEMENTS' => true,
        'T_ATTRIBUTE' => true,
        'T_NEW' => true,
        'T_CONST' => true,
        'T_NS_SEPARATOR' => true,
        'T_USE' => true,
        'T_NAMESPACE' => true,
        'T_DOUBLE_COLON' => true,
    ];

    /** `Tokens::$contextSensitiveKeywords`. */
    private const SENSITIVE = [
        'T_ABSTRACT' => true,
        'T_ARRAY' => true,
        'T_AS' => true,
        'T_BREAK' => true,
        'T_CALLABLE' => true,
        'T_CASE' => true,
        'T_CATCH' => true,
        'T_CLASS' => true,
        'T_CLONE' => true,
        'T_CONST' => true,
        'T_CONTINUE' => true,
        'T_DECLARE' => true,
        'T_DEFAULT' => true,
        'T_DO' => true,
        'T_ECHO' => true,
        'T_ELSE' => true,
        'T_ELSEIF' => true,
        'T_EMPTY' => true,
        'T_ENDDECLARE' => true,
        'T_ENDFOR' => true,
        'T_ENDFOREACH' => true,
        'T_ENDIF' => true,
        'T_ENDSWITCH' => true,
        'T_ENDWHILE' => true,
        'T_ENUM' => true,
        'T_EVAL' => true,
        'T_EXIT' => true,
        'T_EXTENDS' => true,
        'T_FINAL' => true,
        'T_FINALLY' => true,
        'T_FN' => true,
        'T_FOR' => true,
        'T_FOREACH' => true,
        'T_FUNCTION' => true,
        'T_GLOBAL' => true,
        'T_GOTO' => true,
        'T_IF' => true,
        'T_IMPLEMENTS' => true,
        'T_INCLUDE' => true,
        'T_INCLUDE_ONCE' => true,
        'T_INSTANCEOF' => true,
        'T_INSTEADOF' => true,
        'T_INTERFACE' => true,
        'T_ISSET' => true,
        'T_LIST' => true,
        'T_LOGICAL_AND' => true,
        'T_LOGICAL_OR' => true,
        'T_LOGICAL_XOR' => true,
        'T_MATCH' => true,
        'T_NAMESPACE' => true,
        'T_NEW' => true,
        'T_PRINT' => true,
        'T_PRIVATE' => true,
        'T_PROTECTED' => true,
        'T_PUBLIC' => true,
        'T_READONLY' => true,
        'T_REQUIRE' => true,
        'T_REQUIRE_ONCE' => true,
        'T_RETURN' => true,
        'T_STATIC' => true,
        'T_SWITCH' => true,
        'T_THROW' => true,
        'T_TRAIT' => true,
        'T_TRY' => true,
        'T_UNSET' => true,
        'T_USE' => true,
        'T_VAR' => true,
        'T_WHILE' => true,
        'T_YIELD' => true,
        'T_YIELD_FROM' => true,
    ];

    /** What may follow `?` in a nullable type. */
    private const TYPE_NAMES = [
        'T_STRING' => true,
        'T_NAME_FULLY_QUALIFIED' => true,
        'T_NAME_RELATIVE' => true,
        'T_NAME_QUALIFIED' => true,
        'T_ARRAY' => true,
        'T_NAMESPACE' => true,
        'T_NS_SEPARATOR' => true,
    ];

    private const METHOD_PREFIXES = [
        'T_PRIVATE' => true,
        'T_PUBLIC' => true,
        'T_PROTECTED' => true,
        'T_ABSTRACT' => true,
        'T_STATIC' => true,
        'T_FINAL' => true,
    ];

    private const SCOPE_MODIFIERS = ['T_PRIVATE' => true, 'T_PUBLIC' => true, 'T_PROTECTED' => true];

    /** @var list<PhpToken> */
    private array $raw = [];

    /** @var array<int, string> raw index => code that replaces the one PHP gave */
    private array $override = [];

    /** @var array<int, true> raw tokens already taken into an earlier phpcs token */
    private array $consumed = [];

    /** @var list<string> */
    private array $codes = [];

    /** @var list<string> */
    private array $texts = [];

    private function __construct(
        private readonly string $eol,
    ) {}

    /**
     * @return array{list<string>, list<string>} the phpcs token names and their text
     */
    public static function tokenize(string $source, string $eol): array
    {
        $tokenizer = new self($eol);
        $tokenizer->raw = PhpToken::tokenize($source);
        $tokenizer->run();

        return [$tokenizer->codes, $tokenizer->texts];
    }

    private function run(): void
    {
        $raw = &$this->raw;
        $count = count($raw);
        $insideInlineIf = [];
        $insideUseGroup = false;
        $insideConst = false;
        $lastNotEmpty = -1;
        for ($i = 0; $i < $count; $i++) {
            if (isset($this->consumed[$i])) {
                continue;
            }

            $token = $raw[$i];
            $text = $token->text;
            $name = $this->nameOf($i);
            $n = count($this->codes);
            if ($n > 0 && !isset(self::EMPTY[$this->codes[$n - 1]])) {
                $lastNotEmpty = $n - 1;
            }

            $lastCode = $lastNotEmpty >= 0 ? $this->codes[$lastNotEmpty] : '';
            $lastText = $lastNotEmpty >= 0 ? $this->texts[$lastNotEmpty] : '';

            // A keyword used as a name.
            if (
                isset(self::SENSITIVE[$name])
                && (isset(self::STRING_CONTEXTS[$lastCode]) || $lastText === '&' || $insideConst)
            ) {
                $preserve = false;
                if (isset(self::STRING_CONTEXTS[$lastCode])) {
                    $lower = strtolower($text);
                    if ($lastCode === 'T_NEW' && ($name === 'T_CLASS' || $name === 'T_STATIC')) {
                        $preserve = true;
                    }

                    if ($lastCode === 'T_NEW' && $lower === 'readonly') {
                        $next = $this->nextRawIndex($i + 1);
                        $preserve = $next !== null && $raw[$next]->id === \T_CLASS;
                    }

                    if (($name === 'T_EXTENDS' || $name === 'T_IMPLEMENTS') && $lastCode === 'T_CLASS') {
                        $preserve = true;
                    }

                    if ($name === 'T_NAMESPACE') {
                        $next = $this->nextRawIndex($i + 1);
                        if ($next !== null && $raw[$next]->id === T_NS_SEPARATOR) {
                            $preserve = true;
                        }
                    }

                    if ($name === 'T_EXIT' && $lastCode === 'T_NS_SEPARATOR') {
                        for ($j = $lastNotEmpty - 1; $j >= 0; $j--) {
                            if (isset(self::EMPTY[$this->codes[$j]])) {
                                continue;
                            }

                            if ($this->codes[$j] !== 'T_STRING' && $this->codes[$j] !== 'T_NAMESPACE') {
                                $preserve = true;
                            }

                            break;
                        }
                    }
                }

                if ($lastCode === 'T_CONST' && isset(self::STRING_CONTEXTS[$lastCode]) || $insideConst) {
                    $preserve = true;
                    $next = $this->nextRawIndex($i + 1);
                    if ($next !== null && ($raw[$next]->text === '=' || $raw[$next]->text === ';')) {
                        $preserve = false;
                        $insideConst = false;
                    }
                }

                if ($lastText === '&') {
                    $preserve = true;
                    for ($j = $lastNotEmpty - 1; $j >= 0; $j--) {
                        if (isset(self::EMPTY[$this->codes[$j]])) {
                            continue;
                        }

                        if ($this->codes[$j] === 'T_FUNCTION') {
                            $preserve = false;
                        }

                        break;
                    }
                }

                if (!$preserve) {
                    $this->push('T_STRING', $text);
                    continue;
                }
            }

            if ($token->id === T_CONST) {
                $insideConst = true;
            }

            if ($insideConst && $token->id < 256 && ($text === '=' || $text === ';')) {
                $insideConst = false;
            }

            // `static()` is a call.
            if ($token->id === T_STATIC && $lastCode !== 'T_NEW') {
                $next = $this->nextRawIndex($i + 1);
                if ($next !== null && $raw[$next]->text === '(') {
                    $this->push('T_STRING', $text);
                    continue;
                }
            }

            // Doc blocks.
            if (
                $token->id === T_DOC_COMMENT
                || $token->id === T_COMMENT && str_starts_with($text, '/**') && $text !== '/**/'
            ) {
                $this->docComment($text);
                continue;
            }

            // PHP 8 puts the line break after a `//` or `#` comment in the next whitespace token.
            if ($token->id === T_COMMENT && (str_starts_with($text, '//') || str_starts_with($text, '#'))) {
                $nextRaw = $raw[$i + 1] ?? null;
                if ($nextRaw !== null && $nextRaw->id === T_WHITESPACE && !isset($this->consumed[$i + 1])) {
                    $ws = $nextRaw->text;
                    if ($ws === "\n" || $ws === "\r\n" || $ws === "\n\r") {
                        $text .= $ws;
                        $this->consumed[$i + 1] = true;
                    } elseif (str_starts_with($ws, "\r\n") || str_starts_with($ws, "\n\r")) {
                        $text .= substr($ws, 0, 2);
                        $this->replaceText($i + 1, substr($ws, 2));
                    } elseif (str_starts_with($ws, "\n")) {
                        $text .= "\n";
                        $this->replaceText($i + 1, substr($ws, 1));
                    }
                }
            }

            if (
                $token->id === T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG
                || $token->id === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG
            ) {
                $this->push('T_BITWISE_AND', $text);
                continue;
            }

            // A double-quoted string is one token.
            if ($token->id === 34 || $text === 'b"') {
                $content = '"';
                $nested = [];
                $j = $i + 1;
                for (; $j < $count; $j++) {
                    $sub = $raw[$j];
                    $content .= $sub->text;
                    if ($sub->id >= 256) {
                        if (($sub->text === '{' || $sub->text === '${') && $sub->id !== T_ENCAPSED_AND_WHITESPACE) {
                            $nested[] = $j;
                        }
                    } elseif ($sub->text === '}') {
                        array_pop($nested);
                    }

                    if ($sub->id === 34 && $nested === []) {
                        break;
                    }
                }

                $i = $j;
                $this->pushLines('T_DOUBLE_QUOTED_STRING', $content);
                continue;
            }

            // A heredoc: its start and end, and a token per line between.
            if ($token->id === T_START_HEREDOC) {
                $nowdoc = str_contains($text, "'");
                $body = '';
                $j = $i + 1;
                for (; $j < $count; $j++) {
                    if ($raw[$j]->id === T_END_HEREDOC) {
                        break;
                    }

                    $body .= $raw[$j]->text;
                }

                if ($j === $count) {
                    $this->push('T_STRING', $text);
                    continue;
                }

                $this->push($nowdoc ? 'T_START_NOWDOC' : 'T_START_HEREDOC', $text);
                $i = $j;
                $this->pushLines($nowdoc ? 'T_NOWDOC' : 'T_HEREDOC', $body);
                $this->push($nowdoc ? 'T_END_NOWDOC' : 'T_END_HEREDOC', $raw[$j]->text);
                continue;
            }

            // `case` of an enum.
            if ($token->id === T_CASE && !isset(self::STRING_CONTEXTS[$lastCode])) {
                $isEnumCase = false;
                $scope = 1;
                for ($j = $i - 1; $j > 0; $j--) {
                    $t = $raw[$j];
                    if ($t->text === '}' && $t->id < 256) {
                        $scope++;
                        continue;
                    }

                    if ($t->text === '{' && $t->id < 256) {
                        $scope--;
                        continue;
                    }

                    if ($t->id < 256 || $scope !== 0) {
                        continue;
                    }

                    if ($t->id === T_SWITCH) {
                        break;
                    }

                    if ($t->id === T_ENUM || ($this->override[$j] ?? '') === 'T_ENUM_CASE') {
                        $isEnumCase = true;
                        break;
                    }
                }

                if ($isEnumCase) {
                    $this->override[$i] = 'T_ENUM_CASE';
                    $this->push('T_ENUM_CASE', $text);
                    continue;
                }
            }

            // Qualified names are split at the separators.
            if (
                $token->id === T_NAME_QUALIFIED
                || $token->id === T_NAME_FULLY_QUALIFIED
                || $token->id === T_NAME_RELATIVE
            ) {
                $this->splitName($token->id, $text);
                continue;
            }

            if ($token->id === T_ATTRIBUTE) {
                $closer = $this->attributeCloser($i + 1);
                $this->push('T_ATTRIBUTE', '#[');
                if ($closer !== null) {
                    $this->override[$closer] = 'T_ATTRIBUTE_END';
                }

                continue;
            }

            // A named argument's label.
            if ($token->id === T_STRING || preg_match('`^[a-zA-Z_\x80-\xff]`', $text) === 1) {
                $next = $this->nextRawIndex($i + 1);
                if ($next !== null && $raw[$next]->text === ':' && $raw[$next]->id < 256) {
                    $prev = null;
                    for ($j = $i - 1; $j > 0; $j--) {
                        if ($raw[$j]->id < 256 || !isset(self::EMPTY[$this->rawName($j)])) {
                            $prev = $raw[$j];
                            break;
                        }
                    }

                    if ($prev !== null && $prev->id < 256 && ($prev->text === '(' || $prev->text === ',')) {
                        $this->override[$i] = 'T_PARAM_NAME';
                        $this->push('T_PARAM_NAME', $text);
                        continue;
                    }
                }
            }

            // `yield\n  from` is one token in PHP; the whitespace in it is a token of its own.
            if (
                $token->id === T_YIELD_FROM
                && str_contains($text, $this->eol)
                && preg_match('`^yield\s+from$`i', $text) === 1
            ) {
                $this->push('T_YIELD_FROM', substr($text, 0, 5));
                $this->pushLines('T_WHITESPACE', substr($text, 5, -4));
                $this->push('T_YIELD_FROM', substr($text, -4));
                continue;
            }

            if ($token->id === T_MATCH || $token->id === T_STRING && strtolower($text) === 'match') {
                $isMatch = false;
                $next = $this->nextRawIndex($i + 1);
                if ($next !== null && $raw[$next]->text === '(' && !isset(self::STRING_CONTEXTS[$lastCode])) {
                    $isMatch = true;
                }

                if ($isMatch && $token->id === T_STRING) {
                    $this->push('T_MATCH', $text);
                    continue;
                }

                if (!$isMatch && $token->id === T_MATCH) {
                    $this->push('T_STRING', $text);
                    continue;
                }
            }

            if ($token->id === T_DEFAULT && !isset(self::STRING_CONTEXTS[$lastCode])) {
                $x = $i + 1;
                for (; $x < $count; $x++) {
                    if ($raw[$x]->text === ',' && $raw[$x]->id < 256) {
                        continue;
                    }

                    if ($raw[$x]->id < 256 || !isset(self::EMPTY[$this->rawName($x)])) {
                        break;
                    }
                }

                if (isset($raw[$x]) && $raw[$x]->id === T_DOUBLE_ARROW) {
                    $this->override[$x] = 'T_MATCH_ARROW';
                    $this->push('T_MATCH_DEFAULT', $text);
                    continue;
                }
            }

            // `?`: a nullable type or the start of a ternary.
            if ($token->id === 63) {
                $this->push($this->override[$i] ?? $this->questionMark($i, $lastCode, $insideInlineIf), '?');
                continue;
            }

            // Keywords after `::` are names.
            if (
                $i > 1
                && $raw[$i - 1]->id === \T_DOUBLE_COLON
                && $token->id >= 256
                && $token->id !== T_STRING
                && $token->id !== \T_VARIABLE
                && $token->id !== 36
                && !isset(self::EMPTY[$name])
            ) {
                $this->push('T_STRING', $text);
                continue;
            }

            if ($token->id === T_FUNCTION || $token->id === T_FN) {
                if ($lastCode !== 'T_USE') {
                    $this->nullableReturnType($i);
                }
            }

            // A goto label.
            if (
                $token->id === T_STRING
                && isset($raw[$i + 1])
                && $raw[$i + 1]->text === ':'
                && $raw[$i + 1]->id < 256
                && ($raw[$i - 1]->id ?? 0) !== \T_DOUBLE_COLON
            ) {
                $stop = [
                    'T_CASE' => true,
                    'T_SEMICOLON' => true,
                    'T_OPEN_TAG' => true,
                    'T_OPEN_CURLY_BRACKET' => true,
                    'T_INLINE_THEN' => true,
                    'T_ENUM' => true,
                ];
                $x = count($this->codes) - 1;
                for (; $x > 0; $x--) {
                    if (isset($stop[$this->codes[$x]])) {
                        break;
                    }
                }

                if (
                    $this->codes[$x] !== 'T_CASE'
                    && $this->codes[$x] !== 'T_INLINE_THEN'
                    && $this->codes[$x] !== 'T_ENUM'
                ) {
                    $this->push('T_GOTO_LABEL', $text . ':');
                    $this->consumed[$i + 1] = true;
                    continue;
                }
            }

            if ($token->id >= 256 && str_contains($text, $this->eol)) {
                $this->pushLines($name, $text);
                continue;
            }

            // The general case.
            if ($token->id === T_STRING) {
                $preserve = false;
                if ($lastCode === 'T_CONST' && isset(self::STRING_CONTEXTS[$lastCode]) || $insideConst) {
                    $next = $this->nextRawIndex($i + 1);
                    if ($next !== null && $raw[$next]->text === '=') {
                        $preserve = true;
                        $insideConst = false;
                    }
                } elseif (isset(self::STRING_CONTEXTS[$lastCode]) && $lastCode !== 'T_CONST') {
                    $preserve = true;
                    $lower = strtolower($text);
                    if ($lastCode === 'T_NEW' && ($lower === 'self' || $lower === 'parent')) {
                        $preserve = false;
                    }

                    if (
                        $lastCode === 'T_NS_SEPARATOR'
                        && ($lower === 'true' || $lower === 'false' || $lower === 'null')
                    ) {
                        for ($j = $lastNotEmpty - 1; $j >= 0; $j--) {
                            if (isset(self::EMPTY[$this->codes[$j]])) {
                                continue;
                            }

                            if ($this->codes[$j] !== 'T_STRING' && $this->codes[$j] !== 'T_NAMESPACE') {
                                $preserve = false;
                            }

                            break;
                        }
                    }
                } elseif ($lastText === '&') {
                    for ($j = $lastNotEmpty - 1; $j >= 0; $j--) {
                        if (isset(self::EMPTY[$this->codes[$j]])) {
                            continue;
                        }

                        if ($this->codes[$j] === 'T_FUNCTION') {
                            $preserve = true;
                        }

                        break;
                    }
                } else {
                    $next = $this->nextRawIndex($i + 1);
                    if ($next !== null && $raw[$next]->text === '(') {
                        $preserve = true;
                    }
                }

                if ($preserve) {
                    $this->push('T_STRING', $text);
                    continue;
                }
            }

            $code = $this->override[$i] ?? $name;
            if ($token->id === T_STRING) {
                $code = match (strtolower($text)) {
                    'false' => 'T_FALSE',
                    'true' => 'T_TRUE',
                    'null' => 'T_NULL',
                    'self' => 'T_SELF',
                    'parent' => 'T_PARENT',
                    default => 'T_STRING',
                };
            }

            if ($token->id === T_CURLY_OPEN) {
                $code = 'T_OPEN_CURLY_BRACKET';
            }

            if ($insideInlineIf !== [] && $code === 'T_COLON') {
                if ($this->isInlineElse($i, $insideInlineIf)) {
                    array_pop($insideInlineIf);
                    $code = 'T_INLINE_ELSE';
                }
            }

            if ($code === 'T_ARRAY') {
                $next = $this->nextRawIndex($i + 1);
                if ($next !== null && $raw[$next]->text !== '(') {
                    $code = 'T_STRING';
                }
            }

            if ($code === 'T_STRING' && strtolower($text) === 'finally' && $lastCode === 'T_CLOSE_CURLY_BRACKET') {
                $code = 'T_FINALLY';
            }

            if (($code === 'T_FUNCTION' || $code === 'T_CONST') && ($lastCode === 'T_USE' || $insideUseGroup)) {
                $code = 'T_STRING';
            }

            if ($code === 'T_OPEN_CURLY_BRACKET' && $lastCode === 'T_NS_SEPARATOR') {
                $code = 'T_OPEN_USE_GROUP';
                $insideUseGroup = true;
            }

            if ($insideUseGroup && $code === 'T_CLOSE_CURLY_BRACKET') {
                $code = 'T_CLOSE_USE_GROUP';
                $insideUseGroup = false;
            }

            $this->push($code, $text);
        }
    }

    private function push(string $code, string $text): void
    {
        $this->codes[] = $code;
        $this->texts[] = $text;
    }

    /** One token per line, each with its line break. */
    private function pushLines(string $code, string $text): void
    {
        $lines = explode($this->eol, $text);
        $last = count($lines) - 1;
        foreach ($lines as $index => $line) {
            if ($index === $last) {
                if ($line === '') {
                    break;
                }

                $this->push($code, $line);
            } else {
                $this->push($code, $line . $this->eol);
            }
        }
    }

    private function replaceText(int $index, string $text): void
    {
        $token = $this->raw[$index];
        $this->raw[$index] = new PhpToken($token->id, $text, $token->line, $token->pos);
    }

    private function nameOf(int $index): string
    {
        return $this->override[$index] ?? $this->rawName($index);
    }

    private function rawName(int $index): string
    {
        $token = $this->raw[$index];
        if ($token->id < 256) {
            return self::CHARS[$token->text] ?? $token->text;
        }

        $name = $token->getTokenName() ?? $token->text;

        return match ($name) {
            'T_PAAMAYIM_NEKUDOTAYIM' => 'T_DOUBLE_COLON',
            'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' => 'T_BITWISE_AND',
            default => $name,
        };
    }

    /** The next raw token that is not whitespace or a comment. */
    private function nextRawIndex(int $from): ?int
    {
        $count = count($this->raw);
        for ($i = $from; $i < $count; $i++) {
            if (isset($this->consumed[$i])) {
                continue;
            }

            $id = $this->raw[$i]->id;
            if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                return $i;
            }
        }

        return null;
    }

    private function splitName(int $id, string $name): void
    {
        if ($id === T_NAME_FULLY_QUALIFIED) {
            $this->push('T_NS_SEPARATOR', '\\');
            $name = substr($name, 1);
        }

        if ($id === T_NAME_RELATIVE) {
            $this->push('T_NAMESPACE', substr($name, 0, 9));
            $this->push('T_NS_SEPARATOR', '\\');
            $name = substr($name, 10);
        }

        if ($id === T_NAME_FULLY_QUALIFIED) {
            $special = match (strtolower($name)) {
                'exit', 'die' => 'T_EXIT',
                'true' => 'T_TRUE',
                'false' => 'T_FALSE',
                'null' => 'T_NULL',
                default => null,
            };
            if ($special !== null) {
                $this->push($special, $name);

                return;
            }
        }

        $parts = explode('\\', $name);
        $last = count($parts) - 1;
        foreach ($parts as $index => $part) {
            $this->push('T_STRING', $part);
            if ($index !== $last) {
                $this->push('T_NS_SEPARATOR', '\\');
            }
        }
    }

    private function attributeCloser(int $start): ?int
    {
        $depth = 1;
        $count = count($this->raw);
        for ($i = $start; $i < $count; $i++) {
            $token = $this->raw[$i];
            if ($token->text === '[' || $token->id === T_ATTRIBUTE) {
                $depth++;
            } elseif ($token->text === ']') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * PHPCS's `?` handling: T_NULLABLE or T_INLINE_THEN.
     *
     * @param list<int> $insideInlineIf
     */
    private function questionMark(int $i, string $lastCode, array &$insideInlineIf): string
    {
        $raw = $this->raw;
        $count = count($raw);
        if ($lastCode === 'T_CONST') {
            return 'T_NULLABLE';
        }

        $lastRelevant = null;
        for ($x = $i + 1; $x < $count; $x++) {
            $token = $raw[$x];
            $type = $token->id < 256 ? $token->text : $this->rawName($x);
            if (isset(self::EMPTY[$type])) {
                continue;
            }

            if (isset(self::TYPE_NAMES[$type])) {
                $lastRelevant = $type;
                continue;
            }

            if (
                $type !== 'T_CALLABLE' && $lastRelevant === null
                || $lastRelevant === 'T_ARRAY' && $type === '('
                || (
                    $lastRelevant === 'T_STRING'
                    || $lastRelevant === 'T_NAME_FULLY_QUALIFIED'
                    || $lastRelevant === 'T_NAME_RELATIVE'
                    || $lastRelevant === 'T_NAME_QUALIFIED'
                )
                && ($type === 'T_DOUBLE_COLON' || $type === '(' || $type === ':')
            ) {
                $insideInlineIf[] = $i;

                return 'T_INLINE_THEN';
            }

            break;
        }

        if ($x === $count) {
            $insideInlineIf[] = $i;

            return 'T_INLINE_THEN';
        }

        $prevNonEmpty = null;
        $lastSeen = null;
        for ($x = $i - 1; $x >= 0; $x--) {
            $token = $raw[$x];
            $type = $token->id < 256 ? $token->text : $this->rawName($x);
            if ($type === 'T_STATIC' && ($lastSeen === 'T_DOUBLE_COLON' || $lastSeen === '(')) {
                $lastSeen = $type;
                continue;
            }

            if ($prevNonEmpty === null && !isset(self::EMPTY[$type])) {
                if (
                    $type === ':'
                    || $type === ','
                    || $type === 'T_ATTRIBUTE_END'
                    || ($this->override[$x] ?? '') === 'T_ATTRIBUTE_END'
                ) {
                    return 'T_NULLABLE';
                }

                $prevNonEmpty = $type;
            }

            if (
                $type === 'T_FUNCTION'
                || $type === 'T_FN'
                || isset(self::METHOD_PREFIXES[$type])
                || isset(self::SCOPE_MODIFIERS[$type])
                || $type === 'T_VAR'
                || $type === 'T_READONLY'
            ) {
                return 'T_NULLABLE';
            }

            if (in_array($type, ['T_DOUBLE_ARROW', 'T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO', '=', '{', ';'], true)) {
                $insideInlineIf[] = $i;

                return 'T_INLINE_THEN';
            }

            if (!isset(self::EMPTY[$type])) {
                $lastSeen = $type;
            }
        }

        return 'T_INLINE_THEN';
    }

    /**
     * Marks the `?` of a nullable return type after a function's parameters.
     */
    private function nullableReturnType(int $i): void
    {
        $raw = $this->raw;
        $count = count($raw);
        $stack = [];
        $closer = null;
        for ($x = $i + 1; $x < $count; $x++) {
            if ($raw[$x]->id < 256 && $raw[$x]->text === '(') {
                $stack[] = $x;
            } elseif ($raw[$x]->id < 256 && $raw[$x]->text === ')') {
                array_pop($stack);
                if ($stack === []) {
                    $closer = $x;
                    break;
                }
            }
        }

        if ($closer === null) {
            return;
        }

        for ($x = $closer + 1; $x < $count; $x++) {
            if ($raw[$x]->id < 256 || !isset(self::EMPTY[$this->rawName($x)])) {
                if ($raw[$x]->id === T_USE) {
                    for ($x++; $x < $count; $x++) {
                        if ($raw[$x]->id < 256 && $raw[$x]->text === ')') {
                            return;
                        }
                    }
                }

                break;
            }
        }

        if (!isset($raw[$x]) || $raw[$x]->text !== ':' || $raw[$x]->id >= 256) {
            return;
        }

        for ($x++; $x < $count; $x++) {
            if ($raw[$x]->id >= 256 && isset(self::EMPTY[$this->rawName($x)])) {
                continue;
            }

            if ($raw[$x]->text === '?' && $raw[$x]->id < 256) {
                $this->override[$x] = 'T_NULLABLE';
                continue;
            }

            break;
        }
    }

    /**
     * Whether the `:` at $i ends a ternary rather than starting a block or a return type.
     *
     * @param list<int> $insideInlineIf
     */
    private function isInlineElse(int $i, array $insideInlineIf): bool
    {
        $raw = $this->raw;
        $x = $i - 1;
        for (; $x > 0; $x--) {
            if ($raw[$x]->id < 256 || !isset(self::EMPTY[$this->rawName($x)])) {
                break;
            }
        }

        if (($this->override[$x] ?? '') === 'T_PARAM_NAME') {
            return false;
        }

        $x = $i - 1;
        for (; $x > 0; $x--) {
            $id = $raw[$x]->id;
            if ($id < 256 || $id !== T_DOC_COMMENT && $id !== T_COMMENT && $id !== T_WHITESPACE) {
                break;
            }
        }

        if ($raw[$x]->text === ')' && $raw[$x]->id < 256) {
            $parens = 1;
            for ($x--; $x > 0; $x--) {
                if ($raw[$x]->id < 256 && $raw[$x]->text === '(') {
                    $parens--;
                    if ($parens === 0) {
                        break;
                    }
                } elseif ($raw[$x]->id < 256 && $raw[$x]->text === ')') {
                    $parens++;
                }
            }

            for ($x--; $x > 0; $x--) {
                $id = $raw[$x]->id;
                if (
                    $id < 256
                    || $id !== T_DOC_COMMENT && $id !== T_COMMENT && $id !== T_WHITESPACE && $id !== T_STRING
                ) {
                    break;
                }
            }

            if ($raw[$x]->id === T_FUNCTION || $raw[$x]->id === T_FN || $raw[$x]->id === T_USE) {
                return false;
            }
        }

        $inlineIf = $insideInlineIf[count($insideInlineIf) - 1];
        for ($x = $i; $x > $inlineIf; $x--) {
            $id = $raw[$x]->id;
            if ($id === T_CASE || $id === T_DEFAULT) {
                return false;
            }

            if ($id < 256 && ($raw[$x]->text === ';' || $raw[$x]->text === '{' || $raw[$x]->text === '}')) {
                break;
            }
        }

        return true;
    }

    /**
     * The doc block as phpcs's comment tokenizer splits it, kept to the tokens layout sniffs need: the
     * opening tag, the indentation of each line, the rest of the line and the closing tag.
     */
    private function docComment(string $text): void
    {
        $this->push('T_DOC_COMMENT_OPEN_TAG', '/**');
        $body = substr($text, 3);
        $closes = str_ends_with($body, '*/');
        if ($closes) {
            $body = substr($body, 0, -2);
        }

        $lines = explode($this->eol, $body);
        $last = count($lines) - 1;
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $indent = strspn($line, " \t");
                if ($indent > 0) {
                    $this->push('T_DOC_COMMENT_WHITESPACE', substr($line, 0, $indent));
                    $line = substr($line, $indent);
                }
            }

            $line .= $index === $last ? '' : $this->eol;
            if ($line !== '') {
                $this->push('T_DOC_COMMENT_STRING', $line);
            }
        }

        if ($closes) {
            $this->push('T_DOC_COMMENT_CLOSE_TAG', '*/');
        }
    }
}
