<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\WordPress\Lists;
use Rlorenzo\MagoWordPress\Settings;

use function array_fill_keys;
use function array_merge;
use function count;
use function implode;
use function in_array;
use function is_int;
use function ltrim;
use function max;
use function preg_match;
use function preg_replace;
use function sprintf;
use function strlen;
use function strtolower;
use function trim;

/**
 * Ports `WordPress.Security.EscapeOutput`: output from `echo`, `print`, `<?=`, `exit`/`die`,
 * uncaught `throw` and WordPress's printing functions must go through an escaping function.
 *
 * The sniff works on phpcs tokens (what it skips depends on token order, not on the
 * expression tree), so this port walks the same tokens (PhpcsTokenStream) and follows the sniff
 * line by line.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:halstead
 * @mago-expect lint:too-many-methods
 */
final class EscapeOutputRule implements Rule
{
    private const SNIFF = 'WordPress.Security.EscapeOutput';

    private const MESSAGE = 'All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found \'%s\'.';

    /** WPCS: function => [alternative, [position => name] of the parameters still checked]. */
    private const UNSAFE_PRINTING = [
        '_e' => ['esc_html_e() or esc_attr_e()', [1 => 'text']],
        '_ex' => ['echo esc_html_x() or echo esc_attr_x()', [1 => 'text']],
    ];

    private const SAFE_PHP_CONSTANTS = [
        'PHP_EOL' => true,
        'PHP_VERSION' => true,
        'PHP_MAJOR_VERSION' => true,
        'PHP_MINOR_VERSION' => true,
        'PHP_RELEASE_VERSION' => true,
        'PHP_VERSION_ID' => true,
        'PHP_EXTRA_VERSION' => true,
        'PHP_DEBUG' => true,
    ];

    /** The sniff's safe components plus phpcs's comparison, arithmetic, boolean and ++/-- tokens. */
    private const SAFE_COMPONENTS = [
        'T_LNUMBER' => true,
        'T_DNUMBER' => true,
        'T_TRUE' => true,
        'T_FALSE' => true,
        'T_NULL' => true,
        'T_CONSTANT_ENCAPSED_STRING' => true,
        'T_START_NOWDOC' => true,
        'T_NOWDOC' => true,
        'T_END_NOWDOC' => true,
        'T_BOOLEAN_NOT' => true,
        'T_IS_EQUAL' => true,
        'T_IS_IDENTICAL' => true,
        'T_IS_NOT_EQUAL' => true,
        'T_IS_NOT_IDENTICAL' => true,
        'T_LESS_THAN' => true,
        'T_GREATER_THAN' => true,
        'T_IS_SMALLER_OR_EQUAL' => true,
        'T_IS_GREATER_OR_EQUAL' => true,
        'T_SPACESHIP' => true,
        'T_COALESCE' => true,
        'T_MINUS' => true,
        'T_PLUS' => true,
        'T_MULTIPLY' => true,
        'T_DIVIDE' => true,
        'T_MODULUS' => true,
        'T_POW' => true,
        'T_BITWISE_AND' => true,
        'T_BITWISE_OR' => true,
        'T_BITWISE_XOR' => true,
        'T_SL' => true,
        'T_SR' => true,
        'T_BOOLEAN_AND' => true,
        'T_BOOLEAN_OR' => true,
        'T_LOGICAL_AND' => true,
        'T_LOGICAL_OR' => true,
        'T_LOGICAL_XOR' => true,
        'T_INC' => true,
        'T_DEC' => true,
    ];

    private const TARGET_KEYWORDS = ['T_EXIT' => true, 'T_PRINT' => true, 'T_THROW' => true];

    private const MAGIC_CONSTANTS = [
        'T_CLASS_C' => true,
        'T_DIR' => true,
        'T_FILE' => true,
        'T_FUNC_C' => true,
        'T_LINE' => true,
        'T_METHOD_C' => true,
        'T_NS_C' => true,
        'T_TRAIT_C' => true,
    ];

    private const SAFE_CASTS = [
        'T_INT_CAST' => true,
        'T_DOUBLE_CAST' => true,
        'T_BOOL_CAST' => true,
        'T_UNSET_CAST' => true,
    ];

    private const OO_HIERARCHY = ['T_SELF' => true, 'T_PARENT' => true, 'T_STATIC' => true];

    /** ArrayWalkingFunctionsHelper: function => [callback position, name]. */
    private const ARRAY_WALKING = ['array_map' => [1, 'callback'], 'map_deep' => [2, 'callback']];

    /** @var array<string, true> */
    private readonly array $escaping;

    /** @var array<string, true> */
    private readonly array $autoEscaped;

