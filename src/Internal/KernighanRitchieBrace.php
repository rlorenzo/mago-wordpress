<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function strlen;
use function strpos;
use function strrpos;
use function substr;

/**
 * The checks of `Generic.Functions.OpeningFunctionBraceKernighanRitchie` for the opening brace of
 * one function or closure, reported under the sniff the caller names (the sniff itself, or
 * `Squiz.Functions.MultiLineFunctionDeclaration` for a single-line closure, which runs it).
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class KernighanRitchieBrace
{
    private function __construct() {}

    /**
     * @param list<PhpcsToken> $tokens
     * @param bool $contentAfter whether `ContentAfterBrace` is reported (WordPress-Core turns it off on the Generic sniff)
     * @mago-expect lint:excessive-parameter-list
     */
    public static function check(
        Report $report,
        LintContext $context,
        array $tokens,
        int $function,
        string $sniff,
        bool $contentAfter,
    ): void {
        $brace = $tokens[$function]->scopeOpener;
        if ($brace === null) {
            return;
        }

        $source = $context->file->contents;
        $prev = PhpcsTokenStream::previous($tokens, $brace);
        $at = $tokens[$brace];
        $span = new Span($at->pos, $at->pos + 1);
        $newLine = $prev !== null && ($at->line - $tokens[$prev]->line) > 0;
        if ($newLine) {
            $report->issue(
                $context,
                FunctionTokens::withEdits(
                    Issue::new('Opening brace should be on the same line as the declaration', $span),
                    self::moveBrace($tokens, $prev, $brace),
                ),
                [$sniff . '.BraceOnNewLine'],
            );
        }

        if ($contentAfter) {
            self::contentAfter($report, $context, $tokens, $function, $sniff, closeTagExempt: true);
        }

        // Only continue checking if the opening brace looks good.
        if ($newLine) {
            return;
        }

        $before = $tokens[$brace - 1];
        if ($before->code === 'T_WHITESPACE' && $before->text === ' ') {
            return;
        }

        $length = $before->code === 'T_WHITESPACE' ? FunctionTokens::width($before, $source) : 0;
        $issue = Issue::new("Expected 1 space before opening brace; found {$length}", $span);
        $issue = $before->code === 'T_WHITESPACE'
            ? $issue->withEdit(TextEdit::replace(new Span($before->pos, $before->pos + strlen($before->text)), ' '))
            : $issue->withEdit(TextEdit::insert($at->pos, ' '));
        $report->issue($context, $issue, [$sniff . '.SpaceBeforeBrace']);
    }

    /**
     * `ContentAfterBrace`: the brace is not the last thing on its line, unless the function is
     * empty. A `phpcs:` annotation comment does not count as content.
     *
     * @param list<PhpcsToken> $tokens
     * @mago-expect lint:excessive-parameter-list
     */
    public static function contentAfter(
        Report $report,
        LintContext $context,
        array $tokens,
        int $function,
        string $sniff,
        bool $closeTagExempt,
    ): void {
        $brace = $tokens[$function]->scopeOpener;
        if ($brace === null) {
            return;
        }

        $next = $brace;
        do {
            $next++;
        } while (
            isset($tokens[$next])
            && ($tokens[$next]->code === 'T_WHITESPACE' || FunctionTokens::isPhpcsAnnotation($tokens[$next]))
        );

        if (
            !isset($tokens[$next])
            || $tokens[$next]->line !== $tokens[$brace]->line
            || $next === $tokens[$function]->scopeCloser
            || $closeTagExempt && $tokens[$next]->code === 'T_CLOSE_TAG'
        ) {
            return;
        }

        $at = $tokens[$brace];
        $report->issue(
            $context,
            Issue::new(
                'Opening brace must be the last content on the line',
                new Span($at->pos, $at->pos + 1),
            )->withEdit(TextEdit::insert($at->pos + 1, FunctionTokens::eol($context->file->contents))),
            [$sniff . '.ContentAfterBrace'],
        );
    }

    /**
     * The fix for a brace on a line of its own: it moves after the end of the declaration, and the
     * line it leaves, with its indentation, goes with it.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<TextEdit>
     */
    private static function moveBrace(array $tokens, int $prev, int $brace): array
    {
        $end = $tokens[$prev]->pos + strlen($tokens[$prev]->text);
        $edits = [
            TextEdit::insert($end, ' {'),
            TextEdit::delete(new Span($tokens[$brace]->pos, $tokens[$brace]->pos + 1)),
        ];
        $after = $tokens[$brace + 1] ?? null;
        if ($after === null || $after->code !== 'T_WHITESPACE') {
            return $edits;
        }

        $newline = strpos($after->text, "\n");
        if ($newline === false) {
            return $edits;
        }

        // The brace is followed by a line break: remove it so no blank line is left at the top of the block.
        $edits[] = TextEdit::delete(new Span($after->pos, $after->pos + $newline + 1));
        $before = $tokens[$brace - 1];
        if ($before->code === 'T_WHITESPACE') {
            $last = strrpos($before->text, "\n");
            $indent = $last === false ? null : substr($before->text, $last + 1);
            if ($indent !== null && $indent !== '') {
                // The brace is preceded by indentation: remove it so none is left over.
                $edits[] = TextEdit::delete(new Span($before->pos + $last + 1, $before->pos + strlen($before->text)));
            }
        }

        return $edits;
    }
}
