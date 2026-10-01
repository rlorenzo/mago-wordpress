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
use function in_array;
use function ltrim;
use function preg_match;
use function str_contains;
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
    /**
     * phpcs sniff => the Mago rule codes that port it: this package's `wordpress/*` and
     * `generic/*` rules and Mago's own core rules (the `wordpress` integration and the generic
     * ones that cover sniffs the WPCS standards pull in). The parity
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
        // Generic sniffs the WPCS standards pull in: this package's `generic/*` ports and the
        // Mago core lint rules that cover the rest (analyzer-only coverage is in the README).
        'Generic.CodeAnalysis.AssignmentInCondition' => ['no-assign-in-condition'],
        'Generic.CodeAnalysis.EmptyPHPStatement' => ['no-noop'],
        'Generic.CodeAnalysis.ForLoopShouldBeWhileLoop' => ['prefer-while-loop'],
        'Generic.CodeAnalysis.ForLoopWithTestFunctionCall' => ['generic/for-loop-with-test-function-call'],
        'Generic.CodeAnalysis.JumbledIncrementer' => ['generic/jumbled-incrementer'],
        'Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence' => [
            'generic/require-explicit-boolean-operator-precedence',
        ],
        'Generic.CodeAnalysis.UnconditionalIfStatement' => ['constant-condition'],
        'Generic.CodeAnalysis.UnnecessaryFinalModifier' => ['no-redundant-final'],
        'Generic.CodeAnalysis.UselessOverridingMethod' => ['no-redundant-method-override'],
        'Generic.Files.ByteOrderMark' => ['generic/byte-order-mark'],
        'Generic.Files.OneObjectStructurePerFile' => ['single-class-per-file'],
        'Generic.NamingConventions.UpperCaseConstantName' => ['constant-name'],
        'Generic.PHP.BacktickOperator' => ['no-shell-execute-string'],
        'Generic.PHP.DisallowAlternativePHPTags' => ['generic/disallow-alternative-php-tags'],
        'Generic.PHP.DisallowShortOpenTag' => ['no-short-opening-tag'],
        'Generic.PHP.DiscourageGoto' => ['no-goto'],
        'Generic.PHP.ForbiddenFunctions' => ['disallowed-functions'],
        'Generic.PHP.LowerCaseConstant' => ['lowercase-keyword'],
        'Generic.PHP.LowerCaseKeyword' => ['lowercase-keyword'],
        'Generic.PHP.LowerCaseType' => ['lowercase-type-hint'],
        'Generic.Strings.UnnecessaryStringConcat' => ['no-redundant-string-concat'],
        'Generic.VersionControl.GitMergeConflict' => ['generic/git-merge-conflict'],
        'PEAR.NamingConventions.ValidClassName' => ['class-name'],
        'PSR2.Files.ClosingTag' => ['no-closing-tag'],
        'Squiz.PHP.DisallowMultipleAssignments' => ['no-multi-assignments'],
        'Squiz.PHP.DisallowSizeFunctionsInLoops' => ['generic/disallow-size-functions-in-loops'],
        'Squiz.PHP.Eval' => ['no-eval'],
        'Universal.Arrays.DisallowShortArraySyntax' => ['array-style'],
        'Universal.CodeAnalysis.ForeachUniqueAssignment' => ['generic/foreach-unique-assignment'],
        'Universal.Operators.DisallowShortTernary' => ['no-shorthand-ternary'],
    ];

    /** A rule this package registers (`wordpress/*`, `generic/*`), not one of Mago's core rules. */
    public static function isExtensionRule(string $code): bool
    {
        return str_contains($code, '/');
    }

    /** @var WeakMap<SourceFile, PhpcsSuppressions> */
    private WeakMap $suppressions;

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