    /** @var array<string, true> printing functions, minus the unsafe ones */
    private readonly array $printing;

    /** @var array<string, true> */
    private readonly array $formatting;

    /** @var list<PhpcsToken> */
    private array $tokens = [];

    private ?LintContext $context = null;

    public function __construct(
        private readonly Report $report,
        Settings $settings,
    ) {
        $this->escaping = array_fill_keys(
            array_merge(Lists::ESCAPING_FUNCTIONS, $settings->customList('custom-escaping-functions')),
            value: true,
        );
        // WPCS lists `get_the_ID` and looks it up lowercased, so its built-in entry never matches.
        $builtIn = array_fill_keys(Lists::AUTO_ESCAPED_FUNCTIONS, value: true);
        unset($builtIn['get_the_id']);
        $this->autoEscaped =
            $builtIn + array_fill_keys($settings->customList('custom-auto-escaped-functions'), value: true);
        $printing = array_fill_keys(
            array_merge(Lists::PRINTING_FUNCTIONS, $settings->customList('custom-printing-functions')),
            value: true,
        );
        foreach (self::UNSAFE_PRINTING as $name => $_) {
            unset($printing[$name]);
        }

        $this->printing = $printing;
        $this->formatting = array_fill_keys(Lists::FORMATTING_FUNCTIONS, value: true);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/escape-output',
            name: 'Escape output',
            description: 'Reports output from echo, print, <?=, exit, uncaught exceptions and the WordPress printing functions that is not run through an escaping function.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $this->context = $context;
        $this->tokens = PhpcsTokenStream::fromSource($context->file->contents);
        $count = count($this->tokens);
        $ignoreTo = null;
        for ($ptr = 0; $ptr < $count; $ptr++) {
            // phpcs does not call a sniff again for tokens before the pointer it returned.
            if ($ignoreTo !== null && $ignoreTo > $ptr) {
                continue;
            }

            $code = $this->tokens[$ptr]->code;
            if (
                $code !== 'T_STRING'
                && $code !== 'T_ECHO'
                && $code !== 'T_OPEN_TAG_WITH_ECHO'
                && (self::TARGET_KEYWORDS[$code] ?? null) === null
            ) {
                continue;
            }

            $result = $this->processToken($ptr);
            if ($result !== null) {
                $ignoreTo = $result;
            }
        }

        $this->tokens = [];
        $this->context = null;
    }

    private function processToken(int $ptr): ?int
    {
        $tokens = $this->tokens;
        $start = $ptr + 1;
        switch ($tokens[$ptr]->code) {
            case 'T_STRING':
                return $this->processFunctionCall($ptr);
            case 'T_EXIT':
                $params = $this->parameters($ptr);
                if ($params === []) {
                    return null;
                }

                $last = null;
                foreach ($params as $param) {
                    $this->checkCodeIsEscaped($param['start'], $param['end'] + 1);
                    $last = $param['end'] + 1;
                }

                return $last;
            case 'T_THROW':
                return $this->processThrow($ptr);
            case 'T_PRINT':
                $end = $this->findEndOfStatement($ptr);
                $endCode = $tokens[$end]->code;
                if (
                    !in_array($endCode, ['T_COMMA', 'T_SEMICOLON', 'T_COLON', 'T_DOUBLE_ARROW'], strict: true)
                    && ($tokens[$end + 1] ?? null) !== null
                ) {
                    // findEndOfStatement() returns the last token of the expression; the check needs the one after.
                    $end++;
                }

                if ($end >= (count($tokens) - 1)) {
                    $last = $this->previousNonEmpty($end + 1);
                    if ($last === null || $tokens[$last]->code !== 'T_SEMICOLON') {
                        return null;
                    }
                }

                // A print *within* a ternary ends at the "inline else".
                $prev = $this->previousNonEmpty($ptr);
                if ($prev !== null && $tokens[$prev]->code === 'T_INLINE_THEN') {
                    $level = $tokens[$ptr]->parens;
                    $inlineElse = null;
                    for ($i = $ptr + 1; $i < $end; $i++) {
                        if (!($tokens[$i]->code === 'T_INLINE_ELSE' && $tokens[$i]->parens === $level)) {
                            continue;
                        }

                        $inlineElse = $i;
                        break;
                    }

                    if ($inlineElse === null) {
                        return null;
                    }

                    $end = $inlineElse;
                }

                break;
            default:
                // Echo, open tag with echo.
                $end = null;
                for ($i = $ptr; $i < count($tokens); $i++) {
                    if (!($tokens[$i]->code === 'T_SEMICOLON' || $tokens[$i]->code === 'T_CLOSE_TAG')) {
                        continue;
                    }

                    $end = $i;
                    break;
                }

                if ($end === null) {
                    return null;
                }

                break;
        }

        return $this->checkCodeIsEscaped($start, $end);
    }

