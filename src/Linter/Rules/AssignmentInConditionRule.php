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
use Rlorenzo\MagoWordPress\Internal\FileCache;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function in_array;
use function strlen;

/**
 * Ports `Generic.CodeAnalysis.AssignmentInCondition`: an assignment to a variable, array element
 * or property inside the condition of `if`, `elseif`, `switch`, `case`, `while`, `match` or the
 * middle part of `for`. Like the sniff it walks tokens: each assignment operator whose left side,
 * back to the nearest `(`, `;` or boolean operator, ends in a variable or `]` is reported, so
 * `! $a = f()` and `$b && ( $a = f() )` count, and a call such as `f() = 1` does not.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class AssignmentInConditionRule implements Rule
{
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
        'T_SL_EQUAL' => true,
        'T_SR_EQUAL' => true,
        'T_COALESCE_EQUAL' => true,
    ];

    /** Where the left side of an assignment starts: boolean operators, `;` and `(`. */
    private const CONDITION_STARTS = [
        'T_BOOLEAN_AND' => true,
        'T_BOOLEAN_OR' => true,
        'T_LOGICAL_AND' => true,
        'T_LOGICAL_OR' => true,
        'T_LOGICAL_XOR' => true,
        'T_SEMICOLON' => true,
        'T_OPEN_PARENTHESIS' => true,
    ];

    private const OWNERS = ['T_IF', 'T_ELSEIF', 'T_FOR', 'T_SWITCH', 'T_CASE', 'T_WHILE', 'T_MATCH'];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/assignment-in-condition',
            name: 'Assignment in condition',
            description: 'Reports a variable assignment inside the condition of an if, elseif, switch, case, while, match or for, which is often a mistyped comparison.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = self::tokens($context);
        foreach ($tokens as $index => $token) {
            if (in_array($token->code, self::OWNERS, strict: true)) {
                $this->check($context, $tokens, $index);
            }
        }
    }

    /**
     * The file's phpcs-style tokens, shared with generic/disallow-multiple-assignments.
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

    /**
     * @param list<PhpcsToken> $tokens
     */
    private function check(LintContext $context, array $tokens, int $owner): void
    {
        $range = self::condition($tokens, $owner);
        if ($range === null) {
            return;
        }

        // Like the sniff, each assignment's left side is looked for after the previous one.
        [$start, $closer] = $range;
        for ($at = $start + 1; $at < $closer; $at++) {
            if ((self::ASSIGNMENTS[$tokens[$at]->code] ?? null) === null) {
                continue;
            }

            $previous = $start;
            $start = $at;
            if (!self::assignsVariable($tokens, $previous, $at)) {
                continue;
            }

            $token = $tokens[$at];
            $this->report->issue(
                $context,
                Issue::new(
                    'Variable assignment found within a condition. Did you mean to do a comparison?',
                    new Span($token->pos, $token->pos + strlen($token->content)),
                    'assignment',
                )->withHelp('Assign before the condition, or compare with `===` if a comparison was meant.'),
                [
                    'Generic.CodeAnalysis.AssignmentInCondition.'
                        . ($tokens[$owner]->code === 'T_WHILE' ? 'FoundInWhileCondition' : 'Found'),
                ],
            );
        }
    }

    /**
     * The tokens before and after the condition: the parentheses, the `for` loop's two
     * semicolons, or `case` and its `:`.
     *
     * @param list<PhpcsToken> $tokens
     * @return array{int, int}|null
     */
    private static function condition(array $tokens, int $owner): ?array
    {
        if ($tokens[$owner]->code === 'T_CASE') {
            for ($i = $owner + 1, $count = count($tokens); $i < $count; $i++) {
                $code = $tokens[$i]->code;
                if ($code === 'T_COLON' || $code === 'T_SEMICOLON') {
                    return [$owner, $i];
                }

                if ($tokens[$i]->closer !== null) {
                    $i = $tokens[$i]->closer;
                }
            }

            return null;
        }

        $open = PhpcsTokenStream::next($tokens, $owner);
        $close = $open === null || $tokens[$open]->code !== 'T_OPEN_PARENTHESIS' ? null : $tokens[$open]->closer;
        if ($open === null || $close === null) {
            return null;
        }

        if ($tokens[$owner]->code !== 'T_FOR') {
            return [$open, $close];
        }

        $semicolons = [];
        for ($i = $open + 1; $i < $close && count($semicolons) < 2; $i++) {
            if ($tokens[$i]->code === 'T_SEMICOLON') {
                $semicolons[] = $i;
            }
        }

        return count($semicolons) === 2 ? [$semicolons[0], $semicolons[1]] : null;
    }

    /**
     * Whether the left side of the assignment at $at ends in a variable or `]`, looking back
     * to the nearest condition start, or to $start (the condition's start or the previous
     * assignment).
     *
     * @param list<PhpcsToken> $tokens
     */
    private static function assignsVariable(array $tokens, int $start, int $at): bool
    {
        $from = $start;
        for ($i = $at - 1; $i >= $start; $i--) {
            if ((self::CONDITION_STARTS[$tokens[$i]->code] ?? null) !== null) {
                $from = $i;
                break;
            }
        }

        for ($i = $at - 1; $i > $from; $i--) {
            $code = $tokens[$i]->code;
            if ($code === 'T_VARIABLE' || $code === 'T_CLOSE_SQUARE_BRACKET') {
                return true;
            }

            if ($code === 'T_CLOSE_PARENTHESIS') {
                return false;
            }
        }

        return false;
    }
}
