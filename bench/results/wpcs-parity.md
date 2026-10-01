# WPCS test-suite parity

`php bench/wpcs-parity.php ~/Projects/wpcs-src` (WPCS 3.4.1) runs every WPCS sniff test file through
the Mago rule that ports the sniff and compares the lines reported with the lines the sniff's own
`getErrorList()`/`getWarningList()` expect. Levels are not compared. `phpcs:set` directives are
honoured per region. `Recall` is matched / expected; `Extra` is lines Mago reports that WPCS does
not expect (some are Mago being right where the sniff is documented as not yet resolving a case).

Rows for `WordPress.Security.*`, `WordPress.DB.DirectDatabaseQuery`, `WordPress.DB.PreparedSQL`,
`WordPress.WP.AlternativeFunctions` and the four `WordPress.PHP.*` rows mapped to Mago core rules
measure Mago's own rules, not this package's.

Matching is by span (an expected line inside a report's primary span matches) and `RestrictedClasses`
files 2 and 3, which depend on test-only sniff groups, are skipped.

Snapshots below are newest first; the top table is the current state.

After Sprint E, 2026-09-30: the `exclude` (group), `custom_test_classes`, `treat_files_as_scoped`,
`allowed_custom_properties`, `is_theme` and `strict_class_file_names` properties now have settings,
so their `phpcs:set` directives are honoured, and `WordPress.Files.FileName` runs the 78 files in
`FileNameUnitTests/` (was 1; the directives before the open tag apply to the whole file). Extras
194 → 176 with 77 more files; no row dropped. Remaining extension-rule extras: the three `.inc`
hyphenation exceptions WPCS adds only in its own tests (FileName), a PHP 7-only namespace and a
parse-error file (GlobalVariablesOverride), `phpinfo()` from Mago's core `no-debug-symbols`, which
has no group setting (DevelopmentFunctions), and PrefixAllGlobals's documented cases.

| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |
|:---|---:|---:|---:|---:|---:|---:|:---|
| `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | 1 | 11 | 11 | 0 | 0 | 100% |  |
| `WordPress.CodeAnalysis.EscapedNotTranslated` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.DateTime.CurrentTimeTimestamp` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.DateTime.RestrictedFunctions` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.DB.DirectDatabaseQuery` | 2 | 70 | 68 | 2 | 4 | 97% | DirectDatabaseQueryUnitTest.1.inc: no setting for phpcs:set customCacheGetFunctions, customCacheSetFunctions, customCacheDeleteFunctions |
| `WordPress.DB.PreparedSQL` | 3 | 33 | 28 | 5 | 9 | 85% |  |
| `WordPress.DB.PreparedSQLPlaceholders` | 1 | 106 | 106 | 0 | 0 | 100% | PreparedSQLPlaceholdersUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.DB.RestrictedClasses` | 2 | 37 | 35 | 2 | 0 | 95% | RestrictedClassesUnitTest.1.inc: 3 phpcs:set directive(s), honoured by region; RestrictedClassesUnitTest.2.inc skipped: test-only sniff groups; RestrictedClassesUnitTest.3.inc skipped: test-only sniff groups |
| `WordPress.DB.RestrictedFunctions` | 1 | 42 | 41 | 1 | 0 | 98% |  |
| `WordPress.DB.SlowDBQuery` | 1 | 8 | 8 | 0 | 0 | 100% |  |
| `WordPress.Files.FileName` | 78 | 27 | 27 | 0 | 3 | 100% |  |
| `WordPress.NamingConventions.PrefixAllGlobals` | 9 | 99 | 96 | 3 | 5 | 97% | PrefixAllGlobalsUnitTest.1.inc: 17 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.4.inc: 4 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.5.inc: 1 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.7.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.8.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.9.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidFunctionName` | 2 | 29 | 29 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.ValidHookName` | 3 | 55 | 55 | 0 | 0 | 100% | ValidHookNameUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; ValidHookNameUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidPostTypeSlug` | 2 | 29 | 28 | 1 | 1 | 97% |  |
| `WordPress.NamingConventions.ValidVariableName` | 1 | 70 | 70 | 0 | 0 | 100% | ValidVariableNameUnitTest.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.PHP.DevelopmentFunctions` | 1 | 16 | 16 | 0 | 1 | 100% | DevelopmentFunctionsUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.PHP.DiscouragedPHPFunctions` | 1 | 24 | 24 | 0 | 0 | 100% |  |
| `WordPress.PHP.DontExtract` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.PHP.IniSet` | 1 | 28 | 28 | 0 | 18 | 100% |  |
| `WordPress.PHP.NoSilencedErrors` | 1 | 29 | 29 | 0 | 14 | 100% | NoSilencedErrorsUnitTest.inc: no setting for phpcs:set customAllowedFunctionsList, usePHPFunctionsList, context_length |
| `WordPress.PHP.PregQuoteDelimiter` | 1 | 5 | 4 | 1 | 2 | 80% |  |
| `WordPress.PHP.RestrictedPHPFunctions` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.PHP.StrictInArray` | 1 | 15 | 15 | 0 | 1 | 100% |  |
| `WordPress.PHP.TypeCasts` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.PHP.YodaConditions` | 1 | 19 | 19 | 0 | 0 | 100% |  |
| `WordPress.Security.EscapeOutput` | 23 | 176 | 101 | 75 | 65 | 57% | EscapeOutputUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region; EscapeOutputUnitTest.1.inc: no setting for phpcs:set customPrintingFunctions |
| `WordPress.Security.NonceVerification` | 8 | 66 | 38 | 28 | 25 | 58% | NonceVerificationUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region; NonceVerificationUnitTest.1.inc: no setting for phpcs:set customNonceVerificationFunctions |
| `WordPress.Security.PluginMenuSlug` | 1 | 5 | 5 | 0 | 0 | 100% |  |
| `WordPress.Security.SafeRedirect` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.Security.ValidatedSanitizedInput` | 5 | 106 | 36 | 70 | 12 | 34% | ValidatedSanitizedInputUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.WP.AlternativeFunctions` | 1 | 62 | 20 | 42 | 14 | 32% | AlternativeFunctionsUnitTest.inc: 10 phpcs:set directive(s), honoured by region |
| `WordPress.WP.Capabilities` | 5 | 48 | 37 | 11 | 0 | 77% | CapabilitiesUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.WP.CapitalPDangit` | 2 | 32 | 32 | 0 | 0 | 100% |  |
| `WordPress.WP.ClassNameCase` | 1 | 18 | 18 | 0 | 0 | 100% |  |
| `WordPress.WP.CronInterval` | 1 | 35 | 35 | 0 | 0 | 100% | CronIntervalUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.DeprecatedClasses` | 2 | 22 | 22 | 0 | 0 | 100% |  |
| `WordPress.WP.DeprecatedFunctions` | 2 | 388 | 388 | 0 | 0 | 100% |  |
| `WordPress.WP.DeprecatedParameters` | 1 | 64 | 64 | 0 | 0 | 100% |  |
| `WordPress.WP.DeprecatedParameterValues` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.DiscouragedConstants` | 1 | 20 | 20 | 0 | 0 | 100% |  |
| `WordPress.WP.DiscouragedFunctions` | 2 | 8 | 8 | 0 | 0 | 100% | DiscouragedFunctionsUnitTest.1.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.EnqueuedResourceParameters` | 2 | 30 | 29 | 1 | 0 | 97% |  |
| `WordPress.WP.EnqueuedResources` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.GetMetaSingle` | 1 | 12 | 12 | 0 | 0 | 100% |  |
| `WordPress.WP.GlobalVariablesOverride` | 8 | 43 | 43 | 0 | 2 | 100% | GlobalVariablesOverrideUnitTest.1.inc: 2 phpcs:set directive(s), honoured by region; GlobalVariablesOverrideUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; GlobalVariablesOverrideUnitTest.4.inc: 2 phpcs:set directive(s), honoured by region; GlobalVariablesOverrideUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.I18n` | 3 | 149 | 149 | 0 | 0 | 100% | I18nUnitTest.1.inc: 8 phpcs:set directive(s), honoured by region; I18nUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.PostsPerPage` | 1 | 26 | 26 | 0 | 0 | 100% | PostsPerPageUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| **Total** | **194** | **2158** | **1916** | **242** | **176** | **89%** | |

Only reports carrying the mapped rule codes count (Mago's parser/semantics errors on WPCS's
deliberately odd test files no longer count as matches or extras), so numbers from this run on
are slightly lower and exact.

After wave 4 (wp-i18n, prepared-sql-placeholders, discouraged-wp-functions, wp-deprecated-functions,
wp-deprecated-classes), 2026-09-30. Every extension rule with a WPCS test is now at 100% except the
accepted cases (test-only sniff groups, `phpcs:set exclude`/`custom_test_classes`/
`treat_files_as_scoped` which have no setting, WPCS's deliberate parse-error files, and severity-3
"undetermined" warnings phpcs hides by default). The rows still short are Mago's core rules:

| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |
|:---|---:|---:|---:|---:|---:|---:|:---|
| `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | 1 | 11 | 11 | 0 | 0 | 100% |  |
| `WordPress.CodeAnalysis.EscapedNotTranslated` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.DateTime.CurrentTimeTimestamp` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.DateTime.RestrictedFunctions` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.DB.DirectDatabaseQuery` | 2 | 70 | 68 | 2 | 4 | 97% | DirectDatabaseQueryUnitTest.1.inc: no setting for phpcs:set customCacheGetFunctions, customCacheSetFunctions, customCacheDeleteFunctions |
| `WordPress.DB.PreparedSQL` | 3 | 33 | 28 | 5 | 9 | 85% |  |
| `WordPress.DB.PreparedSQLPlaceholders` | 1 | 106 | 106 | 0 | 0 | 100% | PreparedSQLPlaceholdersUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.DB.RestrictedClasses` | 2 | 37 | 35 | 2 | 2 | 95% | RestrictedClassesUnitTest.1.inc: no setting for phpcs:set exclude; RestrictedClassesUnitTest.2.inc skipped: test-only sniff groups; RestrictedClassesUnitTest.3.inc skipped: test-only sniff groups |
| `WordPress.DB.RestrictedFunctions` | 1 | 42 | 41 | 1 | 0 | 98% |  |
| `WordPress.DB.SlowDBQuery` | 1 | 8 | 8 | 0 | 0 | 100% |  |
| `WordPress.Files.FileName` | 1 | 1 | 1 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.PrefixAllGlobals` | 9 | 99 | 96 | 3 | 6 | 97% | PrefixAllGlobalsUnitTest.1.inc: 15 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.1.inc: no setting for phpcs:set custom_test_classes; PrefixAllGlobalsUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.4.inc: 4 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.5.inc: 1 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.7.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.8.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.9.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidFunctionName` | 2 | 29 | 29 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.ValidHookName` | 3 | 55 | 55 | 0 | 0 | 100% | ValidHookNameUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; ValidHookNameUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidPostTypeSlug` | 2 | 29 | 28 | 1 | 1 | 97% |  |
| `WordPress.NamingConventions.ValidVariableName` | 1 | 70 | 70 | 0 | 3 | 100% | ValidVariableNameUnitTest.inc: no setting for phpcs:set allowed_custom_properties |
| `WordPress.PHP.DevelopmentFunctions` | 1 | 16 | 16 | 0 | 3 | 100% | DevelopmentFunctionsUnitTest.inc: no setting for phpcs:set exclude |
| `WordPress.PHP.DiscouragedPHPFunctions` | 1 | 24 | 24 | 0 | 0 | 100% |  |
| `WordPress.PHP.DontExtract` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.PHP.IniSet` | 1 | 28 | 28 | 0 | 18 | 100% |  |
| `WordPress.PHP.NoSilencedErrors` | 1 | 29 | 29 | 0 | 14 | 100% | NoSilencedErrorsUnitTest.inc: no setting for phpcs:set customAllowedFunctionsList, usePHPFunctionsList, context_length |
| `WordPress.PHP.PregQuoteDelimiter` | 1 | 5 | 4 | 1 | 2 | 80% |  |
| `WordPress.PHP.RestrictedPHPFunctions` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.PHP.StrictInArray` | 1 | 15 | 15 | 0 | 1 | 100% |  |
| `WordPress.PHP.TypeCasts` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.PHP.YodaConditions` | 1 | 19 | 19 | 0 | 0 | 100% |  |
| `WordPress.Security.EscapeOutput` | 23 | 176 | 101 | 75 | 65 | 57% | EscapeOutputUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region; EscapeOutputUnitTest.1.inc: no setting for phpcs:set customPrintingFunctions |
| `WordPress.Security.NonceVerification` | 8 | 66 | 38 | 28 | 25 | 58% | NonceVerificationUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region; NonceVerificationUnitTest.1.inc: no setting for phpcs:set customNonceVerificationFunctions |
| `WordPress.Security.PluginMenuSlug` | 1 | 5 | 5 | 0 | 0 | 100% |  |
| `WordPress.Security.SafeRedirect` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.Security.ValidatedSanitizedInput` | 5 | 106 | 36 | 70 | 12 | 34% | ValidatedSanitizedInputUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.WP.AlternativeFunctions` | 1 | 62 | 20 | 42 | 14 | 32% | AlternativeFunctionsUnitTest.inc: 10 phpcs:set directive(s), honoured by region |
| `WordPress.WP.Capabilities` | 5 | 48 | 37 | 11 | 0 | 77% | CapabilitiesUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.WP.CapitalPDangit` | 2 | 32 | 32 | 0 | 0 | 100% |  |
| `WordPress.WP.ClassNameCase` | 1 | 18 | 18 | 0 | 0 | 100% |  |
| `WordPress.WP.CronInterval` | 1 | 35 | 35 | 0 | 0 | 100% | CronIntervalUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.DeprecatedClasses` | 2 | 22 | 22 | 0 | 0 | 100% |  |
| `WordPress.WP.DeprecatedFunctions` | 2 | 388 | 388 | 0 | 0 | 100% |  |
| `WordPress.WP.DeprecatedParameters` | 1 | 64 | 64 | 0 | 0 | 100% |  |
| `WordPress.WP.DeprecatedParameterValues` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.DiscouragedConstants` | 1 | 20 | 20 | 0 | 0 | 100% |  |
| `WordPress.WP.DiscouragedFunctions` | 2 | 8 | 8 | 0 | 2 | 100% | DiscouragedFunctionsUnitTest.1.inc: no setting for phpcs:set exclude |
| `WordPress.WP.EnqueuedResourceParameters` | 2 | 30 | 29 | 1 | 0 | 97% |  |
| `WordPress.WP.EnqueuedResources` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.GetMetaSingle` | 1 | 12 | 12 | 0 | 0 | 100% |  |
| `WordPress.WP.GlobalVariablesOverride` | 8 | 43 | 43 | 0 | 12 | 100% | GlobalVariablesOverrideUnitTest.1.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.3.inc: no setting for phpcs:set treat_files_as_scoped; GlobalVariablesOverrideUnitTest.4.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.6.inc: no setting for phpcs:set treat_files_as_scoped |
| `WordPress.WP.I18n` | 3 | 149 | 149 | 0 | 0 | 100% | I18nUnitTest.1.inc: 8 phpcs:set directive(s), honoured by region; I18nUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.PostsPerPage` | 1 | 26 | 26 | 0 | 1 | 100% | PostsPerPageUnitTest.inc: 3 phpcs:set directive(s), honoured by region; PostsPerPageUnitTest.inc: no setting for phpcs:set exclude |
| **Total** | **117** | **2132** | **1890** | **242** | **194** | **89%** | |

After the shared-code pass (deprecations reported regardless of `minimum-wp-version`, `namespace\`
relative calls skipped everywhere), 2026-09-30:

| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |
|:---|---:|---:|---:|---:|---:|---:|:---|
| `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | 1 | 11 | 11 | 0 | 0 | 100% |  |
| `WordPress.CodeAnalysis.EscapedNotTranslated` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.DateTime.CurrentTimeTimestamp` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.DateTime.RestrictedFunctions` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.DB.DirectDatabaseQuery` | 2 | 70 | 68 | 2 | 4 | 97% | DirectDatabaseQueryUnitTest.1.inc: no setting for phpcs:set customCacheGetFunctions, customCacheSetFunctions, customCacheDeleteFunctions |
| `WordPress.DB.PreparedSQL` | 3 | 33 | 28 | 5 | 9 | 85% |  |
| `WordPress.DB.PreparedSQLPlaceholders` | 1 | 106 | 53 | 53 | 0 | 50% | PreparedSQLPlaceholdersUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.DB.RestrictedClasses` | 2 | 37 | 35 | 2 | 2 | 95% | RestrictedClassesUnitTest.1.inc: no setting for phpcs:set exclude; RestrictedClassesUnitTest.2.inc skipped: test-only sniff groups; RestrictedClassesUnitTest.3.inc skipped: test-only sniff groups |
| `WordPress.DB.RestrictedFunctions` | 1 | 42 | 41 | 1 | 0 | 98% |  |
| `WordPress.DB.SlowDBQuery` | 1 | 8 | 8 | 0 | 0 | 100% |  |
| `WordPress.Files.FileName` | 1 | 1 | 1 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.PrefixAllGlobals` | 9 | 99 | 96 | 3 | 6 | 97% | PrefixAllGlobalsUnitTest.1.inc: 15 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.1.inc: no setting for phpcs:set custom_test_classes; PrefixAllGlobalsUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.4.inc: 4 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.5.inc: 1 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.7.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.8.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.9.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidFunctionName` | 2 | 29 | 29 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.ValidHookName` | 3 | 55 | 55 | 0 | 0 | 100% | ValidHookNameUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; ValidHookNameUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidPostTypeSlug` | 2 | 29 | 28 | 1 | 1 | 97% |  |
| `WordPress.NamingConventions.ValidVariableName` | 1 | 70 | 70 | 0 | 3 | 100% | ValidVariableNameUnitTest.inc: no setting for phpcs:set allowed_custom_properties |
| `WordPress.PHP.DevelopmentFunctions` | 1 | 16 | 16 | 0 | 3 | 100% | DevelopmentFunctionsUnitTest.inc: no setting for phpcs:set exclude |
| `WordPress.PHP.DiscouragedPHPFunctions` | 1 | 24 | 24 | 0 | 0 | 100% |  |
| `WordPress.PHP.DontExtract` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.PHP.IniSet` | 1 | 28 | 28 | 0 | 18 | 100% |  |
| `WordPress.PHP.NoSilencedErrors` | 1 | 29 | 29 | 0 | 14 | 100% | NoSilencedErrorsUnitTest.inc: no setting for phpcs:set customAllowedFunctionsList, usePHPFunctionsList, context_length |
| `WordPress.PHP.PregQuoteDelimiter` | 1 | 5 | 4 | 1 | 2 | 80% |  |
| `WordPress.PHP.RestrictedPHPFunctions` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.PHP.StrictInArray` | 1 | 15 | 15 | 0 | 1 | 100% |  |
| `WordPress.PHP.TypeCasts` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.PHP.YodaConditions` | 1 | 19 | 19 | 0 | 0 | 100% |  |
| `WordPress.Security.EscapeOutput` | 23 | 176 | 101 | 75 | 65 | 57% | EscapeOutputUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region; EscapeOutputUnitTest.1.inc: no setting for phpcs:set customPrintingFunctions |
| `WordPress.Security.NonceVerification` | 8 | 66 | 38 | 28 | 25 | 58% | NonceVerificationUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region; NonceVerificationUnitTest.1.inc: no setting for phpcs:set customNonceVerificationFunctions |
| `WordPress.Security.PluginMenuSlug` | 1 | 5 | 5 | 0 | 0 | 100% |  |
| `WordPress.Security.SafeRedirect` | 1 | 4 | 4 | 0 | 0 | 100% |  |
| `WordPress.Security.ValidatedSanitizedInput` | 5 | 106 | 36 | 70 | 12 | 34% | ValidatedSanitizedInputUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.WP.AlternativeFunctions` | 1 | 62 | 20 | 42 | 14 | 32% | AlternativeFunctionsUnitTest.inc: 10 phpcs:set directive(s), honoured by region |
| `WordPress.WP.Capabilities` | 5 | 48 | 37 | 11 | 0 | 77% | CapabilitiesUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.WP.CapitalPDangit` | 2 | 32 | 32 | 0 | 0 | 100% |  |
| `WordPress.WP.ClassNameCase` | 1 | 18 | 18 | 0 | 0 | 100% |  |
| `WordPress.WP.CronInterval` | 1 | 35 | 35 | 0 | 0 | 100% | CronIntervalUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.DeprecatedClasses` | 2 | 22 | 20 | 2 | 0 | 91% |  |
| `WordPress.WP.DeprecatedFunctions` | 2 | 388 | 387 | 1 | 0 | 100% |  |
| `WordPress.WP.DeprecatedParameters` | 1 | 64 | 64 | 0 | 0 | 100% |  |
| `WordPress.WP.DeprecatedParameterValues` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.DiscouragedConstants` | 1 | 20 | 20 | 0 | 0 | 100% |  |
| `WordPress.WP.DiscouragedFunctions` | 2 | 8 | 5 | 3 | 2 | 63% | DiscouragedFunctionsUnitTest.1.inc: no setting for phpcs:set exclude |
| `WordPress.WP.EnqueuedResourceParameters` | 2 | 30 | 29 | 1 | 0 | 97% |  |
| `WordPress.WP.EnqueuedResources` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.GetMetaSingle` | 1 | 12 | 12 | 0 | 0 | 100% |  |
| `WordPress.WP.GlobalVariablesOverride` | 8 | 43 | 43 | 0 | 12 | 100% | GlobalVariablesOverrideUnitTest.1.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.3.inc: no setting for phpcs:set treat_files_as_scoped; GlobalVariablesOverrideUnitTest.4.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.6.inc: no setting for phpcs:set treat_files_as_scoped |
| `WordPress.WP.I18n` | 3 | 149 | 120 | 29 | 1 | 81% | I18nUnitTest.1.inc: 8 phpcs:set directive(s), honoured by region; I18nUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.PostsPerPage` | 1 | 26 | 26 | 0 | 1 | 100% | PostsPerPageUnitTest.inc: 3 phpcs:set directive(s), honoured by region; PostsPerPageUnitTest.inc: no setting for phpcs:set exclude |
| **Total** | **117** | **2132** | **1802** | **330** | **195** | **85%** | |

After wave 3 (capital-p-dangit, valid-hook-name, global-variables-override), 2026-09-30:

| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |
|:---|---:|---:|---:|---:|---:|---:|:---|
| `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | 1 | 11 | 11 | 0 | 0 | 100% |  |
| `WordPress.CodeAnalysis.EscapedNotTranslated` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DateTime.CurrentTimeTimestamp` | 1 | 10 | 10 | 0 | 1 | 100% |  |
| `WordPress.DateTime.RestrictedFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DB.DirectDatabaseQuery` | 2 | 70 | 68 | 2 | 4 | 97% | DirectDatabaseQueryUnitTest.1.inc: no setting for phpcs:set customCacheGetFunctions, customCacheSetFunctions, customCacheDeleteFunctions |
| `WordPress.DB.PreparedSQL` | 3 | 33 | 28 | 5 | 9 | 85% |  |
| `WordPress.DB.PreparedSQLPlaceholders` | 1 | 106 | 53 | 53 | 0 | 50% | PreparedSQLPlaceholdersUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.DB.RestrictedClasses` | 2 | 37 | 35 | 2 | 2 | 95% | RestrictedClassesUnitTest.1.inc: no setting for phpcs:set exclude; RestrictedClassesUnitTest.2.inc skipped: test-only sniff groups; RestrictedClassesUnitTest.3.inc skipped: test-only sniff groups |
| `WordPress.DB.RestrictedFunctions` | 1 | 42 | 41 | 1 | 0 | 98% |  |
| `WordPress.DB.SlowDBQuery` | 1 | 8 | 8 | 0 | 0 | 100% |  |
| `WordPress.Files.FileName` | 1 | 1 | 1 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.PrefixAllGlobals` | 9 | 99 | 96 | 3 | 8 | 97% | PrefixAllGlobalsUnitTest.1.inc: 15 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.1.inc: no setting for phpcs:set custom_test_classes; PrefixAllGlobalsUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.4.inc: 4 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.5.inc: 1 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.7.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.8.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.9.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidFunctionName` | 2 | 29 | 29 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.ValidHookName` | 3 | 55 | 55 | 0 | 0 | 100% | ValidHookNameUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; ValidHookNameUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidPostTypeSlug` | 2 | 29 | 28 | 1 | 2 | 97% |  |
| `WordPress.NamingConventions.ValidVariableName` | 1 | 70 | 70 | 0 | 3 | 100% | ValidVariableNameUnitTest.inc: no setting for phpcs:set allowed_custom_properties |
| `WordPress.PHP.DevelopmentFunctions` | 1 | 16 | 16 | 0 | 4 | 100% | DevelopmentFunctionsUnitTest.inc: no setting for phpcs:set exclude |
| `WordPress.PHP.DiscouragedPHPFunctions` | 1 | 24 | 24 | 0 | 1 | 100% |  |
| `WordPress.PHP.DontExtract` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.IniSet` | 1 | 28 | 28 | 0 | 18 | 100% |  |
| `WordPress.PHP.NoSilencedErrors` | 1 | 29 | 29 | 0 | 14 | 100% | NoSilencedErrorsUnitTest.inc: no setting for phpcs:set customAllowedFunctionsList, usePHPFunctionsList, context_length |
| `WordPress.PHP.PregQuoteDelimiter` | 1 | 5 | 4 | 1 | 2 | 80% |  |
| `WordPress.PHP.RestrictedPHPFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.StrictInArray` | 1 | 15 | 15 | 0 | 2 | 100% |  |
| `WordPress.PHP.TypeCasts` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.PHP.YodaConditions` | 1 | 19 | 19 | 0 | 0 | 100% |  |
| `WordPress.Security.EscapeOutput` | 23 | 176 | 101 | 75 | 65 | 57% | EscapeOutputUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region; EscapeOutputUnitTest.1.inc: no setting for phpcs:set customPrintingFunctions |
| `WordPress.Security.NonceVerification` | 8 | 66 | 38 | 28 | 25 | 58% | NonceVerificationUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region; NonceVerificationUnitTest.1.inc: no setting for phpcs:set customNonceVerificationFunctions |
| `WordPress.Security.PluginMenuSlug` | 1 | 5 | 5 | 0 | 1 | 100% |  |
| `WordPress.Security.SafeRedirect` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.Security.ValidatedSanitizedInput` | 5 | 106 | 36 | 70 | 12 | 34% | ValidatedSanitizedInputUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.WP.AlternativeFunctions` | 1 | 62 | 20 | 42 | 14 | 32% | AlternativeFunctionsUnitTest.inc: 10 phpcs:set directive(s), honoured by region |
| `WordPress.WP.Capabilities` | 5 | 48 | 37 | 11 | 1 | 77% | CapabilitiesUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.WP.CapitalPDangit` | 2 | 32 | 32 | 0 | 0 | 100% |  |
| `WordPress.WP.ClassNameCase` | 1 | 18 | 18 | 0 | 0 | 100% |  |
| `WordPress.WP.CronInterval` | 1 | 35 | 35 | 0 | 1 | 100% | CronIntervalUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.DeprecatedClasses` | 2 | 22 | 20 | 2 | 0 | 91% |  |
| `WordPress.WP.DeprecatedFunctions` | 2 | 388 | 381 | 7 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameters` | 1 | 64 | 63 | 1 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameterValues` | 2 | 28 | 28 | 0 | 1 | 100% |  |
| `WordPress.WP.DiscouragedConstants` | 1 | 20 | 20 | 0 | 1 | 100% |  |
| `WordPress.WP.DiscouragedFunctions` | 2 | 8 | 5 | 3 | 3 | 63% | DiscouragedFunctionsUnitTest.1.inc: no setting for phpcs:set exclude |
| `WordPress.WP.EnqueuedResourceParameters` | 2 | 30 | 29 | 1 | 0 | 97% |  |
| `WordPress.WP.EnqueuedResources` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.GetMetaSingle` | 1 | 12 | 12 | 0 | 1 | 100% |  |
| `WordPress.WP.GlobalVariablesOverride` | 8 | 43 | 43 | 0 | 12 | 100% | GlobalVariablesOverrideUnitTest.1.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.3.inc: no setting for phpcs:set treat_files_as_scoped; GlobalVariablesOverrideUnitTest.4.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.6.inc: no setting for phpcs:set treat_files_as_scoped |
| `WordPress.WP.I18n` | 3 | 149 | 120 | 29 | 1 | 81% | I18nUnitTest.1.inc: 8 phpcs:set directive(s), honoured by region; I18nUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.PostsPerPage` | 1 | 26 | 26 | 0 | 1 | 100% | PostsPerPageUnitTest.inc: 3 phpcs:set directive(s), honoured by region; PostsPerPageUnitTest.inc: no setting for phpcs:set exclude |
| **Total** | **117** | **2132** | **1795** | **337** | **216** | **84%** | |

After wave 2 (slow-db-query, enqueued-resource-parameters, class-name-case), 2026-09-30:

| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |
|:---|---:|---:|---:|---:|---:|---:|:---|
| `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | 1 | 11 | 11 | 0 | 0 | 100% |  |
| `WordPress.CodeAnalysis.EscapedNotTranslated` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DateTime.CurrentTimeTimestamp` | 1 | 10 | 10 | 0 | 1 | 100% |  |
| `WordPress.DateTime.RestrictedFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DB.DirectDatabaseQuery` | 2 | 70 | 68 | 2 | 4 | 97% | DirectDatabaseQueryUnitTest.1.inc: no setting for phpcs:set customCacheGetFunctions, customCacheSetFunctions, customCacheDeleteFunctions |
| `WordPress.DB.PreparedSQL` | 3 | 33 | 28 | 5 | 9 | 85% |  |
| `WordPress.DB.PreparedSQLPlaceholders` | 1 | 106 | 53 | 53 | 0 | 50% | PreparedSQLPlaceholdersUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.DB.RestrictedClasses` | 2 | 37 | 35 | 2 | 2 | 95% | RestrictedClassesUnitTest.1.inc: no setting for phpcs:set exclude; RestrictedClassesUnitTest.2.inc skipped: test-only sniff groups; RestrictedClassesUnitTest.3.inc skipped: test-only sniff groups |
| `WordPress.DB.RestrictedFunctions` | 1 | 42 | 41 | 1 | 0 | 98% |  |
| `WordPress.DB.SlowDBQuery` | 1 | 8 | 8 | 0 | 0 | 100% |  |
| `WordPress.Files.FileName` | 1 | 1 | 1 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.PrefixAllGlobals` | 9 | 99 | 96 | 3 | 8 | 97% | PrefixAllGlobalsUnitTest.1.inc: 15 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.1.inc: no setting for phpcs:set custom_test_classes; PrefixAllGlobalsUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.4.inc: 4 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.5.inc: 1 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.7.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.8.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.9.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidFunctionName` | 2 | 29 | 29 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.ValidHookName` | 3 | 55 | 49 | 6 | 1 | 89% | ValidHookNameUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; ValidHookNameUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidPostTypeSlug` | 2 | 29 | 28 | 1 | 2 | 97% |  |
| `WordPress.NamingConventions.ValidVariableName` | 1 | 70 | 70 | 0 | 3 | 100% | ValidVariableNameUnitTest.inc: no setting for phpcs:set allowed_custom_properties |
| `WordPress.PHP.DevelopmentFunctions` | 1 | 16 | 16 | 0 | 4 | 100% | DevelopmentFunctionsUnitTest.inc: no setting for phpcs:set exclude |
| `WordPress.PHP.DiscouragedPHPFunctions` | 1 | 24 | 24 | 0 | 1 | 100% |  |
| `WordPress.PHP.DontExtract` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.IniSet` | 1 | 28 | 28 | 0 | 18 | 100% |  |
| `WordPress.PHP.NoSilencedErrors` | 1 | 29 | 29 | 0 | 14 | 100% | NoSilencedErrorsUnitTest.inc: no setting for phpcs:set customAllowedFunctionsList, usePHPFunctionsList, context_length |
| `WordPress.PHP.PregQuoteDelimiter` | 1 | 5 | 4 | 1 | 2 | 80% |  |
| `WordPress.PHP.RestrictedPHPFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.StrictInArray` | 1 | 15 | 15 | 0 | 2 | 100% |  |
| `WordPress.PHP.TypeCasts` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.PHP.YodaConditions` | 1 | 19 | 19 | 0 | 0 | 100% |  |
| `WordPress.Security.EscapeOutput` | 23 | 176 | 101 | 75 | 65 | 57% | EscapeOutputUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region; EscapeOutputUnitTest.1.inc: no setting for phpcs:set customPrintingFunctions |
| `WordPress.Security.NonceVerification` | 8 | 66 | 38 | 28 | 25 | 58% | NonceVerificationUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region; NonceVerificationUnitTest.1.inc: no setting for phpcs:set customNonceVerificationFunctions |
| `WordPress.Security.PluginMenuSlug` | 1 | 5 | 5 | 0 | 1 | 100% |  |
| `WordPress.Security.SafeRedirect` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.Security.ValidatedSanitizedInput` | 5 | 106 | 36 | 70 | 12 | 34% | ValidatedSanitizedInputUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.WP.AlternativeFunctions` | 1 | 62 | 20 | 42 | 14 | 32% | AlternativeFunctionsUnitTest.inc: 10 phpcs:set directive(s), honoured by region |
| `WordPress.WP.Capabilities` | 5 | 48 | 37 | 11 | 1 | 77% | CapabilitiesUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.WP.CapitalPDangit` | 2 | 32 | 5 | 27 | 0 | 16% |  |
| `WordPress.WP.ClassNameCase` | 1 | 18 | 18 | 0 | 0 | 100% |  |
| `WordPress.WP.CronInterval` | 1 | 35 | 35 | 0 | 1 | 100% | CronIntervalUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.DeprecatedClasses` | 2 | 22 | 20 | 2 | 0 | 91% |  |
| `WordPress.WP.DeprecatedFunctions` | 2 | 388 | 381 | 7 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameters` | 1 | 64 | 63 | 1 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameterValues` | 2 | 28 | 28 | 0 | 1 | 100% |  |
| `WordPress.WP.DiscouragedConstants` | 1 | 20 | 20 | 0 | 1 | 100% |  |
| `WordPress.WP.DiscouragedFunctions` | 2 | 8 | 5 | 3 | 3 | 63% | DiscouragedFunctionsUnitTest.1.inc: no setting for phpcs:set exclude |
| `WordPress.WP.EnqueuedResourceParameters` | 2 | 30 | 29 | 1 | 0 | 97% |  |
| `WordPress.WP.EnqueuedResources` | 2 | 28 | 28 | 0 | 0 | 100% |  |
| `WordPress.WP.GetMetaSingle` | 1 | 12 | 12 | 0 | 1 | 100% |  |
| `WordPress.WP.GlobalVariablesOverride` | 8 | 43 | 37 | 6 | 17 | 86% | GlobalVariablesOverrideUnitTest.1.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.3.inc: no setting for phpcs:set treat_files_as_scoped; GlobalVariablesOverrideUnitTest.4.inc: no setting for phpcs:set custom_test_classes; GlobalVariablesOverrideUnitTest.6.inc: no setting for phpcs:set treat_files_as_scoped |
| `WordPress.WP.I18n` | 3 | 149 | 120 | 29 | 1 | 81% | I18nUnitTest.1.inc: 8 phpcs:set directive(s), honoured by region; I18nUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.PostsPerPage` | 1 | 26 | 26 | 0 | 1 | 100% | PostsPerPageUnitTest.inc: 3 phpcs:set directive(s), honoured by region; PostsPerPageUnitTest.inc: no setting for phpcs:set exclude |
| **Total** | **117** | **2132** | **1756** | **376** | **222** | **82%** | |

After wave 1 (posts-per-page, cron-interval, wp-date-time), 2026-09-30:

| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |
|:---|---:|---:|---:|---:|---:|---:|:---|
| `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | 1 | 11 | 11 | 0 | 1 | 100% |  |
| `WordPress.CodeAnalysis.EscapedNotTranslated` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DateTime.CurrentTimeTimestamp` | 1 | 10 | 10 | 0 | 1 | 100% |  |
| `WordPress.DateTime.RestrictedFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DB.DirectDatabaseQuery` | 2 | 70 | 68 | 2 | 7 | 97% |  |
| `WordPress.DB.PreparedSQL` | 3 | 33 | 28 | 5 | 11 | 85% |  |
| `WordPress.DB.PreparedSQLPlaceholders` | 1 | 106 | 53 | 53 | 0 | 50% | PreparedSQLPlaceholdersUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.DB.RestrictedClasses` | 2 | 37 | 35 | 2 | 5 | 95% | RestrictedClassesUnitTest.2.inc skipped: test-only sniff groups; RestrictedClassesUnitTest.3.inc skipped: test-only sniff groups |
| `WordPress.DB.RestrictedFunctions` | 1 | 42 | 41 | 1 | 0 | 98% |  |
| `WordPress.DB.SlowDBQuery` | 1 | 8 | 4 | 4 | 0 | 50% |  |
| `WordPress.Files.FileName` | 1 | 1 | 1 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.PrefixAllGlobals` | 9 | 99 | 97 | 2 | 19 | 98% | PrefixAllGlobalsUnitTest.1.inc: 15 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.4.inc: 4 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.5.inc: 1 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.7.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.8.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.9.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidFunctionName` | 2 | 29 | 29 | 0 | 3 | 100% |  |
| `WordPress.NamingConventions.ValidHookName` | 3 | 55 | 49 | 6 | 9 | 89% |  |
| `WordPress.NamingConventions.ValidPostTypeSlug` | 2 | 29 | 28 | 1 | 2 | 97% |  |
| `WordPress.NamingConventions.ValidVariableName` | 1 | 70 | 70 | 0 | 5 | 100% |  |
| `WordPress.PHP.DevelopmentFunctions` | 1 | 16 | 16 | 0 | 4 | 100% |  |
| `WordPress.PHP.DiscouragedPHPFunctions` | 1 | 24 | 24 | 0 | 1 | 100% |  |
| `WordPress.PHP.DontExtract` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.IniSet` | 1 | 28 | 28 | 0 | 18 | 100% |  |
| `WordPress.PHP.NoSilencedErrors` | 1 | 29 | 29 | 0 | 14 | 100% |  |
| `WordPress.PHP.PregQuoteDelimiter` | 1 | 5 | 4 | 1 | 2 | 80% |  |
| `WordPress.PHP.RestrictedPHPFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.StrictInArray` | 1 | 15 | 15 | 0 | 2 | 100% |  |
| `WordPress.PHP.TypeCasts` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.PHP.YodaConditions` | 1 | 19 | 19 | 0 | 0 | 100% |  |
| `WordPress.Security.EscapeOutput` | 23 | 176 | 104 | 72 | 84 | 59% | EscapeOutputUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.Security.NonceVerification` | 8 | 66 | 38 | 28 | 30 | 58% | NonceVerificationUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.Security.PluginMenuSlug` | 1 | 5 | 5 | 0 | 1 | 100% |  |
| `WordPress.Security.SafeRedirect` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.Security.ValidatedSanitizedInput` | 5 | 106 | 40 | 66 | 14 | 38% | ValidatedSanitizedInputUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.WP.AlternativeFunctions` | 1 | 62 | 20 | 42 | 14 | 32% | AlternativeFunctionsUnitTest.inc: 10 phpcs:set directive(s), honoured by region |
| `WordPress.WP.Capabilities` | 5 | 48 | 37 | 11 | 3 | 77% | CapabilitiesUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.WP.CapitalPDangit` | 2 | 32 | 13 | 19 | 7 | 41% |  |
| `WordPress.WP.ClassNameCase` | 1 | 18 | 7 | 11 | 0 | 39% |  |
| `WordPress.WP.CronInterval` | 1 | 35 | 35 | 0 | 1 | 100% | CronIntervalUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.DeprecatedClasses` | 2 | 22 | 20 | 2 | 0 | 91% |  |
| `WordPress.WP.DeprecatedFunctions` | 2 | 388 | 381 | 7 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameters` | 1 | 64 | 63 | 1 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameterValues` | 2 | 28 | 28 | 0 | 2 | 100% |  |
| `WordPress.WP.DiscouragedConstants` | 1 | 20 | 20 | 0 | 2 | 100% |  |
| `WordPress.WP.DiscouragedFunctions` | 2 | 8 | 5 | 3 | 4 | 63% |  |
| `WordPress.WP.EnqueuedResourceParameters` | 2 | 30 | 13 | 17 | 1 | 43% |  |
| `WordPress.WP.EnqueuedResources` | 2 | 28 | 28 | 0 | 1 | 100% |  |
| `WordPress.WP.GetMetaSingle` | 1 | 12 | 12 | 0 | 2 | 100% |  |
| `WordPress.WP.GlobalVariablesOverride` | 8 | 43 | 37 | 6 | 20 | 86% |  |
| `WordPress.WP.I18n` | 3 | 149 | 120 | 29 | 1 | 81% | I18nUnitTest.1.inc: 8 phpcs:set directive(s), honoured by region; I18nUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.PostsPerPage` | 1 | 26 | 26 | 0 | 1 | 100% | PostsPerPageUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| **Total** | **117** | **2132** | **1741** | **391** | **299** | **82%** | |

Baseline, 2026-09-30, before any parity fixes (`main` at 997e2ac):

| WPCS sniff | Files | Expected lines | Matched | Missed | Extra | Recall | Notes |
|:---|---:|---:|---:|---:|---:|---:|:---|
| `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | 1 | 11 | 11 | 0 | 1 | 100% |  |
| `WordPress.CodeAnalysis.EscapedNotTranslated` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DateTime.CurrentTimeTimestamp` | 1 | 10 | 4 | 6 | 0 | 40% |  |
| `WordPress.DateTime.RestrictedFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.DB.DirectDatabaseQuery` | 2 | 70 | 67 | 3 | 7 | 96% |  |
| `WordPress.DB.PreparedSQL` | 3 | 33 | 22 | 11 | 17 | 67% |  |
| `WordPress.DB.PreparedSQLPlaceholders` | 1 | 106 | 50 | 56 | 1 | 47% | PreparedSQLPlaceholdersUnitTest.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.DB.RestrictedClasses` | 4 | 73 | 37 | 36 | 6 | 51% |  |
| `WordPress.DB.RestrictedFunctions` | 1 | 42 | 41 | 1 | 0 | 98% |  |
| `WordPress.DB.SlowDBQuery` | 1 | 8 | 4 | 4 | 0 | 50% |  |
| `WordPress.Files.FileName` | 1 | 1 | 1 | 0 | 0 | 100% |  |
| `WordPress.NamingConventions.PrefixAllGlobals` | 9 | 99 | 97 | 2 | 20 | 98% | PrefixAllGlobalsUnitTest.1.inc: 15 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.3.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.4.inc: 4 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.5.inc: 1 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.6.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.7.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.8.inc: 2 phpcs:set directive(s), honoured by region; PrefixAllGlobalsUnitTest.9.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.NamingConventions.ValidFunctionName` | 2 | 29 | 29 | 0 | 3 | 100% |  |
| `WordPress.NamingConventions.ValidHookName` | 3 | 55 | 49 | 6 | 9 | 89% |  |
| `WordPress.NamingConventions.ValidPostTypeSlug` | 2 | 29 | 24 | 5 | 6 | 83% |  |
| `WordPress.NamingConventions.ValidVariableName` | 1 | 70 | 70 | 0 | 5 | 100% |  |
| `WordPress.PHP.DevelopmentFunctions` | 1 | 16 | 16 | 0 | 4 | 100% |  |
| `WordPress.PHP.DiscouragedPHPFunctions` | 1 | 24 | 24 | 0 | 1 | 100% |  |
| `WordPress.PHP.DontExtract` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.IniSet` | 1 | 28 | 28 | 0 | 18 | 100% |  |
| `WordPress.PHP.NoSilencedErrors` | 1 | 29 | 29 | 0 | 14 | 100% |  |
| `WordPress.PHP.PregQuoteDelimiter` | 1 | 5 | 4 | 1 | 2 | 80% |  |
| `WordPress.PHP.RestrictedPHPFunctions` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.PHP.StrictInArray` | 1 | 15 | 15 | 0 | 2 | 100% |  |
| `WordPress.PHP.TypeCasts` | 1 | 10 | 10 | 0 | 0 | 100% |  |
| `WordPress.PHP.YodaConditions` | 1 | 19 | 19 | 0 | 0 | 100% |  |
| `WordPress.Security.EscapeOutput` | 23 | 176 | 88 | 88 | 91 | 50% | EscapeOutputUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.Security.NonceVerification` | 8 | 66 | 38 | 28 | 30 | 58% | NonceVerificationUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.Security.PluginMenuSlug` | 1 | 5 | 5 | 0 | 1 | 100% |  |
| `WordPress.Security.SafeRedirect` | 1 | 4 | 4 | 0 | 1 | 100% |  |
| `WordPress.Security.ValidatedSanitizedInput` | 5 | 106 | 40 | 66 | 14 | 38% | ValidatedSanitizedInputUnitTest.1.inc: 5 phpcs:set directive(s), honoured by region |
| `WordPress.WP.AlternativeFunctions` | 1 | 62 | 20 | 42 | 14 | 32% | AlternativeFunctionsUnitTest.inc: 10 phpcs:set directive(s), honoured by region |
| `WordPress.WP.Capabilities` | 5 | 48 | 37 | 11 | 3 | 77% | CapabilitiesUnitTest.1.inc: 4 phpcs:set directive(s), honoured by region |
| `WordPress.WP.CapitalPDangit` | 2 | 32 | 4 | 28 | 10 | 13% |  |
| `WordPress.WP.ClassNameCase` | 1 | 18 | 7 | 11 | 0 | 39% |  |
| `WordPress.WP.CronInterval` | 1 | 35 | 1 | 34 | 8 | 3% | CronIntervalUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| `WordPress.WP.DeprecatedClasses` | 2 | 22 | 20 | 2 | 0 | 91% |  |
| `WordPress.WP.DeprecatedFunctions` | 2 | 388 | 381 | 7 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameters` | 1 | 64 | 63 | 1 | 1 | 98% |  |
| `WordPress.WP.DeprecatedParameterValues` | 2 | 28 | 28 | 0 | 2 | 100% |  |
| `WordPress.WP.DiscouragedConstants` | 1 | 20 | 20 | 0 | 2 | 100% |  |
| `WordPress.WP.DiscouragedFunctions` | 2 | 8 | 5 | 3 | 4 | 63% |  |
| `WordPress.WP.EnqueuedResourceParameters` | 2 | 30 | 13 | 17 | 1 | 43% |  |
| `WordPress.WP.EnqueuedResources` | 2 | 28 | 28 | 0 | 1 | 100% |  |
| `WordPress.WP.GetMetaSingle` | 1 | 12 | 12 | 0 | 2 | 100% |  |
| `WordPress.WP.GlobalVariablesOverride` | 8 | 43 | 37 | 6 | 20 | 86% |  |
| `WordPress.WP.I18n` | 3 | 149 | 120 | 29 | 1 | 81% | I18nUnitTest.1.inc: 8 phpcs:set directive(s), honoured by region; I18nUnitTest.2.inc: 2 phpcs:set directive(s), honoured by region |
| `WordPress.WP.PostsPerPage` | 1 | 26 | 6 | 20 | 1 | 23% | PostsPerPageUnitTest.inc: 3 phpcs:set directive(s), honoured by region |
| **Total** | **119** | **2168** | **1644** | **524** | **328** | **76%** | |