    /**
     * AbstractFunctionRestrictionsSniff::process_token() for the two printing-function groups.
     */
    private function processFunctionCall(int $ptr): ?int
    {
        $tokens = $this->tokens;
        $name = strtolower($tokens[$ptr]->content);
        $unsafe = (self::UNSAFE_PRINTING[$name] ?? null) !== null;
        if (!$unsafe && ($this->printing[$name] ?? null) === null) {
            return null;
        }

        if (!$this->isTargettedToken($ptr)) {
            return null;
        }

        // Only actual function calls, not function import use statements.
        $open = $this->nextNonEmpty($ptr);
        if ($open === null || $tokens[$open]->code !== 'T_OPEN_PARENTHESIS' || $tokens[$open]->closer === null) {
            return null;
        }

        $end = $tokens[$open]->closer;
        $params = $this->parameters($ptr);
        if ($unsafe) {
            [$alternative, $checked] = self::UNSAFE_PRINTING[$name];
            $reported = $this->error(
                sprintf(
                    "All output should be run through an escaping function (like %s), found '%s'.",
                    $alternative,
                    $name,
                ),
                $ptr,
                'UnsafePrintingFunction',
            );
            // When the error is reported (not silenced), the arguments are not examined.
            if ($reported) {
                return $end;
            }

            foreach ($checked as $position => $paramName) {
                $param = self::parameterFromStack($params, $position, $paramName);
                if ($param !== null) {
                    $this->checkCodeIsEscaped($param['start'], $param['end'] + 1);
                }
            }

            return $end;
        }

        // trigger_error()/user_error() only print their message; the second argument is the error level.
        if ($name === 'trigger_error' || $name === 'user_error') {
            $message = self::parameterFromStack($params, 1, 'message');
            if ($message === null) {
                return $end;
            }

            return $this->checkCodeIsEscaped($message['start'], $message['end'] + 1);
        }

        if ($name === '_deprecated_file') {
            $file = self::parameterFromStack($params, 1, 'file');
            if (
                $file !== null
                && preg_match('`^[\\\\]?basename\s*\(\s*__FILE__\s*\)$`i', $this->clean($file['start'], $file['end']))
                    === 1
            ) {
                foreach ($params as $key => $param) {
                    if ($param !== $file) {
                        continue;
                    }

                    unset($params[$key]);
                }
            }
        }

        foreach ($params as $param) {
            $this->checkCodeIsEscaped($param['start'], $param['end'] + 1);
        }

        return $end;
    }

    private function isTargettedToken(int $ptr): bool
    {
        $tokens = $this->tokens;
        $prev = $this->previousNonEmpty($ptr);
        $prevCode = $prev === null ? '' : $tokens[$prev]->code;
        if (in_array($prevCode, ['T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON'], strict: true)) {
            return false;
        }

        if ($this->isNamespaced($ptr) || $this->inAttribute($ptr)) {
            return false;
        }

        while ($prev !== null && $tokens[$prev]->code === 'T_BITWISE_AND') {
            $prev = $this->previousNonEmpty($prev);
        }

        $prevCode = $prev === null ? '' : $tokens[$prev]->code;
        if (in_array(
            $prevCode,
            ['T_CLASS', 'T_ANON_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM', 'T_FUNCTION', 'T_NEW', 'T_AS'],
            strict: true,
        )) {
            return false;
        }

        $next = $this->nextNonEmpty($ptr);

        return $next !== null && $tokens[$next]->code === 'T_OPEN_PARENTHESIS';
    }

    private function isNamespaced(int $ptr): bool
    {
        $prev = $this->previousNonEmpty($ptr);
        if ($prev === null || $this->tokens[$prev]->code !== 'T_NS_SEPARATOR') {
            return false;
        }

        $before = $this->previousNonEmpty($prev);

        return $before !== null && in_array($this->tokens[$before]->code, ['T_STRING', 'T_NAMESPACE'], strict: true);
    }

    private function inAttribute(int $ptr): bool
    {
        for ($i = $ptr - 1; $i >= 0; $i--) {
            $token = $this->tokens[$i];
            if ($token->code === 'T_ATTRIBUTE' && ($token->closer ?? -1) > $ptr) {
                return true;
            }

            if ($token->code === 'T_SEMICOLON' || $token->code === 'T_OPEN_CURLY_BRACKET') {
                return false;
            }
        }

        return false;
    }

