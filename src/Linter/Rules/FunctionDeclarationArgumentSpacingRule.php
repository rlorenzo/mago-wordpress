<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Rlorenzo\MagoWordPress\Internal\FunctionTokens;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_slice;
use function in_array;
use function strlen;
use function strpos;

/**
 * Ports `Squiz.Functions.FunctionDeclarationArgumentSpacing` with the values WordPress-Core sets
 * (`equalsSpacing` 1, one space inside the parentheses) for functions, methods, closures (and their
 * `use` list) and arrow functions: spaces inside the parentheses of a single-line list, around the
 * `=` of a default, after `&`, a type, a visibility or `readonly` modifier, none before a comma and
 * one after it (unless it ends the line). `SpacingAfterVariadic` is off in WordPress-Core.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionDeclarationArgumentSpacingRule implements Rule
{
    private const SNIFF = 'Squiz.Functions.FunctionDeclarationArgumentSpacing';

    private const VISIBILITY = ['T_PUBLIC' => true, 'T_PROTECTED' => true, 'T_PRIVATE' => true];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/function-declaration-argument-spacing',
            name: 'Function declaration argument spacing',
            description: 'Checks the spacing inside a function declaration: one space inside the parentheses, around the `=` of a default, after a type or modifier, and around commas.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        foreach ($tokens as $index => $token) {
            if (!in_array($token->code, ['T_FUNCTION', 'T_CLOSURE', 'T_FN'], strict: true)) {
                continue;
            }

            $parentheses = FunctionTokens::parentheses($tokens, $index);
            if ($parentheses === null) {
                continue;
            }

            $this->bracket($context, $tokens, $parentheses[0]);
            if ($token->code === 'T_CLOSURE') {
                $use = FunctionTokens::useClause($tokens, $parentheses[1], $token->scopeOpener);
                if ($use !== null) {
                    $this->bracket($context, $tokens, $use[1]);
                }
            }
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function bracket(LintContext $context, array $tokens, int $open): void
    {
        $close = $tokens[$open]->closer;
        if ($close === null) {
            return;
        }

        $source = $context->file->contents;
        $multiLine = $tokens[$open]->line !== $tokens[$close]->line;
        $params = self::parameters($tokens, $open, $close);
        if ($params === [] && FunctionTokens::nextNonWhitespace($tokens, $open, $close) === null) {
            if (($close - $open) !== 1) {
                $found = $multiLine ? 'newline' : (string) FunctionTokens::width($tokens[$open + 1], $source);
                $this->emit(
                    $context,
                    $tokens[$open]->pos,
                    'SpacingBetween',
                    "Expected 0 spaces between parenthesis of function declaration; {$found} found",
                    [self::remove($tokens[$open + 1])],
                );
            }

            return;
        }

        foreach ($params as $number => $param) {
            $this->parameter($context, $tokens, $param);
            if ($number > 0 && $params[$number - 1]['comma'] !== null) {
                $this->comma($context, $tokens, $params[$number - 1], $param, $close, $multiLine);
            }
        }

        if ($multiLine) {
            return;
        }

        $before = $tokens[$close - 1];
        $gap = $before->code === 'T_WHITESPACE' ? FunctionTokens::width($before, $source) : 0;
        if ($gap !== 1) {
            $this->emit(
                $context,
                $tokens[$close]->pos,
                'SpacingBeforeClose',
                "Expected 1 spaces before closing parenthesis; {$gap} found",
                [self::pad($before, $tokens[$close]->pos)],
            );
        }

        $after = $tokens[$open + 1];
        $gap = $after->code === 'T_WHITESPACE' ? FunctionTokens::width($after, $source) : 0;
        if ($gap !== 1) {
            $this->emit(
                $context,
                $tokens[$open]->pos,
                'SpacingAfterOpen',
                "Expected 1 spaces after opening parenthesis; {$gap} found",
                [self::pad($after, $tokens[$open]->pos + 1)],
            );
        }
    }

    /**
     * The parameters of a list as `File::getMethodParameters()` sees them. Tokens inside an attribute,
     * a nested bracket or a default value that has parentheses are not read.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<array{variable: int, reference: ?int, variadic: ?int, equal: ?int, default: ?int, hintEnd: ?int, modifiers: list<int>, comma: ?int, promoted: bool, hint: bool}>
     */
    private static function parameters(array $tokens, int $open, int $close): array
    {
        $params = [];
        $items = [];
        for ($i = $open + 1; $i <= $close; $i++) {
            $code = $tokens[$i]->code;
            if ($i < $close && $tokens[$i]->closer !== null) {
                if ($code !== 'T_ATTRIBUTE') {
                    $items[] = $i;
                }

                $i = $tokens[$i]->closer;
                continue;
            }

            if ($i < $close && $code !== 'T_COMMA') {
                if (!isset(PhpcsTokenStream::EMPTY[$code])) {
                    $items[] = $i;
                }

                continue;
            }

            $param = self::describe($tokens, $items, $i < $close ? $i : null);
            if ($param !== null) {
                $params[] = $param;
            }

            $items = [];
        }

        return $params;
    }

    /**
     * @param list<PhpcsToken> $tokens
     * @param list<int> $items the parameter's tokens that are not whitespace or comments
     *
     * @return null|array{variable: int, reference: ?int, variadic: ?int, equal: ?int, default: ?int, hintEnd: ?int, modifiers: list<int>, comma: ?int, promoted: bool, hint: bool}
     */
    private static function describe(array $tokens, array $items, ?int $comma): ?array
    {
        $variable = null;
        foreach ($items as $position => $item) {
            if ($tokens[$item]->code === 'T_VARIABLE') {
                $variable = $position;
                break;
            }
        }

        if ($variable === null) {
            return null;
        }

        $param = [
            'variable' => $items[$variable],
            'reference' => null,
            'variadic' => null,
            'equal' => null,
            'default' => null,
            'hintEnd' => null,
            'modifiers' => [],
            'comma' => $comma,
            'promoted' => false,
            'hint' => false,
        ];
        foreach (array_slice($items, 0, $variable) as $item) {
            $code = $tokens[$item]->code;
            if ($code === 'T_BITWISE_AND') {
                $param['reference'] = $item;
            } elseif ($code === 'T_ELLIPSIS') {
                $param['variadic'] = $item;
            } elseif (isset(self::VISIBILITY[$code]) || $code === 'T_READONLY') {
                $param['modifiers'][] = $item;
                $param['promoted'] = true;
            } else {
                $param['hint'] = true;
                $param['hintEnd'] = $item;
            }
        }

        $next = $items[$variable + 1] ?? null;
        if ($next !== null && $tokens[$next]->code === 'T_EQUAL') {
            $param['equal'] = $next;
            $param['default'] = $items[$variable + 2] ?? null;
        }

        return $param;
    }

    /**
     * @param list<PhpcsToken> $tokens
     * @param array{variable: int, reference: ?int, variadic: ?int, equal: ?int, default: ?int, hintEnd: ?int, modifiers: list<int>, comma: ?int, promoted: bool, hint: bool} $param
     */
    private function parameter(LintContext $context, array $tokens, array $param): void
    {
        $source = $context->file->contents;
        $variable = $param['variable'];
        $name = $tokens[$variable]->text;
        $reference = $param['reference'];
        if ($reference !== null && $tokens[$reference + 1]->code === 'T_WHITESPACE') {
            $gap = $tokens[$reference]->line !== $tokens[$reference + 2]->line
                ? 'newline'
                : (string) FunctionTokens::width($tokens[$reference + 1], $source);
            $this->emit(
                $context,
                $tokens[$reference]->pos,
                'SpacingAfterReference',
                "Expected 0 spaces after reference operator for argument \"{$name}\"; {$gap} found",
                [self::remove($tokens[$reference + 1])],
            );
        }

        $equal = $param['equal'];
        if ($equal !== null) {
            $this->aroundEquals($context, $tokens, $variable, $equal, $param['default'] ?? ($equal + 1));
        }

        $end = $param['hintEnd'];
        if ($end !== null) {
            $after = $tokens[$end + 1];
            if ($after->text !== ' ' || $after->code !== 'T_WHITESPACE') {
                $found = $after->code === 'T_WHITESPACE' ? strlen($after->text) : 0;
                $this->emit(
                    $context,
                    $tokens[$end]->pos,
                    'SpacingAfterHint',
                    "Expected 1 space between type hint and argument \"{$name}\"; {$found} found",
                    [self::pad($after, $tokens[$end]->pos + strlen($tokens[$end]->text))],
                );
            }
        }

        foreach ($param['modifiers'] as $modifier) {
            $next = FunctionTokens::nextNonWhitespace($tokens, $modifier, $variable);
            $after = $tokens[$modifier + 1];
            $spaces =
                $next !== null && $tokens[$next]->line !== $tokens[$modifier]->line
                    ? -1
                    : ($after->code === 'T_WHITESPACE' ? FunctionTokens::width($after, $source) : 0);
            if ($spaces === 1) {
                continue;
            }

            $this->emit(
                $context,
                $tokens[$modifier]->pos,
                $tokens[$modifier]->code === 'T_READONLY' ? 'SpacingAfterReadonly' : 'SpacingAfterVisbility',
                "Expected 1 space after {$tokens[$modifier]->text} modifier; "
                . ($spaces === -1 ? 'newline' : $spaces)
                . ' found',
                [self::pad($after, $tokens[$modifier]->pos + strlen($tokens[$modifier]->text))],
            );
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private function aroundEquals(LintContext $context, array $tokens, int $variable, int $equal, int $default): void
    {
        $source = $context->file->contents;
        $before = $tokens[$variable + 1];
        $spaces = $tokens[$variable]->line !== $tokens[$equal]->line
            ? -1
            : ($before->code === 'T_WHITESPACE' ? FunctionTokens::width($before, $source) : 0);
        if ($spaces !== 1) {
            $this->emit(
                $context,
                $tokens[$equal]->pos,
                'SpaceBeforeEquals',
                "Incorrect spacing between argument \"{$tokens[$variable]->text}\" and equals sign; expected 1 but found "
                . ($spaces === -1 ? 'newline' : $spaces),
                FunctionTokens::nextNonWhitespace($tokens, $variable, $equal) !== null
                    ? []
                    : [self::pad($before, $tokens[$variable]->pos + strlen($tokens[$variable]->text))],
            );
        }

        $after = $tokens[$equal + 1];
        $spaces = $tokens[$equal]->line !== $tokens[$default]->line
            ? -1
            : ($after->code === 'T_WHITESPACE' ? FunctionTokens::width($after, $source) : 0);
        if ($spaces !== 1) {
            $this->emit(
                $context,
                $tokens[$equal]->pos,
                'SpaceAfterEquals',
                "Incorrect spacing between default value and equals sign for argument \"{$tokens[$variable]->text}\"; expected 1 but found "
                . ($spaces === -1 ? 'newline' : $spaces),
                FunctionTokens::nextNonWhitespace($tokens, $equal, $default) !== null
                    ? []
                    : [self::pad($after, $tokens[$equal]->pos + 1)],
            );
        }
    }

    /**
     * The comma after $previous: none before it, one space after it unless it ends the line.
     *
     * @param list<PhpcsToken> $tokens
     * @param array{variable: int, reference: ?int, variadic: ?int, equal: ?int, default: ?int, hintEnd: ?int, modifiers: list<int>, comma: ?int, promoted: bool, hint: bool} $previous
     * @param array{variable: int, reference: ?int, variadic: ?int, equal: ?int, default: ?int, hintEnd: ?int, modifiers: list<int>, comma: ?int, promoted: bool, hint: bool} $param
     * @mago-expect lint:excessive-parameter-list
     */
    private function comma(
        LintContext $context,
        array $tokens,
        array $previous,
        array $param,
        int $close,
        bool $multiLine,
    ): void {
        $source = $context->file->contents;
        $comma = (int) $previous['comma'];
        $prev = PhpcsTokenStream::previous($tokens, $comma);
        $before = $tokens[$comma - 1];
        if ($prev !== null) {
            $spaces = $tokens[$prev]->line !== $tokens[$comma]->line
                ? -1
                : ($before->code === 'T_WHITESPACE' ? FunctionTokens::width($before, $source) : 0);
            if ($spaces !== 0) {
                $this->emit(
                    $context,
                    $tokens[$comma]->pos,
                    'SpaceBeforeComma',
                    "Expected 0 spaces between argument \"{$tokens[$previous['variable']]->text}\" and comma; "
                    . ($spaces === -1 ? 'newline' : $spaces)
                    . ' found',
                    $this->moveComma($tokens, $prev, $comma),
                );
            }
        }

        $next = PhpcsTokenStream::next($tokens, $comma, $close);
        if ($multiLine && ($next === null || $tokens[$next]->line !== $tokens[$comma]->line)) {
            return;
        }

        $after = $tokens[$comma + 1];
        $spaces = $after->code === 'T_WHITESPACE' ? FunctionTokens::width($after, $source) : 0;
        if ($spaces === 1) {
            return;
        }

        [$kind, $short, $content] = match (true) {
            $param['promoted'] => ['property modifier', 'PropertyModifier', $next === null ? '' : $tokens[$next]->text],
            $param['hint'] => ['type hint', 'Hint', $next === null ? '' : $tokens[$next]->text],
            default => ['argument', 'Arg', $tokens[$param['variable']]->text],
        };
        $this->emit(
            $context,
            $tokens[$comma]->pos,
            ($spaces === 0 ? 'NoSpaceBefore' : 'SpacingBefore') . $short,
            "Expected 1 space between comma and {$kind} \"{$content}\"; {$spaces} found",
            [self::pad($after, $tokens[$comma]->pos + 1)],
        );
    }

    /**
     * The fix for a space or line break before a comma: the comma moves to the end of the previous
     * parameter, with what phpcs removes around it.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<TextEdit>
     */
    private function moveComma(array $tokens, int $prev, int $comma): array
    {
        $edits = [
            TextEdit::insert($tokens[$prev]->pos + strlen($tokens[$prev]->text), ','),
            TextEdit::delete(new Span($tokens[$comma]->pos, $tokens[$comma]->pos + 1)),
        ];
        $start = PhpcsTokenStream::next($tokens, $comma);
        $after = $tokens[$comma + 1];
        $before = $tokens[$comma - 1];
        if ($start !== null && $tokens[$start]->line === $tokens[$comma]->line) {
            if ($after->code === 'T_WHITESPACE') {
                $edits[] = self::remove($after);
            }

            return $edits;
        }

        $newline = strpos($before->text, "\n");
        if ($before->code === 'T_WHITESPACE' && $newline !== false) {
            $edits[] = TextEdit::delete(new Span($before->pos + $newline + 1, $before->pos + strlen($before->text)));
        }

        $newline = strpos($after->text, "\n");
        if ($after->code === 'T_WHITESPACE' && $newline !== false) {
            $edits[] = TextEdit::delete(new Span($after->pos, $after->pos + $newline + 1));
        } elseif ($after->code === 'T_WHITESPACE') {
            $edits[] = self::remove($after);
        }

        return $edits;
    }

    private static function remove(PhpcsToken $token): TextEdit
    {
        return TextEdit::delete(new Span($token->pos, $token->pos + strlen($token->text)));
    }

    /** Replaces the whitespace token, or inserts at $at when there is none, with one space. */
    private static function pad(PhpcsToken $token, int $at): TextEdit
    {
        return $token->code === 'T_WHITESPACE'
            ? TextEdit::replace(new Span($token->pos, $token->pos + strlen($token->text)), ' ')
            : TextEdit::insert($at, ' ');
    }

    /** @param list<TextEdit> $edits */
    private function emit(LintContext $context, int $pos, string $code, string $message, array $edits): void
    {
        $this->report->issue(
            $context,
            FunctionTokens::withEdits(Issue::new($message, new Span($pos, $pos + 1)), $edits),
            [self::SNIFF . '.' . $code],
        );
    }
}
