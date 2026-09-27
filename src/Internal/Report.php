<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

/**
 * The one reporting path for the rules, so each report honours the phpcs
 * suppression comments for the WPCS sniff codes the rule ports.
 *
 * @internal
 */
final class Report
{
    private static bool $honorPhpcsComments = true;

    /** @var null|WeakMap<SourceFile, PhpcsSuppressions> */
    private static ?WeakMap $suppressions = null;

    private function __construct() {}

    /**
     * Set once per worker from the `honor-phpcs-comments` setting.
     */
    public static function honorPhpcsComments(bool $honor): void
    {
        self::$honorPhpcsComments = $honor;
    }

    /**
     * Reports the issue unless a phpcs comment silences one of the codes on
     * the line where its primary span starts.
     *
     * @param non-empty-list<string> $sniffCodes
     */
    public static function issue(LintContext $context, Issue $issue, array $sniffCodes): void
    {
        if (self::$honorPhpcsComments && self::isSuppressed($context->file, $issue, $sniffCodes)) {
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
    public static function fileIssue(LintContext $context, Issue $issue, array $sniffCodes): void
    {
        if (self::$honorPhpcsComments && self::suppressions($context->file)->disablesToEnd($sniffCodes)) {
            return;
        }

        self::issue($context, $issue, $sniffCodes);
    }

    /**
     * @param list<string> $sniffCodes
     */
    private static function isSuppressed(SourceFile $file, Issue $issue, array $sniffCodes): bool
    {
        $suppressions = self::suppressions($file);

        return !$suppressions->isEmpty()
        && $suppressions->isSuppressedAt($issue->annotations[0]->span->start, $sniffCodes);
    }

    private static function suppressions(SourceFile $file): PhpcsSuppressions
    {
        self::$suppressions ??= new WeakMap();
        /** @var WeakMap<SourceFile, PhpcsSuppressions> $cache */
        $cache = self::$suppressions;

        return $cache[$file] ??= PhpcsSuppressions::fromSource($file->contents);
    }
}
