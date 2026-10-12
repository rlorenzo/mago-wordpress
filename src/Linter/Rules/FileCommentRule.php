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
use Rlorenzo\MagoWordPress\Internal\DocComment;
use Rlorenzo\MagoWordPress\Internal\FileCommentTags;
use Rlorenzo\MagoWordPress\Internal\NodeIndex;
use Rlorenzo\MagoWordPress\Internal\PhpText;
use Rlorenzo\MagoWordPress\Internal\Report;

use function preg_match;
use function preg_replace_callback;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Ports `Squiz.Commenting.FileComment` as `WordPress-Docs` sets it, for the first PHP open tag, as the
 * sniff does: `Missing` (no docblock, or one that documents the declaration after it),
 * `WrongStyle` (`//` or `/*` where a docblock belongs), `SpacingAfterOpen`, `SpacingAfterComment`,
 * and the `@package` and `@subpackage` checks: `Duplicate*Tag`, `Empty*Tag` and `MissingPackageTag`.
 *
 * WordPress-Docs turns off the `@author`, `@copyright` and `@subpackage` requirements, the tag order
 * checks and the author and copyright formats; this rule does not report them. Duplicate and empty
 * `@author` and `@copyright` tags stay, as in the sniff.
 */
final class FileCommentRule implements Rule
{
    private const SNIFF = 'Squiz.Commenting.FileComment';

    private const TAGS = [NodeKind::FullOpeningTag, NodeKind::OpeningTag, NodeKind::ShortOpeningTag];

    /** A docblock followed by one of these documents it, not the file. */
    // `enum` only declares one when no call, constant access or assignment follows it, past any comments.
    private const DOCUMENTING = '~\G(?:class|interface|trait|enum(?![\w\x80-\xff])(?!(?:\s|/\*(?s:.*?)\*/|(?://|\#(?!\[))[^\n]*)*[(:=;,\\\\])|function|public|private|protected|final|static|abstract|readonly|const|include|include_once|require|require_once)(?![\w\x80-\xff])~i';

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/file-comment',
            name: 'File comment',
            description: 'Reports a missing or malformed file docblock: no docblock, a non-docblock comment, bad spacing, and a missing, duplicate or empty `@package` tag.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // The sniff returns the end of the file after the first open tag, so it checks only that one.
        $first = null;
        foreach (self::TAGS as $kind) {
            foreach (NodeIndex::ofKind($context->file, $context->node, $kind) as $tag) {
                if ($first === null || $tag->span->start < $first->start) {
                    $first = $tag->span;
                }
            }
        }

        if ($first !== null) {
            $this->check($context, $first);
        }
    }

    private function check(LintContext $context, Span $open): void
    {
        $text = $context->file->contents;
        $start = PhpText::skipSpace($text, $open->end);
        $matched = [];
        // A phpcs annotation is a token of its own in phpcs, not a comment.
        if (preg_match('~\G(?://|#(?!\[)|/\*(?!\*\s))(?!\s*@?phpcs:)~i', $text, $matched, 0, $start) === 1) {
            $this->add(
                $context,
                'You must use "/**" style comments for a file comment',
                new Span($start, $start + 1),
                'WrongStyle',
            );

            return;
        }

        if (preg_match('~\G/\*\*\s~', $text, $matched, 0, $start) !== 1) {
            $this->add($context, 'Missing file doc comment', $open, 'Missing');

            return;
        }

        $closer = PhpText::docblockCloser($text, $start);
        if ($closer === null) {
            return;
        }

        [$close, $end] = $closer;

        $next = PhpText::skipAttributes($text, $end);
        if (preg_match(self::DOCUMENTING, $text, $matched, 0, $next) === 1) {
            $this->add($context, 'Missing file doc comment', $open, 'Missing');

            return;
        }

        $line = static fn(int $offset): int => PhpText::line($text, $offset);
        if ($line($start) > ($line($open->start) + 1)) {
            $this->add($context, 'There must be no blank lines before the file comment', $open, 'SpacingAfterOpen');
        }

        $after = PhpText::skipSpace($text, $end);
        if ($after < strlen($text) && $line($after) !== ($line($close) + 2)) {
            $this->add(
                $context,
                'There must be exactly one blank line after the file comment',
                new Span($close, $end),
                'SpacingAfterComment',
            );
        }

        // Extra stars in front of the closing `*/` are no tag text (phpcs tokenizes them apart).
        $block = (string) preg_replace_callback(
            '~\*+(?=\*/$)~',
            static fn(array $stars): string => str_repeat(' ', strlen($stars[0])),
            substr($text, $start, $end - $start),
        );
        $this->tags($context, DocComment::tags($block, $start), new Span($close, $end));
    }

    /**
     * @param list<array{name: string, offset: int, content: ?string, ...}> $tags
     */
    private function tags(LintContext $context, array $tags, Span $end): void
    {
        foreach (FileCommentTags::findings($tags, $end) as [$message, $span, $code]) {
            $this->add($context, $message, $span, $code);
        }
    }

    private function add(LintContext $context, string $message, Span $span, string $code): void
    {
        $this->report->issue($context, Issue::new($message, $span), [self::SNIFF . '.' . $code]);
    }
}
