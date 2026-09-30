# WPCS test-suite parity

`php bench/wpcs-parity.php ~/Projects/wpcs-src` (WPCS 3.4.1) runs every WPCS sniff test file through
the Mago rule that ports the sniff and compares the lines reported with the lines the sniff's own
`getErrorList()`/`getWarningList()` expect. Levels are not compared. `phpcs:set` directives are
honoured per region. `Recall` is matched / expected; `Extra` is lines Mago reports that WPCS does
not expect (some are Mago being right where the sniff is documented as not yet resolving a case).

Rows for `WordPress.Security.*`, `WordPress.DB.DirectDatabaseQuery`, `WordPress.DB.PreparedSQL`,
`WordPress.WP.AlternativeFunctions` and the four `WordPress.PHP.*` rows mapped to Mago core rules
measure Mago's own rules, not this package's.

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
