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
use Mago\Sdk\Syntax\SourceFile;
use Rlorenzo\MagoWordPress\Internal\BlankLinesAfterComment;
use Rlorenzo\MagoWordPress\Internal\CommentRun;
use Rlorenzo\MagoWordPress\Internal\CommentSpacing;
use Rlorenzo\MagoWordPress\Internal\InlineCommentScan;
use Rlorenzo\MagoWordPress\Internal\PhpcsComments;
use Rlorenzo\MagoWordPress\Internal\Report;

use function count;
use function in_array;
use function intdiv;
use function ltrim;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * Ports `Squiz.Commenting.InlineComment` as `WordPress-Docs` sets it, for `//` and `#` comments:
 * `WrongStyle` (`#`), `TabBefore`, `NoSpaceBefore`, `SpacingBefore`, `Empty`, `InvalidEndChar` and
 * `SpacingAfterAtFunctionEnd`. A run of `//` comments on consecutive lines is one comment, as in
 * the sniff, so a run reports a bad ending once, on its last line.
 *
 * `WordPress-Docs` turns off `DocBlock`, `NotCapital` and `SpacingAfter`; this rule does not
 * report them. `SpacingAfterAtFunctionEnd` is a different code and stays.
 */
final class InlineCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.InlineComment';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/inline-comment',
            name: 'Inline comment',
            description: 'Reports `#` comments, bad spacing before the text of a `//` comment, blank comments, comments that do not end in a full stop, and a blank line after the last comment of a function.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        $match = [];
        $eol = preg_match('/\r\n?|\n/', $text, $match) === 1 ? $match[0] : "\n";
        $tokens = PhpcsComments::tokens($context->file);
        $closers = null;
        $count = count($tokens);
        for ($i = 0; $i < $count;) {
            if ($tokens[$i]['type'] !== 'comment') {
                ++$i;

                continue;
            }

            $closers ??= InlineCommentScan::functionClosers($context);
            $i = $this->process($context, $tokens, $i, $eol, $closers);
        }
    }

    /**
     * @param list<array{type: string, text: string, line: int, span: Span, adjacent: bool}> $tokens
     * @param array<int, true> $closers
     *
     * @return int the index to continue from
     */
    private function process(LintContext $context, array $tokens, int $i, string $eol, array $closers): int
    {
        $text = $context->file->contents;
        $token = $tokens[$i];
        $comment = $token['text'];
        if (str_starts_with($comment, '#')) {
            $this->add(
                $context,
                'Perl-style comments are not allowed; use "// Comment" instead',
                $token['span'],
                'WrongStyle',
                TextEdit::replace($token['span'], '// ' . ltrim($comment, "# \t")),
            );
        }

        $before = InlineCommentScan::before($context->file, $tokens, $i);
        if ($before['line'] === $token['line'] && $before['closeCurly']) {
            return $i + 1;
        }

        if (!str_starts_with($comment, '//')) {
            return $i + 1;
        }

        $block = CommentRun::from($tokens, $i);
        $last = $block[count($block) - 1];

        $words = '';
        foreach ($block as $index) {
            $words .= $this->spacing($context, $tokens[$index]);
        }

        if ($words === '') {
            $span = $token['span'];
            $this->add(
                $context,
                'Blank comments are not allowed',
                $span,
                'Empty',
                TextEdit::delete(
                    new Span(
                        $span->start,
                        $span->end + (str_starts_with(substr($text, $span->end, 2), $eol) ? strlen($eol) : 0),
                    ),
                ),
            );

            return $last + 1;
        }

        $this->ending($context, $tokens[$last], $words);
        if ($before['line'] < $token['line']) {
            $this->spacingAfter($context, $tokens[$last], $eol, $closers);
        }

        return $last + 1;
    }

    /**
     * Reports the spacing before one line's text; returns the text the sniff collects from it.
     *
     * @param array{type: string, text: string, line: int, span: Span, adjacent: bool} $token
     */
    private function spacing(LintContext $context, array $token): string
    {
        $problem = CommentSpacing::problem($token['text']);
        if ($problem !== null) {
            $this->add(
                $context,
                $problem[0],
                $token['span'],
                $problem[1],
                TextEdit::replace($token['span'], '// ' . ltrim($token['text'], "/\t ")),
            );
        }

        return trim(substr($token['text'], 2));
    }

    /** @param array{type: string, text: string, line: int, span: Span, adjacent: bool} $last */
    private function ending(LintContext $context, array $last, string $words): void
    {
        if (!CommentSpacing::endsBadly($words)) {
            return;
        }

        $this->add(
            $context,
            'Inline comments must end in full-stops, exclamation marks, or question marks',
            $last['span'],
            'InvalidEndChar',
        );
    }

    /**
     * @param array{type: string, text: string, line: int, span: Span, adjacent: bool} $last
     * @param array<int, true> $closers
     */
    private function spacingAfter(LintContext $context, array $last, string $eol, array $closers): void
    {
        // Only a blank line in front of the `}` that ends a function has a code of its own.
        $range = BlankLinesAfterComment::range($context->file->contents, $last['span']->end, $eol, $closers);
        if ($range === null) {
            return;
        }

        $this->add(
            $context,
            'There must be no blank line following an inline comment',
            $last['span'],
            'SpacingAfterAtFunctionEnd',
            TextEdit::delete(new Span($range[0], $range[1])),
        );
    }

    private function add(LintContext $context, string $message, Span $span, string $code, ?TextEdit $edit = null): void
    {
        $issue = Issue::new($message, $span);
        if ($edit !== null) {
            $issue = $issue->withEdit($edit);
        }

        $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
    }
}
