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
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\FileTokens;
use Rlorenzo\MagoWordPress\Internal\Report;

use function explode;
use function in_array;
use function ltrim;
use function max;
use function preg_match;
use function str_contains;
use function str_repeat;
use function str_split;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_INLINE_HTML;
use const T_START_HEREDOC;
use const T_WHITESPACE;
use const T_YIELD_FROM;

/**
 * Ports `Universal.WhiteSpace.DisallowInlineTabs`: a tab that is not part of a line's indentation
 * (between tokens, or inside a comment after its indentation) is replaced by the spaces that reach the
 * same column, with a tab width of 4 as the `WordPress` ruleset sets. Strings and inline HTML are left
 * alone, as in the sniff.
 *
 * phpcs reports once per token that holds a tab, and a doc comment is many tokens a line; this reports once
 * for each run of whitespace and each comment line, so the lines match and the counts can differ.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class DisallowInlineTabsRule implements Rule
{
    private const SNIFF = 'Universal.WhiteSpace.DisallowInlineTabs';

    private const TAB_WIDTH = 4;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/disallow-inline-tabs',
            name: 'Disallow inline tabs',
            description: 'Reports a tab that is not indentation (alignment in the middle of a line); use spaces.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        if (!str_contains($text, "\t")) {
            return;
        }

        foreach (FileTokens::raw($context->file) as $token) {
            if (!str_contains($token->text, "\t") || $token->id === T_INLINE_HTML) {
                continue;
            }

            if ($token->id === T_WHITESPACE) {
                $this->whitespace($context, $text, $token);
            } elseif (
                in_array($token->id, [T_COMMENT, T_DOC_COMMENT, T_START_HEREDOC, T_YIELD_FROM], strict: true)
                && !self::isAnnotation($token)
            ) {
                $this->lines($context, $text, $token);
            }
        }
    }

    /** A `phpcs:ignore`, `phpcs:disable` ... comment: phpcs makes it a token of its own, which the sniff does not read. */
    private static function isAnnotation(PhpToken $token): bool
    {
        return (
            preg_match('~^(?://|\#|/\*+)\s*@?phpcs:(?:ignore|ignoreFile|disable|enable|set)\b~i', $token->text) === 1
        );
    }

    /** Whitespace between tokens: only the part on the line the token starts on, unless it starts the line. */
    private function whitespace(LintContext $context, string $text, PhpToken $token): void
    {
        if (self::startsLine($text, $token->pos)) {
            return;
        }

        $first = explode("\n", $token->text, 2)[0];
        if (str_contains($first, "\t")) {
            $this->fix($context, $text, $token->pos, $first);
        }
    }

    /** A comment, heredoc opener or `yield from`: each of its lines, after the indentation of the lines that start a line. */
    private function lines(LintContext $context, string $text, PhpToken $token): void
    {
        $offset = $token->pos;
        foreach (explode("\n", $token->text) as $index => $line) {
            $lineStart = $offset;
            $offset += strlen($line) + 1;
            $indent = $index === 0 && !self::startsLine($text, $lineStart)
                ? 0
                : strlen($line) - strlen(ltrim($line, " \t"));
            $rest = substr($line, $indent);
            if (str_contains($rest, "\t")) {
                $this->fix($context, $text, $lineStart + $indent, $rest);
            }
        }
    }

    /**
     * @param int $at offset of $segment in the file; the issue is reported at its first tab
     */
    private function fix(LintContext $context, string $text, int $at, string $segment): void
    {
        $tab = $at + (int) strpos($segment, "\t");
        $this->report->issue(
            $context,
            Issue::new(
                'Spaces must be used for mid-line alignment; tabs are not allowed',
                new Span($tab, $tab + 1),
            )->withEdit(TextEdit::replace(new Span($at, $at + strlen($segment)), self::expand($text, $at, $segment))),
            [self::SNIFF . '.NonIndentTabsUsed'],
        );
    }

    /** $segment with each tab turned into the spaces that reach the next tab stop. */
    private static function expand(string $text, int $at, string $segment): string
    {
        $column = self::column($text, $at);
        $expanded = '';
        foreach (str_split($segment) as $byte) {
            $width = $byte === "\t" ? max(1, self::TAB_WIDTH - ($column % self::TAB_WIDTH)) : 1;
            $expanded .= $byte === "\t" ? str_repeat(' ', $width) : $byte;
            $column += $width;
        }

        return $expanded;
    }

    private static function startsLine(string $text, int $offset): bool
    {
        return $offset === 0 || $text[$offset - 1] === "\n";
    }

    /** The display column (0-based) of $offset, with tabs going to the next multiple of the tab width. */
    private static function column(string $text, int $offset): int
    {
        // A negative offset searches backwards from that far from the end, without copying the text before $offset.
        $newline = $offset === 0 ? false : strrpos($text, "\n", $offset - 1 - strlen($text));
        $start = $newline === false ? 0 : $newline + 1;
        $column = 0;
        for ($i = $start; $i < $offset; ++$i) {
            $column += $text[$i] === "\t" ? self::TAB_WIDTH - ($column % self::TAB_WIDTH) : 1;
        }

        return $column;
    }
}
