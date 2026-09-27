<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function substr_count;

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
     * @param list<string> $sniffCodes
     */
    private static function isSuppressed(SourceFile $file, Issue $issue, array $sniffCodes): bool
    {
        self::$suppressions ??= new WeakMap();
        /** @var WeakMap<SourceFile, PhpcsSuppressions> $cache */
        $cache = self::$suppressions;
        $suppressions = $cache[$file] ?? null;
        if ($suppressions === null) {
            $suppressions = PhpcsSuppressions::fromSource($file->contents);
            $cache[$file] = $suppressions;
        }

        if ($suppressions->isEmpty()) {
            return false;
        }

        $line = substr_count($file->contents, needle: "\n", offset: 0, length: $issue->annotations[0]->span->start) + 1;

        return $suppressions->isSuppressed($line, $sniffCodes);
    }
}
