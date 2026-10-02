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
use Rlorenzo\MagoWordPress\Internal\PhpcsTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function implode;
use function in_array;
use function strlen;

/**
 * Ports `Squiz.Operators.IncrementDecrementUsage`, a token sniff, over the shared phpcs
 * tokens: `$i = $i + 1`, `$i += 1` and `$i -= 1` where `++`/`--` would do (`Found`), and
 * `++`/`--` inside arithmetic (`NotAllowed`) or an unbracketed concatenation (`NoBrackets`).
 * Like the sniff, no fix is offered.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class IncrementDecrementUsageRule implements Rule
{
    private const SNIFF = 'Squiz.Operators.IncrementDecrementUsage';

    private const ARITHMETIC = ['+', '-', '*', '/', '%', 'T_POW'];

    private const STATEMENT_ENDS = [';', ')', ']', '}'];

    /** What may follow the `=` for the assignment check. */
    private const SIMPLE = ['T_LNUMBER', 'T_VARIABLE', '+', '-', '('];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/increment-decrement-usage',
            name: 'Increment/decrement usage',
            description: 'Reports `$i = $i + 1` and `$i += 1` where `++$i` would do, and `++`/`--` inside arithmetic or an unbracketed concatenation.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The whole file, before NonceVerificationRule releases the shared PhpcsTokens.
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = PhpcsTokens::of($context->file);
        foreach ($tokens->codes as $index => $code) {
            match ($code) {
                'T_INC', 'T_DEC' => $this->incDec($context, $tokens, $index),
                '=', 'T_PLUS_EQUAL', 'T_MINUS_EQUAL' => $this->assignment($context, $tokens, $index),
                default => null,
            };
        }
    }

    private function incDec(LintContext $context, PhpcsTokens $tokens, int $index): void
    {
        // phpcs looks one raw token past the operator (or the variable after it) for what
        // follows; whitespace counts as a token there.
        $before = $tokens->gapBefore($index) ? null : $tokens->code($index - 1);
        $post =
            $before === 'T_VARIABLE'
            || $before === 'T_STRING'
            && !$tokens->gapBefore($index - 1)
            && in_array($tokens->code($index - 2), ['T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR'], strict: true);
        $next = $post || $tokens->gapBefore($index + 1) ? $index + 1 : $index + 2;
        if (in_array($tokens->code($next), self::ARITHMETIC, strict: true)) {
            $this->report(
                $context,
                $tokens,
                $index,
                'NotAllowed',
                'Increment and decrement operators cannot be used in an arithmetic operation',
            );

            return;
        }

        $previous = $post ? $index - 2 : $index - 1;
        if ($tokens->code($next) === '.' || $tokens->code($previous) === '.') {
            $this->report(
                $context,
                $tokens,
                $index,
                'NoBrackets',
                'Increment and decrement operators must be bracketed when used in string concatenation',
            );
        }
    }

    /** @mago-expect lint:halstead */
    private function assignment(LintContext $context, PhpcsTokens $tokens, int $index): void
    {
        $assigned = $index - 1;
        if ($tokens->code($assigned) !== 'T_VARIABLE') {
            return;
        }

        $end = $index + 1;
        while ($tokens->code($end) !== null && !in_array($tokens->code($end), self::STATEMENT_ENDS, strict: true)) {
            if (!in_array($tokens->code($end), self::SIMPLE, strict: true)) {
                return;
            }

            $end++;
        }

        $variables = [];
        $numbers = [];
        for ($i = $index + 1; $i < $end; $i++) {
            if ($tokens->code($i) === 'T_VARIABLE') {
                $variables[] = $i;
            } elseif ($tokens->code($i) === 'T_LNUMBER') {
                $numbers[] = $i;
            }
        }

        $isEqual = $tokens->code($index) === '=';
        if (
            count($numbers) !== 1
            || $tokens->content($numbers[0]) !== '1'
            || (
                $isEqual
                    ? count($variables) !== 1 || $tokens->content($variables[0]) !== $tokens->content($assigned)
                    : $variables !== []
            )
        ) {
            return;
        }

        $operator = $isEqual ? self::firstSign($tokens, $variables[0] + 1, $end) : $tokens->content($index)[0];
        if ($operator === null) {
            return; // `$i = 1 + $i`
        }

        if (!$isEqual && self::hasMinus($tokens, $index + 1, $numbers[0])) {
            $operator = $operator === '+' ? '-' : '+';
        }

        $found = [];
        for ($i = $assigned; $i <= $end && $tokens->code($i) !== null; $i++) {
            $found[] = $tokens->content($i);
        }

        $expected = $operator . $operator . $tokens->content($assigned);
        $this->report(
            $context,
            $tokens,
            $index,
            'Found',
            ($operator === '+' ? 'Increment' : 'Decrement')
            . ' operators should be used where possible; found "'
            . implode(' ', $found)
            . "\" but expected \"{$expected}\"",
        );
    }

    /** The first `+` or `-` in [$from, $to). */
    private static function firstSign(PhpcsTokens $tokens, int $from, int $to): ?string
    {
        for ($i = $from; $i < $to; $i++) {
            if (in_array($tokens->code($i), ['+', '-'], strict: true)) {
                return $tokens->code($i);
            }
        }

        return null;
    }

    private static function hasMinus(PhpcsTokens $tokens, int $from, int $to): bool
    {
        for ($i = $from; $i < $to; $i++) {
            if ($tokens->code($i) === '-') {
                return true;
            }
        }

        return false;
    }

    private function report(LintContext $context, PhpcsTokens $tokens, int $index, string $code, string $message): void
    {
        $start = $tokens->pos($index);
        $this->report->issue(
            $context,
            Issue::new($message, new Span($start, $start + strlen($tokens->content($index))), 'operator')->withHelp(
                'Use `++`/`--` on its own, or bracket it.',
            ),
            [self::SNIFF . '.' . $code],
        );
    }
}