    private function processThrow(int $ptr): ?int
    {
        $tokens = $this->tokens;
        $count = count($tokens);
        // Find the open parenthesis, stepping over the exception creation tokens.
        $skip =
            [
                'T_NS_SEPARATOR' => true,
                'T_NAMESPACE' => true,
                'T_STRING' => true,
                'T_VARIABLE' => true,
                'T_STATIC' => true,
                'T_SELF' => true,
                'T_PARENT' => true,
                'T_OBJECT_OPERATOR' => true,
                'T_NULLSAFE_OBJECT_OPERATOR' => true,
                'T_DOUBLE_COLON' => true,
                'T_READONLY' => true,
                'T_NEW' => true,
                'T_ANON_CLASS' => true,
            ] + PhpcsTokenStream::EMPTY;
        $next = $ptr;
        do {
            $found = null;
            for ($i = $next + 1; $i < $count; $i++) {
                if (($skip[$tokens[$i]->code] ?? null) !== null) {
                    continue;
                }

                $found = $i;
                break;
            }

            if ($found === null) {
                return null;
            }

            $next = $found;
            if ($tokens[$next]->code === 'T_ATTRIBUTE') {
                if ($tokens[$next]->closer === null) {
                    return null;
                }

                $next = $tokens[$next]->closer;
                continue;
            }

            break;
        } while ($next < ($count - 1));

        if ($tokens[$next]->code !== 'T_OPEN_PARENTHESIS' || $tokens[$next]->closer === null) {
            // Live coding/parse error or a pre-created exception.
            return null;
        }

        $end = $tokens[$next]->closer;
        if ($this->lastConditionIsTry($ptr)) {
            // This exception will (probably) be caught.
            return $end;
        }

        $call = $this->previousNonEmpty($next);
        $params = $call === null ? [] : $this->parameters($call);
        foreach ($params as $param) {
            $this->checkCodeIsEscaped($param['start'], $param['end'] + 1, 'ExceptionNotEscaped');
        }

        return $end;
    }

    /**
     * Conditions::getLastCondition() over the closed scopes and `try`: whether the innermost
     * of those around the token is a `try` block.
     */
    private function lastConditionIsTry(int $ptr): bool
    {
        $closed = ['T_CLASS', 'T_ANON_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM', 'T_FUNCTION', 'T_CLOSURE'];
        $innermost = null;
        foreach ($this->tokens as $i => $token) {
            if ($i >= $ptr) {
                break;
            }

            $opener = $token->scopeOpener ?? null;
            if (
                is_int($opener)
                && $opener < $ptr
                && ($token->scopeCloser ?? 0) > $ptr
                && ($token->code === 'T_TRY' || in_array($token->code, $closed, strict: true))
            ) {
                // Later openers are nested deeper.
                $innermost = $token->code;
            }
        }

        return $innermost === 'T_TRY';
    }

