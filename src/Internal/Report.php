<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

/**
 * The one reporting path for the rules, so each report honours the phpcs
 * suppression comments for the WPCS sniff codes the rule ports. Each
 * extension owns one, so its settings never reach another extension's rules.
 *
 * @internal
 */
final class Report
{
    /** @var WeakMap<SourceFile, PhpcsSuppressions> */
    private WeakMap $suppressions;

    public function __construct(
        private readonly bool $honorPhpcsComments,
    ) {
        $this->suppressions = new WeakMap();
    }

    /**
     * Reports the issue unless a phpcs comment silences one of the codes on
     * the line where its primary span starts.
     *
     * @param non-empty-list<string> $sniffCodes
     */
    public function issue(LintContext $context, Issue $issue, array $sniffCodes): void
    {
        if ($this->honorPhpcsComments && $this->isSuppressed($context->file, $issue, $sniffCodes)) {
            return;
        }

        $context->report($issue);
    }

    /**
     * Reports an issue about the whole file, which a phpcs:disable of its
     * sniff also silences when no phpcs:enable follows, as in
     * `WordPress.Files.FileName`.
     *
     * @param non-empty-list<string> $sniffCodes
     */
    public function fileIssue(LintContext $context, Issue $issue, array $sniffCodes): void
    {
        if ($this->honorPhpcsComments && $this->suppressions($context->file)->disablesToEnd($sniffCodes)) {
            return;
        }

        $this->issue($context, $issue, $sniffCodes);
    }

    /**
     * @param list<string> $sniffCodes
     */
    private function isSuppressed(SourceFile $file, Issue $issue, array $sniffCodes): bool
    {
        $suppressions = $this->suppressions($file);

        return !$suppressions->isEmpty()
        && $suppressions->isSuppressedAt($issue->annotations[0]->span->start, $sniffCodes);
    }

    private function suppressions(SourceFile $file): PhpcsSuppressions
    {
        return $this->suppressions[$file] ??= PhpcsSuppressions::fromSource($file->contents);
    }
}
