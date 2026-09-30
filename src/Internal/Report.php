<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function array_slice;
use function count;
use function explode;
use function implode;
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
 */
final class Report
{
    /**
     * WPCS sniff => the Mago rule codes that port it: this package's `wordpress/*` rules and
     * Mago's own core rules (the `wordpress` integration and a few generic ones). The parity
     * harness and the phpcs.xml migration both read it.
     *
     * @var array<string, non-empty-list<string>>
     */
    public const SNIFF_RULES = [
        'WordPress.CodeAnalysis.AssignmentInTernaryCondition' => ['wordpress/assignment-in-ternary-condition'],
        'WordPress.CodeAnalysis.EscapedNotTranslated' => ['wordpress/escaped-not-translated'],
        'WordPress.DateTime.CurrentTimeTimestamp' => ['wordpress/wp-date-time'],
        'WordPress.DateTime.RestrictedFunctions' => ['wordpress/wp-date-time'],
        'WordPress.DB.DirectDatabaseQuery' => ['no-direct-db-query', 'no-db-schema-change'],
        'WordPress.DB.PreparedSQL' => ['prepared-sql'],
        'WordPress.DB.PreparedSQLPlaceholders' => [
            'wordpress/prepared-sql-placeholders',
            'wordpress/prepared-sql-unquoted-complex-placeholder',
        ],
        'WordPress.DB.RestrictedClasses' => ['wordpress/db-restricted-classes'],
        'WordPress.DB.RestrictedFunctions' => ['wordpress/db-restricted-functions'],
        'WordPress.DB.SlowDBQuery' => ['wordpress/slow-db-query'],
        'WordPress.Files.FileName' => ['wordpress/file-name'],
        'WordPress.NamingConventions.PrefixAllGlobals' => ['wordpress/prefix-all-globals'],
        'WordPress.NamingConventions.ValidFunctionName' => ['wordpress/valid-function-name'],
        'WordPress.NamingConventions.ValidHookName' => ['wordpress/valid-hook-name'],
        'WordPress.NamingConventions.ValidPostTypeSlug' => ['wordpress/valid-post-type-slug'],
        'WordPress.NamingConventions.ValidVariableName' => ['wordpress/valid-variable-name'],
        'WordPress.PHP.DevelopmentFunctions' => ['wordpress/discouraged-wp-functions', 'no-debug-symbols'],
        'WordPress.PHP.DiscouragedPHPFunctions' => ['wordpress/discouraged-wp-functions'],
        'WordPress.PHP.DontExtract' => ['wordpress/dont-extract'],
        'WordPress.PHP.IniSet' => ['no-ini-set'],
        'WordPress.PHP.NoSilencedErrors' => ['no-error-control-operator'],
        'WordPress.PHP.PregQuoteDelimiter' => ['require-preg-quote-delimiter'],
        'WordPress.PHP.RestrictedPHPFunctions' => ['wordpress/restricted-php-functions'],
        'WordPress.PHP.StrictInArray' => ['wordpress/strict-in-array'],
        'WordPress.PHP.TypeCasts' => ['wordpress/type-casts'],
        'WordPress.PHP.YodaConditions' => ['wordpress/yoda-conditions'],
        'WordPress.Security.EscapeOutput' => ['no-unescaped-output'],
        'WordPress.Security.NonceVerification' => ['nonce-verification'],
        'WordPress.Security.PluginMenuSlug' => ['wordpress/plugin-menu-slug'],
        'WordPress.Security.SafeRedirect' => ['wordpress/safe-redirect'],
        'WordPress.Security.ValidatedSanitizedInput' => ['validated-sanitized-input'],
        'WordPress.WP.AlternativeFunctions' => ['use-wp-functions'],
        'WordPress.WP.Capabilities' => ['wordpress/capabilities', 'no-roles-as-capabilities'],
        'WordPress.WP.CapitalPDangit' => ['wordpress/capital-p-dangit'],
        'WordPress.WP.ClassNameCase' => ['wordpress/class-name-case'],
        'WordPress.WP.CronInterval' => ['wordpress/cron-interval'],
        'WordPress.WP.DeprecatedClasses' => ['wordpress/wp-deprecated-classes'],
        'WordPress.WP.DeprecatedFunctions' => ['wordpress/wp-deprecated-functions'],
        'WordPress.WP.DeprecatedParameters' => ['wordpress/wp-deprecated-parameters'],
        'WordPress.WP.DeprecatedParameterValues' => ['wordpress/wp-deprecated-parameter-values'],
        'WordPress.WP.DiscouragedConstants' => ['wordpress/discouraged-constants'],
        'WordPress.WP.DiscouragedFunctions' => ['wordpress/discouraged-wp-functions'],
        'WordPress.WP.EnqueuedResourceParameters' => ['wordpress/enqueued-resource-parameters'],
        'WordPress.WP.EnqueuedResources' => ['wordpress/enqueued-resources'],
        'WordPress.WP.GetMetaSingle' => ['wordpress/get-meta-single'],
        'WordPress.WP.GlobalVariablesOverride' => ['wordpress/global-variables-override'],
        'WordPress.WP.I18n' => ['wordpress/wp-i18n'],
        'WordPress.WP.PostsPerPage' => ['wordpress/posts-per-page'],
    ];

    /** @var WeakMap<SourceFile, PhpcsSuppressions> */
    private WeakMap $suppressions;

    /** @var array<string, list<string>> WPCS code => regexes, as phpcs compiles `<exclude-pattern>` */
    private readonly array $excludes;

    /**
     * @param array<string, list<string>> $excludePatterns WPCS code (standard, category, sniff or
     *        message) => phpcs `<exclude-pattern>` values; `*` excludes the code everywhere
     */
    public function __construct(
        private readonly bool $honorPhpcsComments,
        array $excludePatterns = [],
    ) {
        $this->suppressions = new WeakMap();
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
     * Reports the issue unless a phpcs comment silences one of the codes on
     * the line where its primary span starts.
     *
     * @param non-empty-list<string> $sniffCodes
     */
    public function issue(LintContext $context, Issue $issue, array $sniffCodes): void
    {
        if ($this->excludes !== [] && $this->isExcluded($context->file->path, $sniffCodes)) {
            return;
        }

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
        return $this->suppressions[$file] ??= PhpcsSuppressions::fromSource($file->contents);
    }
}