    private function checkCodeIsEscaped(int $start, int $end, string $code = 'OutputNotEscaped'): int
    {
        $tokens = $this->tokens;
        // Only skip over a long ternary's condition when the expression is not wrapped in one
        // set of parentheses as a whole.
        $ternary = null;
        $nextNonEmpty = $this->nextNonEmpty($start);
        $lastNonEmpty = $this->previousNonEmpty($end);
        if (
            $nextNonEmpty === null
            || $lastNonEmpty === null
            || $tokens[$nextNonEmpty]->code !== 'T_OPEN_PARENTHESIS'
            || $tokens[$lastNonEmpty]->code !== 'T_CLOSE_PARENTHESIS'
            || $tokens[$nextNonEmpty]->closer !== null && $tokens[$nextNonEmpty]->closer !== $lastNonEmpty
        ) {
            $ternary = $this->findLongTernary($start, $end);
            if ($ternary !== null) {
                $start = $ternary + 1;
            }
        }

        $inCast = false;
        $watch = true;
        for ($i = $start; $i < $end; $i++) {
            if (($tokens[$i] ?? null) === null) {
                break;
            }

            $token = $tokens[$i];
            $tokenCode = $token->code;
            if ((PhpcsTokenStream::EMPTY[$tokenCode] ?? null) !== null) {
                continue;
            }

            if (
                (self::MAGIC_CONSTANTS[$tokenCode] ?? null) !== null
                || $tokenCode === 'T_NS_SEPARATOR'
                || $tokenCode === 'T_DOUBLE_ARROW'
                || $tokenCode === 'T_CLOSE_PARENTHESIS'
            ) {
                continue;
            }

            if ($tokenCode === 'T_OPEN_PARENTHESIS') {
                if ($token->closer === null) {
                    break;
                }

                if ($inCast) {
                    // Skip to the end of a function call cast to a safe value.
                    $i = $token->closer;
                    $inCast = false;
                    continue;
                }

                // WPCS reuses `$ternary` here, so a ternary in parentheses also wakes up on its else.
                $ternary = $this->findLongTernary($i + 1, $token->closer);
                if ($ternary !== null) {
                    $i = $ternary;
                }

                continue;
            }

            // Nested exit/print/throw are examined as such.
            if ((self::TARGET_KEYWORDS[$tokenCode] ?? null) !== null) {
                $returned = $this->processToken($i);
                if ($returned !== null) {
                    $i = $returned;
                }

                continue;
            }

            if ($tokenCode === 'T_MATCH') {
                $matchEnd = $this->walkMatchExpression($i, $code);
                if ($matchEnd === null) {
                    break;
                }

                $i = $matchEnd;
                continue;
            }

            // Examine the items in an array individually.
            if (
                $tokenCode === 'T_ARRAY'
                || $tokenCode === 'T_OPEN_SHORT_ARRAY'
                || $tokenCode === 'T_OPEN_SQUARE_BRACKET'
            ) {
                $closer = $this->arrayCloser($i);
                if ($closer === null) {
                    continue;
                }

                foreach ($this->parameters($i) as $item) {
                    $this->checkCodeIsEscaped($item['start'], $item['end'] + 1, $code);
                }

                $i = $closer;
                continue;
            }

            if (
                $tokenCode === 'T_STRING'
                && (self::SAFE_PHP_CONSTANTS[$token->content] ?? null) !== null
                && $this->isUseOfGlobalConstant($i)
            ) {
                continue;
            }

            // Wake up on concatenation, after a ternary else, and on commas.
            if ($tokenCode === 'T_STRING_CONCAT') {
                $watch = true;
                continue;
            }

            if ($ternary !== null && $tokenCode === 'T_INLINE_ELSE') {
                $watch = true;
                continue;
            }

            if ($tokenCode === 'T_COMMA') {
                $inCast = false;
                $watch = true;
                continue;
            }

            if (!$watch) {
                continue;
            }

            if ((self::SAFE_COMPONENTS[$tokenCode] ?? null) !== null) {
                continue;
            }

            // `*::class`.
            if (
                in_array($tokenCode, ['T_STRING', 'T_VARIABLE', 'T_NAMESPACE'], strict: true)
                || (self::OO_HIERARCHY[$tokenCode] ?? null) !== null
            ) {
                $doubleColon = null;
                for ($j = $i + 1; $j < $end; $j++) {
                    $c = $tokens[$j]->code;
                    if (
                        (PhpcsTokenStream::EMPTY[$c] ?? null) === null
                        && $c !== 'T_STRING'
                        && $c !== 'T_NS_SEPARATOR'
                    ) {
                        $doubleColon = $j;
                        break;
                    }
                }

                if ($doubleColon !== null && $tokens[$doubleColon]->code === 'T_DOUBLE_COLON') {
                    $classKeyword = $this->nextNonEmpty($doubleColon, $end);
                    if ($classKeyword !== null && strtolower($tokens[$classKeyword]->content) === 'class') {
                        $i = $classKeyword;
                        continue;
                    }
                }
            }

            $watch = false;

            if ((self::SAFE_CASTS[$tokenCode] ?? null) !== null) {
                $next = $this->nextNonEmpty($i, $end);
                if ($next !== null && $tokens[$next]->code === 'T_MATCH' && $tokens[$next]->scopeCloser !== null) {
                    $i = $tokens[$next]->scopeCloser;
                    continue;
                }

                $inCast = true;
                continue;
            }

            // Heredocs only need escaping when they interpolate.
            if ($tokenCode === 'T_START_HEREDOC') {
                $current = $i + 1;
                while (($tokens[$current] ?? null) !== null && $tokens[$current]->code === 'T_HEREDOC') {
                    if ($tokens[$current]->embed) {
                        $this->error(
                            'All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found interpolation in unescaped heredoc.',
                            $current,
                            'HeredocOutputNotEscaped',
                        );
                    }

                    $current++;
                }

                $i = $current;
                continue;
            }

            $content = $token->content;
            $ptr = $i;
            if ($tokenCode === 'T_STRING') {
                $functionName = $token->content;
                $opener = $this->nextNonEmpty($i);
                $isFormatting = ($this->formatting[strtolower($functionName)] ?? null) !== null;
                if ($opener !== null && $tokens[$opener]->code === 'T_OPEN_PARENTHESIS') {
                    $mapped = $this->stringCallback($ptr);
                    if ($mapped !== null) {
                        $functionName = self::stripQuotes($tokens[$mapped]->content);
                        $ptr = $mapped;
                    }

                    if ($isFormatting) {
                        foreach ($this->parameters($i) as $formatParam) {
                            $this->checkCodeIsEscaped($formatParam['start'], $formatParam['end'] + 1, $code);
                        }

                        $watch = true;
                    }

                    if ($tokens[$opener]->closer === null) {
                        break;
                    }

                    $i = $tokens[$opener]->closer;
                }

                $lower = strtolower($functionName);
                if (
                    $isFormatting
                    || ($this->escaping[$lower] ?? null) !== null
                    || ($this->autoEscaped[$lower] ?? null) !== null
                ) {
                    // get_search_query() is unsafe when $escaped is false.
                    if ($lower === 'get_search_query') {
                        $escaped = self::parameterFromStack($this->parameters($ptr), 1, 'escaped');
                        if (
                            $escaped !== null
                            && strtolower(ltrim($this->clean($escaped['start'], $escaped['end']), characters: '\\'))
                                !== 'true'
                        ) {
                            $this->error(
                                'Output from get_search_query() is unsafe due to $escaped parameter being set to "false".',
                                $ptr,
                                'UnsafeSearchQuery',
                            );
                        }
                    }

                    continue;
                }

                $content = $functionName;
            }

            if ($tokens[$ptr]->code === 'T_VARIABLE') {
                $keys = $this->arrayAccessKeys($ptr);
                if ($keys !== []) {
                    $content .= '[' . implode('][', $keys) . ']';
                }
            }

            $this->error(sprintf(self::MESSAGE, $content), $ptr, $code);
        }

        return $end;
    }

