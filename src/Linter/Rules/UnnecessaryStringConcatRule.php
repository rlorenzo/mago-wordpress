<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Generator;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function str_contains;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

use const T_CONSTANT_ENCAPSED_STRING;
use const T_WHITESPACE;

/**
 * Ports `Generic.Strings.UnnecessaryStringConcat`: `'a' . 'b'`, two string literals joined by `.`.
 * Like the sniff it only compares the two tokens next to the dot (a comment between them, or a
 * dot next to a variable or a number, is not reported), skips two strings that open with different
 * characters (`'a' . "b"`) and a `'<' . '?'` or `'?' . '>'` split, which is how a PHP string holds a
 * tag. `allowMultiline` is on, as WordPress-Extra sets it: two strings on different lines are not reported. The `error` property has no setting here: use the rule's level.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class UnnecessaryStringConcatRule implements Rule
{
    private const CODE = 'Generic.Strings.UnnecessaryStringConcat.Found';

    /** The id of a `"` token, which is also what inline HTML consisting of one quote is not. */
    private const QUOTE = 34;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/unnecessary-string-concat',
            name: 'Unnecessary string concat',
            description: 'Reports two string literals joined with `.`; write one string instead.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        if (!str_contains($text, '.')) {
            return;
        }

        // The string before the last dot, if that dot follows a string literal.
        $left = null;
        $leftEnd = 0;
        $dot = 0;
        $previous = '';
        $previousEnd = '';
        $previousPos = 0;
        foreach (self::units($text) as [$unit, $offset, $end]) {
            if (
                $left !== null
                && self::isString($unit)
                && $left[0] === $unit[0]
                && !self::isTagSplit($left, $unit)
                // WordPress-Extra sets `allowMultiline`: strings on different lines are not joined needlessly.
                && !self::breaksBetween($text, $leftEnd, $offset)
            ) {
                $this->report->issue(
                    $context,
                    Issue::new(
                        'String concat is not required here; use a single string instead',
                        new Span($dot, $dot + 1),
                        'joins two strings',
                    )->withHelp('Write one string literal.'),
                    [self::CODE],
                );
            }

            $left = null;
            if ($unit === '.' && self::isString($previous)) {
                $left = $previousEnd;
                $leftEnd = $previousPos + strlen($previous);
                $dot = $offset;
            }

            $previous = $unit;
            $previousEnd = $end;
            $previousPos = $offset;
        }
    }

    /** Whether a line break lies in $text from $from up to $to. */
    private static function breaksBetween(string $text, int $from, int $to): bool
    {
        $newline = strpos($text, "\n", $from);

        return $newline !== false && $newline < $to;
    }

    /** A `<` `?` or `?` `>` split by the dot: the closing or the opening tag, written in a string. */
    private static function isTagSplit(string $left, string $right): bool
    {
        $combined = substr($left, offset: -2, length: 1) . ($right[1] ?? '');

        return $combined === '?>' || $combined === '<?';
    }

    /** A string literal's text starts with a quote or `b` (binary); anything else is a different kind of token. */
    private static function isString(string $unit): bool
    {
        $first = $unit[0] ?? '';

        return ($first === "'" || $first === '"' || $first === 'b' || $first === 'B') && strlen($unit) >= 2;
    }

    /** What phpcs compares when a string is the one before the dot: it splits a multi-line string into a token a line. */
    private static function lastLine(string $string): string
    {
        $newline = strrpos($string, "\n");

        return $newline === false ? $string : substr($string, $newline + 1);
    }

    /**
     * The file's tokens without whitespace, as phpcs sees them: a double-quoted string with
     * interpolation is one unit, as phpcs's `T_DOUBLE_QUOTED_STRING`. A token that is not a string
     * literal or a dot is kept, marked with a leading NUL, so that it separates the two sides of a dot.
     * Each is `[text, offset, end]`: `end` is what phpcs compares when the string is the one before the
     * dot, which is the last line of a multi-line string, interpolated or not.
     *
     * @return Generator<array{string, int, string}>
     */
    private static function units(string $text): Generator
    {
        $open = null;
        foreach (PhpToken::tokenize($text) as $token) {
            if ($open !== null) {
                if ($token->id === self::QUOTE) {
                    $string = substr($text, $open, $token->pos + 1 - $open);
                    yield [$string, $open, self::lastLine($string)];
                    $open = null;
                }

                continue;
            }

            if ($token->id === T_WHITESPACE) {
                continue;
            }

            if ($token->id === self::QUOTE) {
                $open = $token->pos;

                continue;
            }

            if ($token->id !== T_CONSTANT_ENCAPSED_STRING) {
                $other = $token->text === '.' ? '.' : "\0";
                yield [$other, $token->pos, $other];

                continue;
            }

            yield [$token->text, $token->pos, self::lastLine($token->text)];
        }
    }
}
