<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function str_contains;

/**
 * Which Mago rules port which phpcs sniffs.
 *
 * @internal
 */
final class SniffMap
{
    /**
     * phpcs sniff => the Mago rule codes that port it: this package's `wordpress/*` and
     * `generic/*` rules and Mago's own core rules (the `wordpress` integration and the generic
     * ones that cover sniffs the WPCS standards pull in). The parity
     * harness and the phpcs.xml migration both read it.
     *
     * @var array<string, non-empty-list<string>>
     */
    public const RULES = [
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

    private function __construct() {}
}