    /**
     * The callback of array_map()/map_deep() when it is passed as a plain string.
     */
    private function stringCallback(int $ptr): ?int
    {
        $walking = self::ARRAY_WALKING[strtolower($this->tokens[$ptr]->content)] ?? null;
        $callback = $walking === null
            ? null
            : self::parameterFromStack($this->parameters($ptr), $walking[0], $walking[1]);
        if ($callback === null) {
            return null;
        }

        $first = $this->nextNonEmpty($callback['start'] - 1, $callback['end'] + 1);

        return $first !== null && $this->tokens[$first]->code === 'T_CONSTANT_ENCAPSED_STRING' ? $first : null;
    }

    private function findLongTernary(int $start, int $end): ?int
    {
        $tokens = $this->tokens;
        for ($i = $start; $i < $end; $i++) {
            if (($tokens[$i] ?? null) === null) {
                return null;
            }

            $code = $tokens[$i]->code;
            // Skip brackets, parentheses, closures, anonymous classes and the like.
            if ($tokens[$i]->closer !== null && $code !== 'T_ATTRIBUTE') {
                $i = $tokens[$i]->closer;
                continue;
            }

            if ($tokens[$i]->scopeCloser !== null) {
                $i = $tokens[$i]->scopeCloser;
                continue;
            }

            if ($code !== 'T_INLINE_THEN') {
                continue;
            }

            // Operators::isShortTernary().
            $next = $this->nextNonEmpty($i);
            if ($next !== null && $tokens[$next]->code === 'T_INLINE_ELSE') {
                return null;
            }

            return $i;
        }

        return null;
    }

    private function walkMatchExpression(int $ptr, string $code): ?int
    {
        $tokens = $this->tokens;
        $opener = $tokens[$ptr]->scopeOpener ?? null;
        $end = $tokens[$ptr]->scopeCloser ?? null;
        if (!is_int($opener) || $end === null) {
            return null;
        }

        $current = $opener;
        do {
            $arrow = null;
            for ($i = $current + 1; $i < $end; $i++) {
                if ($tokens[$i]->code !== 'T_MATCH_ARROW') {
                    continue;
                }

                $arrow = $i;
                break;
            }

            if ($arrow === null) {
                break;
            }

            $itemStart = $arrow + 1;
            $itemEnd = null;
            for ($i = $itemStart; $i <= $end; $i++) {
                if ($tokens[$i]->closer !== null && $tokens[$i]->code !== 'T_ATTRIBUTE') {
                    $i = $tokens[$i]->closer;
                    continue;
                }

                if ($tokens[$i]->scopeCloser !== null) {
                    $i = $tokens[$i]->scopeCloser;
                    continue;
                }

                if ($tokens[$i]->code !== 'T_COMMA' && $i !== $end) {
                    continue;
                }

                $itemEnd = $i;
                break;
            }

            if ($itemEnd === null) {
                return null;
            }

            $this->checkCodeIsEscaped($itemStart, $itemEnd, $code);
            $current = $itemEnd;
        } while ($current < $end);

        return $end;
    }

