<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;
use PhpToken;

use function array_key_last;
use function array_pop;
use function array_reverse;
use function count;
use function in_array;
use function preg_match;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

/**
 * The file's tokens as phpcs sees them, for the WPCS sniffs that are written over tokens
 * rather than a syntax tree (`ValidatedSanitizedInput`, `NonceVerification`), with the
 * phpcs/PHPCSUtils/WPCS helpers they call. Whitespace and comments are dropped, so the
 * previous or next token is always the previous or next non-empty one. A namespaced name is
 * one `T_STRING` holding its last segment (`ns` marks a qualified one), as phpcs splits
 * names. A double-quoted string or heredoc is one token whose `embeds` list the variables it
 * interpolates, as `TextStrings::getEmbeds()` names them.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class PhpcsTokens
{
    private const EMPTY = ['T_WHITESPACE' => true, 'T_COMMENT' => true, 'T_DOC_COMMENT' => true];

    private const NAMES = [
        'T_NAME_FULLY_QUALIFIED' => true,
        'T_NAME_QUALIFIED' => true,
        'T_NAME_RELATIVE' => true,
    ];

    /** Tokens after which `[` is an array access, not a short array. */
    private const ACCESS_BEFORE = [
        'T_VARIABLE' => true,
        ']' => true,
        'T_CLOSE_SHORT_ARRAY' => true,
        ')' => true,
        'T_STRING' => true,
        'T_CONSTANT_ENCAPSED_STRING' => true,
        'T_DOUBLE_QUOTED_STRING' => true,
    ];

    private const CLASS_LIKE = ['T_CLASS' => true, 'T_INTERFACE' => true, 'T_TRAIT' => true, 'T_ENUM' => true];

    private const SAFE_CASTS = [
        'T_INT_CAST' => true,
        'T_DOUBLE_CAST' => true,
        'T_BOOL_CAST' => true,
        'T_UNSET_CAST' => true,
    ];

    private const OBJECT_OPERATORS = [
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_DOUBLE_COLON' => true,
    ];

    private const COMPARISONS = [
        'T_IS_EQUAL' => true,
        'T_IS_IDENTICAL' => true,
        'T_IS_NOT_EQUAL' => true,
        'T_IS_NOT_IDENTICAL' => true,
        '<' => true,
        '>' => true,
        'T_IS_SMALLER_OR_EQUAL' => true,
        'T_IS_GREATER_OR_EQUAL' => true,
        'T_SPACESHIP' => true,
        'T_COALESCE' => true,
    ];

    private const ASSIGNMENTS = [
        '=' => true,
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
    ];

    private const TYPE_TESTS = [
        'is_array' => true,
        'is_bool' => true,
        'is_callable' => true,
        'is_countable' => true,
        'is_double' => true,
        'is_float' => true,
        'is_int' => true,
        'is_integer' => true,
        'is_iterable' => true,
        'is_long' => true,
        'is_null' => true,
        'is_numeric' => true,
        'is_object' => true,
        'is_real' => true,
        'is_resource' => true,
        'is_scalar' => true,
        'is_string' => true,
    ];

    public const KEY_EXISTS = ['array_key_exists' => true, 'key_exists' => true];

    private const ARRAY_COMPARE = ['in_array' => true, 'array_search' => true, 'array_keys' => true];

    /**
     * @param list<array{code: string, content: string, pos: int, ns: bool, gap: bool, embeds: list<array{string, int}>}> $tokens
     * @param array<int, int> $pairs opener => closer and closer => opener, for `()`, `[]` and `{}`
     * @param array<int, list<int>> $nested token => the `(` around it, outermost first
     * @param array<int, int> $scopes scope owner (function, closure, class-like, `fn`) => its closer
     * @param array<int, int> $functions token => the `{` of the innermost function or closure around it
     * @param array<int, true> $ooDirect tokens directly in a class-like body
     */
    private function __construct(
        public readonly array $tokens,
        private readonly array $pairs,
        private readonly array $nested,
        private readonly array $scopes,
        private readonly array $functions,
        private readonly array $ooDirect,
    ) {}

    public static function of(SourceFile $file): self
    {
        return FileCache::remember($file, 'phpcs-tokens', static fn(): self => self::build($file->contents));
    }

    public static function build(string $contents): self
    {
        $tokens = self::tokenize($contents);
        $pairs = self::pair($tokens);
        [$scopes, $owners] = self::scopes($tokens, $pairs);

        // One pass for the parentheses and the brace scopes each token sits in.
        /** @var array<int, list<int>> $nested */
        $nested = [];
        /** @var array<int, int> $functions */
        $functions = [];
        $ooDirect = [];
        /** @var list<int> $parens */
        $parens = [];
        /** @var list<string> $braces */
        $braces = [];
        /** @var list<int> $functionStack */
        $functionStack = [];
        foreach ($tokens as $index => $token) {
            $code = $token['code'];
            if ($code === ')' && ($pairs[$index] ?? null) !== null) {
                array_pop($parens);
            } elseif ($code === '}' && ($pairs[$index] ?? null) !== null) {
                if (array_pop($braces) === 'f') {
                    array_pop($functionStack);
                }
            }

            if ($parens !== []) {
                $nested[$index] = $parens;
            }

            if ($functionStack !== []) {
                $functions[$index] = $functionStack[array_key_last($functionStack)];
            }

            if ($braces !== [] && $braces[array_key_last($braces)] === 'c') {
                $ooDirect[$index] = true;
            }

            if ($code === '(' && ($pairs[$index] ?? null) !== null) {
                $parens[] = $index;
            } elseif ($code === '{' && ($pairs[$index] ?? null) !== null) {
                $kind = $owners[$index] ?? 'o';
                $braces[] = $kind;
                if ($kind === 'f') {
                    $functionStack[] = $index;
                }
            }
        }

        return new self($tokens, $pairs, $nested, $scopes, $functions, $ooDirect);
    }

    public function code(int $index): ?string
    {
        return $this->tokens[$index]['code'] ?? null;
    }

    public function content(int $index): string
    {
        return $this->tokens[$index]['content'] ?? '';
    }

    public function closer(int $index): ?int
    {
        return $this->pairs[$index] ?? null;
    }

    public function scopeCloser(int $owner): ?int
    {
        return $this->scopes[$owner] ?? null;
    }

    /** The `{` of the innermost function or closure around the token (`Conditions::getLastCondition()`). */
    public function functionOpener(int $index): ?int
    {
        return $this->functions[$index] ?? null;
    }

    /**
     * @return list<int>
     */
    public function nested(int $index): array
    {
        return $this->nested[$index] ?? [];
    }

    /** `Scopes::isOOProperty()`. */
    public function isOOProperty(int $index): bool
    {
        if (($this->ooDirect[$index] ?? null) === null) {
            return false;
        }

        $nested = $this->nested($index);

        // Not a method parameter: the innermost parentheses do not belong to a `function`.
        $owner = $nested === [] ? null : $nested[count($nested) - 1] - 1;
        $owner = $this->code((int) $owner) === 'T_STRING' ? (int) $owner - 1 : $owner;
        $owner = $this->code((int) $owner) === '&' ? (int) $owner - 1 : $owner;

        return $owner === null || $this->code($owner) !== 'T_FUNCTION';
    }

    /** `ContextHelper::has_object_operator_before()`. */
    public function hasObjectOperatorBefore(int $index): bool
    {
        return (self::OBJECT_OPERATORS[$this->code($index - 1) ?? ''] ?? null) !== null;
    }

    /** `ContextHelper::is_token_namespaced()`. */
    public function isNamespaced(int $index): bool
    {
        return $this->tokens[$index]['ns'] ?? false;
    }

    /**
     * `ContextHelper::is_in_function_call()`: the name of the function call whose parentheses
     * hold the token, innermost first, stopping at the first other call unless `$allowNested`.
     *
     * @param array<string, mixed> $functions lowercase names
     */
    public function inFunctionCall(int $index, array $functions, bool $global = true, bool $allowNested = false): ?int
    {
        $nested = $this->nested($index);
        if (!$allowNested) {
            $nested = array_reverse($nested);
        }

        foreach ($nested as $open) {
            $name = $open - 1;
            if ($this->code($name) !== 'T_STRING') {
                continue;
            }

            if (($functions[strtolower($this->content($name))] ?? null) === null) {
                if (!$allowNested) {
                    return null;
                }

                continue;
            }

            if (!$global) {
                return $name;
            }

            if ($this->hasObjectOperatorBefore($name) || $this->isNamespaced($name)) {
                continue;
            }

            return $name;
        }

        return null;
    }

    /** `Parentheses::lastOwnerIn()`: the innermost parentheses belong to one of the keywords. */
    public function lastOwnerIn(int $index, string ...$owners): bool
    {
        $nested = $this->nested($index);

        return $nested !== [] && in_array($this->code($nested[count($nested) - 1] - 1), $owners, strict: true);
    }

    /** `Context::inUnset()`. */
    public function inUnset(int $index): bool
    {
        foreach ($this->nested($index) as $open) {
            if ($this->code($open - 1) === 'T_UNSET') {
                return true;
            }
        }

        return false;
    }

    /** `ContextHelper::is_in_isset_or_empty()`. */
    public function inIssetOrEmpty(int $index): bool
    {
        if ($this->lastOwnerIn($index, 'T_ISSET', 'T_EMPTY')) {
            return true;
        }

        $function = $this->inFunctionCall($index, self::KEY_EXISTS);
        $array = $function === null ? null : $this->parameter($function, 2, 'array');

        return $array !== null && $index >= $array['start'] && $index <= $array['end'];
    }

    /** `ContextHelper::is_in_type_test()`. */
    public function inTypeTest(int $index): bool
    {
        return $this->inFunctionCall($index, self::TYPE_TESTS) !== null;
    }

    /** `ContextHelper::is_safe_casted()`. */
    public function isSafeCasted(int $index): bool
    {
        return (self::SAFE_CASTS[$this->code($index - 1) ?? ''] ?? null) !== null;
    }

    /** `ContextHelper::is_in_array_comparison()`. */
    public function inArrayComparison(int $index): bool
    {
        $function = $this->inFunctionCall($index, self::ARRAY_COMPARE, allowNested: true);
        if ($function === null) {
            return false;
        }

        return (
            strtolower($this->content($function)) !== 'array_keys'
            || $this->parameter($function, 2, 'filter_value') !== null
        );
    }

    /** `VariableHelper::is_comparison()`. */
    public function isComparison(int $index, bool $includeCoalesce = true): bool
    {
        if ($this->lastOwnerIn($index, 'T_SWITCH', 'T_MATCH')) {
            return true;
        }

        $comparisons = self::COMPARISONS;
        if (!$includeCoalesce) {
            unset($comparisons['T_COALESCE']);
        }

        if (($comparisons[$this->code($index - 1) ?? ''] ?? null) !== null) {
            return true;
        }

        return ($comparisons[$this->code($this->afterAccess($index)) ?? ''] ?? null) !== null;
    }

    /** `VariableHelper::is_assignment()`. */
    public function isAssignment(int $index, bool $includeCoalesce = true): bool
    {
        $code = $this->code($index);
        if ($code !== 'T_VARIABLE' && $code !== ']') {
            return false;
        }

        $next = $this->code($index + 1) ?? '';
        if ((self::ASSIGNMENTS[$next] ?? null) !== null) {
            return $includeCoalesce || $next !== 'T_COALESCE_EQUAL';
        }

        $closer = $next === '[' ? $this->closer($index + 1) : null;

        return $closer !== null && $this->isAssignment($closer, $includeCoalesce);
    }

    /** The first token after a variable and its `[...]` accesses. */
    public function afterAccess(int $index): int
    {
        $next = $index + 1;
        while ($this->code($next) === '[' && ($closer = $this->closer($next)) !== null) {
            $next = $closer + 1;
        }

        return $next;
    }

    /**
     * `VariableHelper::get_array_access_keys()`.
     *
     * @return list<string>
     */
    public function arrayAccessKeys(int $index): array
    {
        $keys = [];
        if ($this->code($index) !== 'T_VARIABLE') {
            return $keys;
        }

        $next = $index + 1;
        while ($this->code($next) === '[' && ($closer = $this->closer($next)) !== null) {
            $keys[] = trim($this->compact($next + 1, $closer - 1));
            $next = $closer + 1;
        }

        return $keys;
    }

    /** `GetTokensAsString::compact()` without comments: whitespace runs become one space. */
    public function compact(int $start, int $end): string
    {
        $text = '';
        for ($index = $start; $index <= $end; $index++) {
            $text .= ($index > $start && $this->tokens[$index]['gap'] ? ' ' : '') . $this->content($index);
        }

        return $text;
    }

    /**
     * `PassedParameters::getParameter()` for the call whose name is at `$function`: the
     * positional parameter, or the named one.
     *
     * @return null|array{start: int, end: int, raw: string, name: ?string}
     */
    public function parameter(int $function, int $position, string $name): ?array
    {
        $parameters = $this->parameters($function);
        $positional = $parameters[$position - 1] ?? null;
        if ($positional !== null && $positional['name'] === null) {
            return $positional;
        }

        foreach ($parameters as $parameter) {
            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * @return list<array{start: int, end: int, raw: string, name: ?string}>
     * @mago-expect lint:halstead
     */
    private function parameters(int $function): array
    {
        $open = $function + 1;
        $close = $this->code($open) === '(' ? $this->closer($open) : null;
        if ($close === null || $close === ($open + 1)) {
            return [];
        }

        $parameters = [];
        $start = $open + 1;
        for ($index = $start; $index <= $close; $index++) {
            $code = $this->code($index);
            if ($index < $close && $code !== ',') {
                if (in_array($code, ['(', '[', 'T_ATTRIBUTE', 'T_OPEN_SHORT_ARRAY', '{'], strict: true)) {
                    $index = $this->closer($index) ?? $index;
                }

                continue;
            }

            if ($index > $start) {
                $name = null;
                $first = $start;
                if (
                    ($index - $start) > 2
                    && $this->code($start + 1) === ':'
                    && preg_match('/^\w+$/', $this->content($start)) === 1
                ) {
                    $name = $this->content($start);
                    $first = $start + 2;
                }

                $parameters[] = [
                    'start' => $first,
                    'end' => $index - 1,
                    'raw' => trim($this->compact($first, $index - 1)),
                    'name' => $name,
                ];
            }

            $start = $index + 1;
        }

        return $parameters;
    }

    /** `TextStrings::stripQuotes()`. */
    public static function stripQuotes(string $text): string
    {
        $matches = [];

        return preg_match('/^([\'"])(.*)\1$/s', $text, $matches) === 1 ? $matches[2] : $text;
    }

    /**
     * @return list<array{code: string, content: string, pos: int, ns: bool, gap: bool, embeds: list<array{string, int}>}>
     * @mago-expect lint:halstead
     */
    private static function tokenize(string $contents): array
    {
        $raw = PhpToken::tokenize($contents);
        $tokens = [];
        $gap = false;
        /** @var list<string> $squares */
        $squares = [];
        $count = count($raw);
        for ($index = 0; $index < $count; $index++) {
            $token = $raw[$index];
            $code = $token->getTokenName() ?? $token->text;
            if ((self::EMPTY[$code] ?? null) !== null) {
                $gap = true;
                continue;
            }

            $content = $token->text;
            $ns = false;
            $embeds = [];
            if ((self::NAMES[$code] ?? null) !== null) {
                $last = strrpos($content, needle: '\\');
                $ns = $code !== 'T_NAME_FULLY_QUALIFIED' || $last !== 0;
                $content = substr($content, (int) $last + 1);
                $code = 'T_STRING';
            } elseif ($code === '"' || $code === 'T_START_HEREDOC') {
                [$index, $embeds, $content] = self::string($raw, $index);
                $code = $code === '"' ? 'T_DOUBLE_QUOTED_STRING' : 'T_HEREDOC';
            } elseif ($code === '[') {
                $previous = $tokens === [] ? '' : $tokens[count($tokens) - 1]['code'];
                $code = (self::ACCESS_BEFORE[$previous] ?? null) !== null ? '[' : 'T_OPEN_SHORT_ARRAY';
                $squares[] = $code;
            } elseif ($code === 'T_ATTRIBUTE') {
                $squares[] = $code;
            } elseif ($code === ']') {
                $code = array_pop($squares) === 'T_OPEN_SHORT_ARRAY' ? 'T_CLOSE_SHORT_ARRAY' : ']';
            }

            $tokens[] = [
                'code' => $code,
                'content' => $content,
                'pos' => $token->pos,
                'ns' => $ns,
                'gap' => $gap,
                'embeds' => $embeds,
            ];
            $gap = false;
        }

        return $tokens;
    }

    /**
     * Folds a double-quoted string or heredoc into one token, keeping the position and
     * name of each interpolated variable. As in `TextStrings::getEmbeds()`, only the
     * variable an embed starts with counts, not one in its index or braces.
     *
     * @param list<PhpToken> $raw
     * @return array{int, list<array{string, int}>, string}
     * @mago-expect lint:halstead
     */
    private static function string(array $raw, int $index): array
    {
        $end = $raw[$index]->text === '"' ? '"' : 'T_END_HEREDOC';
        $content = $raw[$index]->text;
        $embeds = [];
        $depth = 0;
        $count = count($raw);
        for ($index++; $index < $count; $index++) {
            $token = $raw[$index];
            $code = $token->getTokenName() ?? $token->text;
            $content .= $token->text;
            if ($depth === 0 && $code === $end) {
                break;
            }

            if ($code === 'T_CURLY_OPEN' || $code === 'T_DOLLAR_OPEN_CURLY_BRACES') {
                // `${(name)}` counts too, as the `getEmbeds()` name pattern allows.
                $next = $raw[$index + 1] ?? null;
                $next =
                    $next?->text === '(' && $code === 'T_DOLLAR_OPEN_CURLY_BRACES' ? $raw[$index + 2] ?? null : $next;
                if (
                    $depth === 0
                    && $next !== null
                    && in_array($next->getTokenName(), ['T_VARIABLE', 'T_STRING_VARNAME', 'T_STRING'], strict: true)
                ) {
                    $embeds[] = [
                        $next->getTokenName() === 'T_VARIABLE' ? substr($next->text, offset: 1) : $next->text,
                        $next->pos,
                    ];
                }

                $depth++;
            } elseif ($code === '{') {
                $depth++;
            } elseif ($code === '}') {
                $depth--;
            } elseif ($depth === 0 && $code === 'T_VARIABLE') {
                $embeds[] = [substr($token->text, offset: 1), $token->pos];
                // A simple embed's index (`"$a[$b]"`) is part of it.
                if (($raw[$index + 1]->text ?? '') === '[') {
                    while (($index + 1) < $count && $raw[$index]->text !== ']') {
                        $index++;
                        $content .= $raw[$index]->text;
                    }
                }
            }
        }

        return [$index, $embeds, $content];
    }

    /**
     * Pairs `()`, `[]` and `{}`; an unmatched one (a parse error) stays unpaired.
     *
     * @param list<array{code: string, content: string, pos: int, ns: bool, gap: bool, embeds: list<array{string, int}>}> $tokens
     * @return array<int, int>
     */
    private static function pair(array $tokens): array
    {
        $openers = [
            '(' => ')',
            '[' => ']',
            'T_ATTRIBUTE' => ']',
            'T_OPEN_SHORT_ARRAY' => 'T_CLOSE_SHORT_ARRAY',
            '{' => '}',
        ];
        /** @var array<string, list<int>> $stacks */
        $stacks = [];
        /** @var array<int, int> $pairs */
        $pairs = [];
        foreach ($tokens as $index => $token) {
            $code = $token['code'];
            if (($openers[$code] ?? null) !== null) {
                $stacks[$openers[$code]][] = $index;
            } elseif (($stacks[$code] ?? []) !== []) {
                $open = (int) array_pop($stacks[$code]);
                $pairs[$open] = $index;
                $pairs[$index] = $open;
            }
        }

        return $pairs;
    }

    /**
     * The scope owners phpcs skips as closed scopes: functions, closures and class-likes
     * (closer: their `}`) and arrow functions (closer: the last token of their expression).
     *
     * @param list<array{code: string, content: string, pos: int, ns: bool, gap: bool, embeds: list<array{string, int}>}> $tokens
     * @param array<int, int> $pairs
     * @return array{array<int, int>, array<int, 'f'|'c'>} owner => closer, `{` => owner kind
     * @mago-expect lint:halstead
     */
    private static function scopes(array $tokens, array $pairs): array
    {
        $scopes = [];
        $owners = [];
        $count = count($tokens);
        foreach ($tokens as $index => $token) {
            $code = $token['code'];
            $kind = match (true) {
                $code === 'T_FUNCTION' => 'f',
                $code === 'T_FN' => 'fn',
                (self::CLASS_LIKE[$code] ?? null) !== null && ($tokens[$index - 1]['code'] ?? '') !== 'T_DOUBLE_COLON'
                    => 'c',
                default => null,
            };
            if ($kind === null) {
                continue;
            }

            // Skip to the body, over the parameter list, a closure's `use` and an anonymous class's arguments.
            for ($next = $index + 1; $next < $count; $next++) {
                $nextCode = $tokens[$next]['code'];
                if ($nextCode === '(') {
                    $next = $pairs[$next] ?? $count;
                } elseif ($nextCode === '{' || $nextCode === ';' || $kind === 'fn' && $nextCode === 'T_DOUBLE_ARROW') {
                    break;
                }
            }

            $nextCode = $tokens[$next]['code'] ?? null;
            if ($nextCode === '{' && $kind !== 'fn' && ($pairs[$next] ?? null) !== null) {
                $scopes[$index] = $pairs[$next];
                $owners[$next] = $kind === 'f' ? 'f' : 'c';
            } elseif ($nextCode === 'T_DOUBLE_ARROW' && $kind === 'fn') {
                $scopes[$index] = self::expressionEnd($tokens, $pairs, $next + 1);
            }
        }

        return [$scopes, $owners];
    }

    /**
     * @param list<array{code: string, content: string, pos: int, ns: bool, gap: bool, embeds: list<array{string, int}>}> $tokens
     * @param array<int, int> $pairs
     */
    private static function expressionEnd(array $tokens, array $pairs, int $index): int
    {
        $count = count($tokens);
        for (; $index < $count; $index++) {
            $code = $tokens[$index]['code'];
            if (in_array($code, [';', ',', ')', ']', 'T_CLOSE_SHORT_ARRAY', '}', 'T_CLOSE_TAG'], strict: true)) {
                return $index - 1;
            }

            if (
                ($pairs[$index] ?? null) !== null
                && in_array($code, ['(', '[', 'T_ATTRIBUTE', 'T_OPEN_SHORT_ARRAY', '{'], strict: true)
            ) {
                $index = $pairs[$index];
            }
        }

        return $count - 1;
    }

    /**
     * @return array{int, int} byte offsets
     */
    public function span(int $index): array
    {
        $pos = $this->tokens[$index]['pos'];

        return [$pos, $pos + strlen($this->tokens[$index]['content'])];
    }
}
