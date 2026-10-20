<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;
use Rlorenzo\MagoWordPress\Internal\Spacing;

use function array_pop;
use function count;
use function in_array;
use function sprintf;
use function strlen;

/**
 * Ports `WordPress.WhiteSpace.OperatorSpacing`, which is `Squiz.WhiteSpace.OperatorSpacing`
 * with `ignoreNewlines` on plus `!` and the boolean and logical operators: one space on each
 * side of arithmetic, bitwise, comparison, assignment (only after it), ternary, `instanceof` and
 * `=>` operators, and `&` when it is not a reference; a line break counts as fine. Unary `+`
 * and `-`, default values (`$a = 1` in a parameter list) and `declare()` are skipped, as are
 * the `|` and `&` of union and intersection types. Autofix, so `mago-wordpress format` runs it
 * for the `|` of a multi-catch that `mago format` writes as `A|B`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
final class OperatorSpacingRule implements Rule
{
    private const SNIFF = 'WordPress.WhiteSpace.OperatorSpacing';

    private const ASSIGNMENTS = [
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

    private const COMPARISONS = [
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
    ];

    private const OPERATORS = [
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
    ];

    private const BOOLEANS = [
        'T_BOOLEAN_AND' => true,
        'T_BOOLEAN_OR' => true,
        'T_LOGICAL_AND' => true,
        'T_LOGICAL_OR' => true,
        'T_LOGICAL_XOR' => true,
    ];

    private const OTHER = [
        'T_INLINE_THEN' => true,
        'T_INLINE_ELSE' => true,
        'T_INSTANCEOF' => true,
        'T_BOOLEAN_NOT' => true,
    ];

    /** Tokens after which `+` and `-` are a sign, not an operator. */
    private const SIGN_AFTER = [
        'T_RETURN',
        'T_ECHO',
        'T_EXIT',
        'T_PRINT',
        'T_YIELD',
        'T_FN_ARROW',
        'T_MATCH_ARROW',
        'T_CASE',
        'T_COLON',
        'T_COMMA',
        'T_INLINE_ELSE',
        'T_INLINE_THEN',
        'T_OPEN_CURLY_BRACKET',
        'T_OPEN_PARENTHESIS',
        'T_OPEN_SHORT_ARRAY',
        'T_OPEN_SQUARE_BRACKET',
        'T_STRING_CONCAT',
    ];

    /** The parts of a type declaration, which a `|` or `&` joins. */
    private const TYPE_PARTS = [
        'T_STRING',
        'T_NS_SEPARATOR',
        'T_BITWISE_OR',
        'T_BITWISE_AND',
        'T_NULLABLE',
        'T_ARRAY',
        'T_CALLABLE',
        'T_STATIC',
        'T_SELF',
        'T_PARENT',
        'T_NULL',
        'T_FALSE',
        'T_TRUE',
    ];

    /** What can precede a type declaration besides `(` and `,`. */
    private const TYPE_STARTS = [
        'T_PUBLIC',
        'T_PROTECTED',
        'T_PRIVATE',
        'T_READONLY',
        'T_VAR',
        'T_CONST',
        'T_FINAL',
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/operator-spacing',
            name: 'Operator spacing',
            description: 'Requires one space on each side of operators (arithmetic, comparison, assignment, ternary, logical, `!`, `=>`), a line break being fine.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $t = Spacing::tokens($context);
        $source = $context->file->contents;
        /** @var list<int> $parens */
        $parens = [];
        $arrows = [];
        $skipTo = -1;
        foreach ($t as $i => $token) {
            $code = $token->code;
            if ($code === 'T_OPEN_PARENTHESIS' && $token->closer !== null) {
                $parens[] = $i;
            } elseif ($code === 'T_CLOSE_PARENTHESIS' && $token->opener !== null) {
                array_pop($parens);
            } elseif ($code === 'T_FN') {
                $arrow = $this->arrowOf($t, $i);
                if ($arrow !== null) {
                    $arrows[$arrow] = true;
                }
            } elseif ($code === 'T_DECLARE') {
                $open = PhpcsTokenStream::next($t, $i);
                $skipTo = $open === null ? count($t) : $t[$open]->closer ?? count($t);
            }

            if ($i <= $skipTo || !$this->isTarget($code) || isset($arrows[$i])) {
                continue;
            }

            if ($this->isOperator($t, $i, $parens)) {
                $this->check($context, $source, $t, $i);
            }
        }
    }

    private function isTarget(string $code): bool
    {
        return (
            isset(self::OPERATORS[$code])
            || isset(self::ASSIGNMENTS[$code])
            || isset(self::COMPARISONS[$code])
            || isset(self::BOOLEANS[$code])
            || isset(self::OTHER[$code])
        );
    }

    /**
     * The `=>` of the arrow function that starts at $fn.
     *
     * @param list<PhpcsToken> $t
     */
    private function arrowOf(array $t, int $fn): ?int
    {
        for ($k = $fn + 1, $count = count($t); $k < $count; $k++) {
            if ($t[$k]->code === 'T_DOUBLE_ARROW') {
                return $k;
            }

            if ($t[$k]->closer !== null) {
                $k = $t[$k]->closer;
            } elseif (in_array($t[$k]->code, ['T_SEMICOLON', 'T_OPEN_CURLY_BRACKET'], strict: true)) {
                return null;
            }
        }

        return null;
    }

    /**
     * `isOperator()` of the Squiz sniff, plus the `|` and `&` of type declarations phpcs tokenizes apart.
     *
     * @param list<PhpcsToken> $t
     * @param list<int> $parens
     */
    private function isOperator(array $t, int $i, array $parens): bool
    {
        $code = $t[$i]->code;
        $open = $parens === [] ? null : $parens[count($parens) - 1];
        if ($code === 'T_EQUAL') {
            if ($open !== null && Spacing::isFunctionParenthesis($t, $open)) {
                return false;
            }

            if (($t[$i + 1]->code ?? '') === 'T_BITWISE_AND') {
                return false;
            }
        }

        if ($code === 'T_BITWISE_OR' || $code === 'T_BITWISE_AND') {
            if ($this->isTypeSeparator($t, $i, $open)) {
                return false;
            }

            if ($code === 'T_BITWISE_AND' && $this->isReference($t, $i, $open)) {
                return false;
            }
        }

        if ($code === 'T_MINUS' || $code === 'T_PLUS') {
            $prev = PhpcsTokenStream::previous($t, $i);
            if ($prev !== null && $this->isSignContext($t[$prev]->code)) {
                return false;
            }
        }

        return true;
    }

    private function isSignContext(string $code): bool
    {
        return (
            isset(self::OPERATORS[$code])
            || isset(self::COMPARISONS[$code])
            || isset(self::BOOLEANS[$code])
            || isset(self::ASSIGNMENTS[$code])
            || isset(Spacing::CASTS[$code])
            || in_array($code, self::SIGN_AFTER, strict: true)
        );
    }

    /**
     * Whether a `|` or `&` joins the types of a declaration (a parameter, return, property or
     * constant type), which phpcs tokenizes as `T_TYPE_UNION` and `T_TYPE_INTERSECTION`.
     *
     * @param list<PhpcsToken> $t
     */
    private function isTypeSeparator(array $t, int $i, ?int $open): bool
    {
        $before = PhpcsTokenStream::previous($t, $i);
        if ($before === null || !in_array($t[$before]->code, self::TYPE_PARTS, strict: true)) {
            return false;
        }

        $k = $before;
        while ($k >= 0 && (Spacing::isEmpty($t[$k]) || in_array($t[$k]->code, self::TYPE_PARTS, strict: true))) {
            $k--;
        }

        if ($k < 0) {
            return false;
        }

        $start = $t[$k]->code;
        if (in_array($start, self::TYPE_STARTS, strict: true)) {
            return true;
        }

        if ($start === 'T_COLON') {
            $prev = PhpcsTokenStream::previous($t, $k);

            return $prev !== null && $t[$prev]->code === 'T_CLOSE_PARENTHESIS';
        }

        return ($start === 'T_OPEN_PARENTHESIS' || $start === 'T_COMMA')
        && $open !== null
        && Spacing::isFunctionParenthesis($t, $open);
    }

    /**
     * `File::isReference()`.
     *
     * @param list<PhpcsToken> $t
     */
    private function isReference(array $t, int $i, ?int $open): bool
    {
        $before = PhpcsTokenStream::previous($t, $i);
        if ($before === null) {
            return false;
        }

        $beforeCode = $t[$before]->code;
        if (
            in_array($beforeCode, ['T_FUNCTION', 'T_CLOSURE', 'T_FN', 'T_DOUBLE_ARROW', 'T_AS'], strict: true)
            || isset(self::ASSIGNMENTS[$beforeCode])
        ) {
            return true;
        }

        $after = PhpcsTokenStream::next($t, $i);
        if ($after !== null && $t[$after]->code === 'T_NEW') {
            return true;
        }

        if ($open !== null) {
            if (Spacing::isFunctionParenthesis($t, $open)) {
                $next = $after;
                if ($next !== null && $t[$next]->code === 'T_ELLIPSIS') {
                    $next = PhpcsTokenStream::next($t, $next);
                }

                if ($next !== null && $t[$next]->code === 'T_VARIABLE') {
                    return true;
                }
            } else {
                $owner = PhpcsTokenStream::previous($t, $open);
                if ($owner !== null && $t[$owner]->code === 'T_USE') {
                    return true;
                }
            }
        }

        if (!in_array($beforeCode, ['T_OPEN_PARENTHESIS', 'T_COMMA', 'T_OPEN_SHORT_ARRAY'], strict: true)) {
            return false;
        }

        $skip = [
            'T_NS_SEPARATOR',
            'T_SELF',
            'T_PARENT',
            'T_STATIC',
            'T_STRING',
            'T_NAMESPACE',
            'T_DOUBLE_COLON',
        ];
        $k = $i + 1;
        while (isset($t[$k]) && (Spacing::isEmpty($t[$k]) || in_array($t[$k]->code, $skip, strict: true))) {
            ++$k;
        }

        return ($t[$k]->code ?? '') === 'T_VARIABLE';
    }

    /**
     * @param list<PhpcsToken> $t
     */
    private function check(LintContext $context, string $source, array $t, int $i): void
    {
        $token = $t[$i];
        $operator = $token->text;
        $isAmp = $token->code === 'T_BITWISE_AND';
        $quoted = '"' . $operator . '"';

        $this->checkBefore($context, $source, $t, $i, $isAmp, $quoted);

        $hasNext = $i + 1;
        while (Spacing::isWs($t, $hasNext)) {
            ++$hasNext;
        }

        if (!isset($t[$hasNext])) {
            return;
        }

        $end = $token->pos + strlen($operator);
        $next = $t[$i + 1];
        if ($next->code !== 'T_WHITESPACE') {
            if ($token->code === 'T_INLINE_THEN' && $next->code === 'T_INLINE_ELSE') {
                return;
            }

            $this->add(
                $context,
                $token,
                $isAmp
                    ? 'Expected 1 space after "&" operator; 0 found'
                    : sprintf('Expected 1 space after %s; 0 found', $quoted),
                $isAmp ? 'NoSpaceAfterAmp' : 'NoSpaceAfter',
                [TextEdit::insert($end, ' ')],
            );

            return;
        }

        $found = isset($t[$i + 2]) && $t[$i + 2]->line !== $token->line ? 'newline' : Spacing::width($source, $next);
        if ($found === 1 || $found === 'newline') {
            return;
        }

        $this->add(
            $context,
            $token,
            $isAmp
                ? sprintf('Expected 1 space after "&" operator; %s found', $found)
                : sprintf('Expected 1 space after %s; %s found', $quoted, $found),
            $isAmp ? 'SpacingAfterAmp' : 'SpacingAfter',
            [Spacing::replace($next, ' ')],
        );
    }

    /**
     * @param list<PhpcsToken> $t
     *
     * @mago-expect lint:excessive-parameter-list
     */
    private function checkBefore(
        LintContext $context,
        string $source,
        array $t,
        int $i,
        bool $isAmp,
        string $quoted,
    ): void {
        $token = $t[$i];
        $ws = Spacing::wsBefore($t, $i);
        $prev = $t[$i - 1] ?? null;
        if ($ws === null) {
            if ($token->code === 'T_INLINE_ELSE' && $prev !== null && $prev->code === 'T_INLINE_THEN') {
                return;
            }

            $this->add(
                $context,
                $token,
                $isAmp
                    ? 'Expected 1 space before "&" operator; 0 found'
                    : sprintf('Expected 1 space before %s; 0 found', $quoted),
                $isAmp ? 'NoSpaceBeforeAmp' : 'NoSpaceBefore',
                [TextEdit::insert($token->pos, ' ')],
            );

            return;
        }

        // Alignment of assignments is left alone.
        if (!$isAmp && isset(self::ASSIGNMENTS[$token->code])) {
            return;
        }

        $k = $i - 1;
        while ($k > 0 && Spacing::isWs($t, $k)) {
            --$k;
        }

        $found = Spacing::endLine($t[$k]) !== $token->line || Spacing::hasNewline($ws)
            ? 'newline'
            : Spacing::width($source, $ws);
        if ($found === 1 || $found === 'newline') {
            return;
        }

        $comment = $t[$k]->code === 'T_COMMENT' || $t[$k]->code === 'T_DOC_COMMENT';
        $this->add(
            $context,
            $token,
            $isAmp
                ? sprintf('Expected 1 space before "&" operator; %s found', $found)
                : sprintf('Expected 1 space before %s; %s found', $quoted, $found),
            $isAmp ? 'SpacingBeforeAmp' : 'SpacingBefore',
            $comment && !$isAmp ? [] : [Spacing::replace($ws, ' ')],
        );
    }

    /**
     * @param list<TextEdit> $edits
     */
    private function add(LintContext $context, PhpcsToken $at, string $message, string $code, array $edits): void
    {
        Spacing::report($this->report, $context, $at, $message, [self::SNIFF . '.' . $code], $edits);
    }
}