    /**
     * Arrays::getOpenClose(): the closer of an array, or NULL for an index bracket.
     */
    private function arrayCloser(int $ptr): ?int
    {
        $tokens = $this->tokens;
        if ($tokens[$ptr]->code === 'T_ARRAY') {
            $open = $this->nextNonEmpty($ptr);

            return $open !== null && $tokens[$open]->code === 'T_OPEN_PARENTHESIS'
                ? $tokens[$open]->closer ?? null
                : null;
        }

        $closer = $tokens[$ptr]->code === 'T_OPEN_SHORT_ARRAY' ? $tokens[$ptr]->closer ?? null : null;
        $next = $closer === null ? null : $this->nextNonEmpty($closer);

        // A short list (`[$a, $b] = ...`) is not an array.
        return $next !== null && $tokens[$next]->code === 'T_EQUAL' ? null : $closer;
    }

    /**
     * PassedParameters::getParameters() for a call, `exit`, `array()` or short array: keyed by
     * 1-based position, or by name for a named argument.
     *
     * @return array<int|string, array{start: int, end: int, name?: string}>
     */
    private function parameters(int $ptr): array
    {
        $tokens = $this->tokens;
        $opener = $tokens[$ptr]->code === 'T_OPEN_SHORT_ARRAY' ? $ptr : $this->nextNonEmpty($ptr);
        if ($opener === null || $opener !== $ptr && $tokens[$opener]->code !== 'T_OPEN_PARENTHESIS') {
            return [];
        }

        $closer = $tokens[$opener]->closer ?? null;
        if ($closer === null) {
            return [];
        }

        $first = $this->nextNonEmpty($opener, $closer);
        if ($first === null) {
            return [];
        }

        $mayHaveNames = $tokens[$ptr]->code !== 'T_OPEN_SHORT_ARRAY' && $tokens[$ptr]->code !== 'T_ARRAY';
        $params = [];
        $paramStart = $opener + 1;
        $position = 1;
        for ($i = $opener + 1; $i <= $closer; $i++) {
            if ($i < $closer && $tokens[$i]->closer !== null && $tokens[$i]->closer > $i) {
                $i = $tokens[$i]->closer;
                continue;
            }

            if ($i < $closer && $tokens[$i]->scopeCloser !== null && $tokens[$i]->scopeCloser > $i) {
                $i = $tokens[$i]->scopeCloser;
                continue;
            }

            if ($i < $closer && $tokens[$i]->code !== 'T_COMMA') {
                continue;
            }

            $paramEnd = $i - 1;
            $key = $position;
            $param = ['start' => $paramStart, 'end' => $paramEnd];
            if ($mayHaveNames) {
                $firstNonEmpty = $this->nextNonEmpty($paramStart - 1, $paramEnd + 1);
                $second = $firstNonEmpty === null ? null : $this->nextNonEmpty($firstNonEmpty, $paramEnd + 1);
                if (
                    $firstNonEmpty !== null
                    && $second !== null
                    && $tokens[$second]->code === 'T_COLON'
                    && preg_match('/^[a-z_\x80-\xff][a-z0-9_\x80-\xff]*$/i', $tokens[$firstNonEmpty]->content) === 1
                ) {
                    $name = $tokens[$firstNonEmpty]->content;
                    $key = ($params[$name] ?? null) !== null ? $position : $name;
                    $param = ['start' => $second + 1, 'end' => $paramEnd, 'name' => $name];
                }
            }

            $params[$key] = $param;
            if ($this->nextNonEmpty($i, $closer) === null) {
                break;
            }

            $paramStart = $i + 1;
            $position++;
        }

        return $params;
    }

    /**
     * @param array<int|string, array{start: int, end: int, name?: string}> $params
     *
     * @return null|array{start: int, end: int, name?: string}
     */
    private static function parameterFromStack(array $params, int $position, string $name): ?array
    {
        if (($params[$name] ?? null) !== null) {
            return $params[$name];
        }

        if (($params[$position] ?? null) !== null && ($params[$position]['name'] ?? null) === null) {
            return $params[$position];
        }

        return null;
    }

