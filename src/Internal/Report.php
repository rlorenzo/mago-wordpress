<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\SourceFile;

use function array_slice;
use function count;
use function explode;
use function implode;
use function in_array;
use function ltrim;
use function preg_match;
use function str_replace;
use function strtr;

/**
 * The one reporting path for the rules, so each report honours the phpcs
 * suppression comments and the `exclude-patterns` setting for the WPCS sniff
 * codes the rule ports. Each extension owns one, so its settings never reach
 * another extension's rules.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class Report
{
    /** @var array<string, list<string>> WPCS code => regexes, as phpcs compiles `<exclude-pattern>` */
    private readonly array $excludes;

    /**
     * @param array<string, list<string>> $excludePatterns WPCS code (standard, category, sniff or
     *        message) => phpcs `<exclude-pattern>` values; `*` excludes the code everywhere
     * @param array<string, list<string>> $excludeGroups WPCS sniff => the function groups its
     *        `exclude` property drops
     */
    public function __construct(
        private readonly bool $honorPhpcsComments,
        array $excludePatterns = [],
        private readonly array $excludeGroups = [],
    ) {
        $excludes = [];
        foreach ($excludePatterns as $code => $patterns) {
            foreach ($patterns as $pattern) {
                // phpcs: `*` is `.*`, `\,` an escaped comma, and the match is unanchored and case-insensitive.
                $regex = '`' . str_replace(search: '`', replace: '\\`', subject: strtr($pattern, [
                    '\\,' => ',',
                    '*' => '.*',
                ])) . '`i';
                if (self::isValidRegex($regex)) {
                    $excludes[$code][] = $regex;
                }
            }
        }

        $this->excludes = $excludes;
    }

    /**
     * Whether the sniff's `exclude` property drops the group, as WPCS's restriction sniffs
     * skip an excluded group before matching.
     */
    public function excludesGroup(string $sniff, string $group): bool
    {
        return in_array($group, $this->excludeGroups[$sniff] ?? [], strict: true);
    }

    /**
     * Reports the issue unless a phpcs comment silences one of the codes on
     * the line where its primary span starts.
     *
     * @param non-empty-list<string> $sniffCodes
     *
     * @return bool whether it was reported, as phpcs's addError() returns
     */
    public function issue(LintContext $context, Issue $issue, array $sniffCodes): bool
    {
        if ($this->excludes !== [] && $this->isExcluded($context->file->path, $sniffCodes)) {
            return false;
        }

        if ($this->honorPhpcsComments && $this->isSuppressed($context->file, $issue, $sniffCodes)) {
            return false;
        }

        $context->report($issue);

        return true;
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
     * Whether an exclude pattern for one of the codes, or for its sniff, category or
     * standard, matches the file. phpcs matches absolute paths; a workspace-relative
     * path gets a leading slash so `/tests/*` still matches `tests/...`.
     *
     * @param list<string> $sniffCodes
     */
    private function isExcluded(string $path, array $sniffCodes): bool
    {
        $path = '/' . ltrim(str_replace(search: '\\', replace: '/', subject: $path), characters: '/');
        foreach ($sniffCodes as $code) {
            $parts = explode('.', $code);
            for ($length = count($parts); $length > 0; $length--) {
                foreach ($this->excludes[implode('.', array_slice($parts, offset: 0, length: $length))]
                    ?? [] as $regex) {
                    if (preg_match($regex, $path) === 1) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * A malformed pattern is dropped once here, rather than warning on every file.
     *
     * @mago-expect lint:no-error-control-operator
     */
    private static function isValidRegex(string $regex): bool
    {
        return @preg_match($regex, subject: '') !== false;
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
        return FileCache::remember(
            $file,
            'suppressions',
            static fn(): PhpcsSuppressions => PhpcsSuppressions::fromSource($file->contents),
        );
    }
}
