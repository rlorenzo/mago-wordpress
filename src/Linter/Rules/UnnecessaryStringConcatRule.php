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
use function substr;

use const T_CONSTANT_ENCAPSED_STRING;
use const T_WHITESPACE;

/**
 * Ports `Generic.Strings.UnnecessaryStringConcat`: `'a' . 'b'`, two string literals joined by `.`.
 * Like the sniff it only compares the two tokens next to the dot (a comment between them, or a
 * dot next to a variable or a number, is not reported), skips two strings that open with different
 * characters (`'a' . "b"`) and a `'<' . '?'` or `'?' . '>'` split, which is how a PHP string holds a
 * tag. The sniff's `allowMultiline` and `error` properties have no setting here: use the rule's level.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class UnnecessaryStringConcatRule implements Rule
{
    private const CODE = 'Generic.Strings.UnnecessaryStringConcat.Found';

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
        $dot = 0;
        $previous = '';
        foreach (self::units($text) as [$unit, $offset]) {
            if ($left !== null && self::isString($unit) && $left[0] === $unit[0] && !self::isTagSplit($left, $unit)) {
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
                $left = $previous;
                $dot = $offset;
            }

            $previous = $unit;
        }
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

    /**
     * The file's tokens without whitespace, as phpcs sees them: a double-quoted string with
     * interpolation is one unit, as phpcs's `T_DOUBLE_QUOTED_STRING`. A token that is not a string
     * literal or a dot is kept, marked with a leading NUL, so that it separates the two sides of a dot.
     * Each is `[text, offset]`.
     *
     * @return Generator<array{string, int}>
     */
    private static function units(string $text): Generator
    {
        $open = null;
        foreach (PhpToken::tokenize($text) as $token) {
            if ($open !== null) {
                if ($token->text === '"') {
                    yield [substr($text, $open, $token->pos + 1 - $open), $open];
                    $open = null;
                }

                continue;
            }

            if ($token->id === T_WHITESPACE) {
                continue;
            }

            if ($token->text === '"') {
                $open = $token->pos;

                continue;
            }

            yield [
                $token->id === T_CONSTANT_ENCAPSED_STRING || $token->text === '.' ? $token->text : "\0",
                $token->pos,
            ];
        }
    }
}