    /**
     * ConstantsHelper::is_use_of_global_constant(), for the expression contexts the sniff walks.
     */
    private function isUseOfGlobalConstant(int $ptr): bool
    {
        $tokens = $this->tokens;
        $next = $this->nextNonEmpty($ptr);
        if ($next !== null && in_array($tokens[$next]->code, ['T_OPEN_PARENTHESIS', 'T_DOUBLE_COLON'], strict: true)) {
            return false;
        }

        $prev = $this->previousNonEmpty($ptr);
        $ignore = [
            'T_NAMESPACE',
            'T_USE',
            'T_EXTENDS',
            'T_IMPLEMENTS',
            'T_NEW',
            'T_FUNCTION',
            'T_INSTANCEOF',
            'T_INSTEADOF',
            'T_GOTO',
            'T_AS',
            'T_CLASS',
            'T_ANON_CLASS',
            'T_INTERFACE',
            'T_TRAIT',
            'T_ENUM',
            'T_OBJECT_OPERATOR',
            'T_NULLSAFE_OBJECT_OPERATOR',
            'T_DOUBLE_COLON',
            'T_PRIVATE',
            'T_PUBLIC',
            'T_PROTECTED',
        ];
        if ($prev !== null && in_array($tokens[$prev]->code, $ignore, strict: true)) {
            return false;
        }

        return !$this->isNamespaced($ptr);
    }

    /**
     * VariableHelper::get_array_access_keys(), for the message.
     *
     * @return list<string>
     */
    private function arrayAccessKeys(int $ptr): array
    {
        $keys = [];
        $next = $this->nextNonEmpty($ptr);
        while ($next !== null && $this->tokens[$next]->code === 'T_OPEN_SQUARE_BRACKET') {
            $closer = $this->tokens[$next]->closer;
            if ($closer === null) {
                break;
            }

            $keys[] = $this->clean($next + 1, $closer - 1);
            $next = $this->nextNonEmpty($closer);
        }

        return $keys;
    }

    /**
     * File::findEndOfStatement() from a `print`.
     */
    private function findEndOfStatement(int $start): int
    {
        $tokens = $this->tokens;
        $count = count($tokens);
        $lastNotEmpty = $start;
        for ($i = $start; $i < $count; $i++) {
            $code = $tokens[$i]->code;
            if (
                $i !== $start
                && in_array($code, ['T_COLON', 'T_COMMA', 'T_DOUBLE_ARROW', 'T_SEMICOLON'], strict: true)
            ) {
                return $i;
            }

            if (
                $i !== $start
                && in_array(
                    $code,
                    [
                        'T_CLOSE_PARENTHESIS',
                        'T_CLOSE_SQUARE_BRACKET',
                        'T_CLOSE_CURLY_BRACKET',
                        'T_CLOSE_SHORT_ARRAY',
                        'T_OPEN_TAG',
                        'T_CLOSE_TAG',
                    ],
                    strict: true,
                )
            ) {
                return $lastNotEmpty;
            }

            if ($code === 'T_FN' && $tokens[$i]->scopeCloser !== null) {
                $lastNotEmpty = $tokens[$i]->scopeCloser;
                $i = $tokens[$i]->scopeCloser - 1;
                continue;
            }

            // Skip nested scopes, brackets and parentheses.
            $i = $tokens[$i]->scopeCloser ?? ($code === 'T_ATTRIBUTE' ? $i : $tokens[$i]->closer ?? $i);

            if ((PhpcsTokenStream::EMPTY[$tokens[$i]->code] ?? null) === null) {
                $lastNotEmpty = $i;
            }
        }

        return $count - 1;
    }

    private function error(string $message, int $ptr, string $code): bool
    {
        $context = $this->context;
        if ($context === null) {
            return false;
        }

        $token = $this->tokens[$ptr];
        $length = max(1, strlen(trim($token->content, characters: "\n")));

        return $this->report->issue(
            $context,
            Issue::new($message, new Span($token->pos, $token->pos + $length))->withHelp(
                'Escape the value for its context, e.g. esc_html(), esc_attr(), esc_url() or wp_kses_post().',
            ),
            [self::SNIFF . '.' . $code],
        );
    }

    private function clean(int $start, int $end): string
    {
        $text = '';
        for ($i = $start; $i <= $end; $i++) {
            $code = $this->tokens[$i]->code;
            if ($code !== 'T_COMMENT' && $code !== 'T_DOC_COMMENT') {
                $text .= $this->tokens[$i]->content;
            }
        }

        return trim($text);
    }

    private static function stripQuotes(string $text): string
    {
        return (string) preg_replace(pattern: '/^([\'"])(.*)\1$/s', replacement: '$2', subject: $text);
    }

    private function nextNonEmpty(int $from, ?int $end = null): ?int
    {
        return PhpcsTokenStream::next($this->tokens, $from, $end);
    }

    private function previousNonEmpty(int $from): ?int
    {
        return PhpcsTokenStream::previous($this->tokens, $from);
    }
}
