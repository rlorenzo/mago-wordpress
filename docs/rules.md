# Rules

| Rule | Level | Ports | Checks |
|:---|:---|:---|:---|
| `wordpress/alternative-functions` | Warning | `WordPress.WP.AlternativeFunctions` | PHP functions with a WordPress alternative (cURL, `parse_url()`, `json_encode()`, `file_get_contents()` on a remote URL, direct filesystem calls, `strip_tags()`, `rand()`), once `minimum-wp-version` has the alternative; replaces Mago's core `use-wp-functions` |
| `wordpress/assignment-in-ternary-condition` | Warning | `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | a variable, array element or property assignment inside a parenthesized ternary condition |
| `wordpress/capabilities` | Warning | `WordPress.WP.Capabilities` | roles, deprecated capabilities and unknown capabilities passed to `current_user_can()`, `add_menu_page()` and the other capability checks; accepts `custom-capabilities` |
| `wordpress/capital-p-dangit` | Note | `WordPress.WP.CapitalPDangit` | "Wordpress"/"wordpress"/"word press" misspellings in strings, inline HTML, comments, class-like and namespace names (not in URLs, paths, arrays or constant declarations) |
| `wordpress/class-name-case` | Warning | `WordPress.WP.ClassNameCase` | a WordPress core, default-theme or bundled-library (getID3, PHPMailer, Requests, SimplePie, Avifinfo, AI Client) class referenced with the wrong case (instantiation, static call, class constant, `extends`, `implements`) |
| `wordpress/cron-interval` | Warning | `WordPress.WP.CronInterval` | `cron_schedules` intervals under `min-cron-interval` (default 900 seconds); closures, arrow functions, and callbacks naming a function or method declared in the same file (string, array, or first-class callable) are inspected, and an unresolvable callback or interval gets a `ChangeDetected` warning |
| `wordpress/db-restricted-classes` | Error | `WordPress.DB.RestrictedClasses` | `mysqli`, `PDO` and `PDOStatement` usage |
| `wordpress/db-restricted-functions` | Error | `WordPress.DB.RestrictedFunctions` | raw `mysql`/`mysqli`/`mysqlnd`/`maxdb` extension function calls |
| `wordpress/direct-database-query` | Warning | `WordPress.DB.DirectDatabaseQuery` | `$wpdb` query calls (`DirectQuery`), the ones without `wp_cache_get()`/`wp_cache_set()` or a cache delete in the same function (`NoCaching`; extra cache functions in `custom-cache-get-functions`, `custom-cache-set-functions`, `custom-cache-delete-functions`), and `ALTER`/`CREATE`/`DROP` queries (`SchemaChange`); replaces Mago's core `no-direct-db-query` and `no-db-schema-change` |
| `wordpress/discouraged-constants` | Warning | `WordPress.WP.DiscouragedConstants` | usage and (re-)declaration of discouraged WordPress constants such as `STYLESHEETPATH` or `PLUGINDIR` |
| `wordpress/discouraged-wp-functions` | Warning | `WordPress.WP.DiscouragedFunctions`, `WordPress.PHP.DiscouragedPHPFunctions`, `WordPress.PHP.DevelopmentFunctions` | `query_posts()`, `wp_reset_query()`, serialization, obfuscation, system calls, debug output |
| `wordpress/dont-extract` | Error | `WordPress.PHP.DontExtract` | `extract()` |
| `wordpress/enqueued-resource-parameters` | Warning | `WordPress.WP.EnqueuedResourceParameters` | missing, `null`, or falsy `$ver` and missing `$in_footer` on enqueue and register calls |
| `wordpress/enqueued-resources` | Warning | `WordPress.WP.EnqueuedResources` | hardcoded `<script src>` and `<link rel="stylesheet">` tags, in PHP strings or inline HTML |
| `wordpress/escape-output` | Error | `WordPress.Security.EscapeOutput` | unescaped output from `echo`, `print`, `<?=`, `exit`/`die`, uncaught `throw` and the printing functions (`_e()`, `printf()`, `wp_die()`, ...); WPCS's escaping and auto-escaped lists, plus `custom-escaping-functions`, `custom-auto-escaped-functions` and `custom-printing-functions`. Replaces Mago's `no-unescaped-output`, which the shipped config turns off |
| `wordpress/escaped-not-translated` | Warning | `WordPress.CodeAnalysis.EscapedNotTranslated` | `esc_html()`/`esc_attr()` called with more than one argument, which likely should be `esc_html__()`/`esc_attr__()` |
| `wordpress/file-name` | Error | `WordPress.Files.FileName` | file names not lowercase and hyphenated, a class file missing its `class-` prefix, a templated `wp-includes` file missing its `-template` suffix |
| `wordpress/get-meta-single` | Warning | `WordPress.WP.GetMetaSingle` | `get_*meta()`/`get_metadata*()` calls that pass the key parameter without also passing `$single` |
| `wordpress/global-variables-override` | Error | `WordPress.WP.GlobalVariablesOverride` | assignments, foreach bindings, destructuring and `$GLOBALS[...]` writes (including array-element writes) to WordPress's protected globals (243 names), skipping unit-test classes |
| `wordpress/nonce-verification` | Error | `WordPress.Security.NonceVerification` | `$_POST`/`$_FILES` (`Missing`) and `$_GET`/`$_REQUEST` (`Recommended`, a warning in WPCS) read without `wp_verify_nonce()`, `check_admin_referer()`, `check_ajax_referer()` or a `custom-nonce-verification-functions` entry earlier in the function or file; an `isset()`, comparison or plain sanitizing may come before the check |
| `wordpress/parentheses-spacing` | Error | the single-line spacing part of `PEAR.Functions.FunctionCallSignature`, `Squiz.Functions.FunctionDeclarationArgumentSpacing`, `WordPress.WhiteSpace.ControlStructureSpacing`, `NormalizedArrays.Arrays.ArrayBraceSpacing`, `WordPress.Arrays.ArrayKeySpacingRestrictions` | one space inside call, declaration, control-structure and array parentheses and brackets (`foo( $a )`, `if ( $x )`, `array( 1 )`, `$a[ $i ]` but `$a['key']`); off by default, see [Formatting](formatting.md) |
| `wordpress/plugin-menu-slug` | Warning | `WordPress.Security.PluginMenuSlug` | `__FILE__` passed as the slug or parent-slug argument of `add_menu_page()` and the other admin menu-registration functions |
| `wordpress/posts-per-page` | Warning | `WordPress.WP.PostsPerPage` | `posts_per_page`/`numberposts` over `max-posts-per-page` (default 100; `-1` and `nopaging` are not flagged, as in WPCS) in any array literal, `$args['key'] = ...`/`??=` assignment, or `posts_per_page=999`-style query string (it does not follow `$args` variables into `WP_Query`) |
| `wordpress/preg-quote-delimiter` | Warning | `WordPress.PHP.PregQuoteDelimiter` | `preg_quote()` called with arguments but no `$delimiter`; replaces Mago's core `require-preg-quote-delimiter` |
| `wordpress/prefix-all-globals` | Warning | `WordPress.NamingConventions.PrefixAllGlobals` | unprefixed global functions, classes, constants and hook names; inert until `prefixes` is configured |
| `wordpress/prepared-sql` | Error | `WordPress.DB.PreparedSQL` | variables, function calls and interpolated variables in the query passed to `$wpdb->query()`, `get_var()`, `get_col()`, `get_row()`, `get_results()` and `prepare()`, unless escaped (`esc_sql()`, `absint()`, `intval()`, `(int)`) or `$wpdb` itself; replaces Mago's core `prepared-sql` |
| `wordpress/prepared-sql-placeholders` | Error | `WordPress.DB.PreparedSQLPlaceholders` | quoted, unsupported or unescaped placeholders, SQL wildcards in `LIKE` operands, dynamic `IN ()` lists and count mismatches in `$wpdb->prepare()` |
| `wordpress/prepared-sql-unquoted-complex-placeholder` | Warning | `WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder` | unquoted complex placeholders (`%1$s`, `%05s`, `%'.10s`) in `$wpdb->prepare()` queries |
| `wordpress/restricted-php-functions` | Error | `WordPress.PHP.RestrictedPHPFunctions` | `create_function()` |
| `wordpress/safe-redirect` | Warning | `WordPress.Security.SafeRedirect` | `wp_redirect()` instead of `wp_safe_redirect()` |
| `wordpress/slow-db-query` | Warning | `WordPress.DB.SlowDBQuery` | `meta_query`, `tax_query`, `meta_key`, `meta_value` as array-literal keys (it does not follow `$args` variables into `WP_Query`), `$args['meta_key'] = ...` assignments, and `'meta_key=...'` query strings |
| `wordpress/strict-in-array` | Warning | `WordPress.PHP.StrictInArray` | `in_array()`, `array_search()` and `array_keys()` called without `true` as the `$strict` argument |
| `wordpress/type-casts` | Error | `WordPress.PHP.TypeCasts` | `(double)`/`(real)` normalized to `(float)`, `(unset)` forbidden, `(binary)` and binary string literals discouraged |
| `wordpress/valid-function-name` | Error | `WordPress.NamingConventions.ValidFunctionName` | function and method names not in snake_case, and double-underscore names that are not PHP magic methods |
| `wordpress/valid-hook-name` | Warning | `WordPress.NamingConventions.ValidHookName` | hook names with uppercase letters or separators other than `_` and `additional-word-delimiters` |
| `wordpress/valid-post-type-slug` | Error | `WordPress.NamingConventions.ValidPostTypeSlug` | invalid characters, reserved names, a reserved prefix, or a slug over 20 characters in `register_post_type()` |
| `wordpress/valid-variable-name` | Error | `WordPress.NamingConventions.ValidVariableName` | variables, properties and object property accesses not in snake_case, including interpolated variables |
| `wordpress/validated-sanitized-input` | Error | `WordPress.Security.ValidatedSanitizedInput` | superglobal array elements read without an `isset()`/`empty()`/`array_key_exists()`/`??` check, without `wp_unslash()`, or without a sanitizing function (`custom-sanitizing-functions`, `custom-unslashing-sanitizing-functions`), and superglobals interpolated into strings |
| `wordpress/wp-date-time` | Warning | `WordPress.DateTime.RestrictedFunctions`, `WordPress.DateTime.CurrentTimeTimestamp` | `date()`, `date_default_timezone_set()`, `current_time('timestamp')` |
| `wordpress/wp-deprecated-classes` | Warning | `WordPress.WP.DeprecatedClasses` | deprecated core classes; a deprecation newer than `minimum-wp-version` is reported with a note (WPCS lowers it to a warning) |
| `wordpress/wp-deprecated-functions` | Warning | `WordPress.WP.DeprecatedFunctions` | 386 deprecated core functions with their replacements; a deprecation newer than `minimum-wp-version` is reported with a note (WPCS lowers it to a warning) |
| `wordpress/wp-deprecated-parameter-values` | Warning | `WordPress.WP.DeprecatedParameterValues` | calls passing a deprecated value for a still-valid parameter (e.g. `bloginfo('home')`); newer than `minimum-wp-version` is reported with a note |
| `wordpress/wp-deprecated-parameters` | Warning | `WordPress.WP.DeprecatedParameters` | calls passing a non-default value for a now-ignored deprecated parameter; newer than `minimum-wp-version` is reported with a note |
| `wordpress/wp-i18n` | Warning | `WordPress.WP.I18n` | wrong, missing or empty text domains, missing or extra arguments, non-literal strings, placeholder-only or HTML-wrapped strings, placeholder mismatches in `_n()`, unordered placeholders, missing `translators:` comments, `_()` and the low-level `translate()` functions |
| `wordpress/yoda-conditions` | Warning | `WordPress.PHP.YodaConditions` | a comparison with a variable, array element or property on the left and a literal or constant on the right |

`WordPress-Core` and `WordPress-Extra` also pull in generic sniffs that catch real bugs and that
Mago doesn't cover. They are ported as `generic/*` rules and matched against phpcs's own tests
(`bench/results/wpcs-parity.md`):

| Rule | Level | Ports | Checks |
|:---|:---|:---|:---|
| `generic/byte-order-mark` | Error | `Generic.Files.ByteOrderMark` | a UTF-8 or UTF-16 byte order mark at the start of the file |
| `generic/disallow-alternative-php-tags` | Warning | `Generic.PHP.DisallowAlternativePHPTags` | `<%`, `<%=` and `<script language="php">` in inline HTML (removed in PHP 7, so the code inside is output as HTML) |
| `generic/disallow-size-functions-in-loops` | Error | `Squiz.PHP.DisallowSizeFunctionsInLoops` | `count()`, `sizeof()` or `strlen()` in a `while`/do-`while` condition or a `for` loop's test part |
| `generic/for-loop-with-test-function-call` | Warning | `Generic.CodeAnalysis.ForLoopWithTestFunctionCall` | any function or method call in a `for` loop's test part |
| `generic/foreach-unique-assignment` | Error | `Universal.CodeAnalysis.ForeachUniqueAssignment` | `foreach ($a as $k => $k)`, or a key reused as a destructuring target (the key wins, so the value is lost); no fix, since phpcbf's changes which value the variable gets |
| `generic/git-merge-conflict` | Error | `Generic.VersionControl.GitMergeConflict` | merge conflict markers at the start of a line, including in inline HTML, comments and heredocs, where the file still parses |
| `generic/jumbled-incrementer` | Warning | `Generic.CodeAnalysis.JumbledIncrementer` | a nested `for` loop incrementing the outer loop's variable |
| `generic/require-explicit-boolean-operator-precedence` | Error | `Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence` | `&&`, `\|\|`, `and`, `or`, `xor` mixed without parentheses, as in `$a && $b \|\| $c` |

The generic rules honour phpcs comments and `exclude-patterns` under their own sniff codes. For
example, `"Generic": ["*"]` turns off every `Generic.*` rule. The `WordPress-Core`-only ruleset of the
phpcs.xml fallback leaves out the five rules that only `WordPress-Extra` includes.

52 of the 53 rules are on by default. `parentheses-spacing` runs only with `--only`; see
[Formatting](formatting.md). Levels are in the tables above.

Function, class, constant and capability lists come from WPCS 3.4.1 (`src/Internal/WordPress/Lists.php`,
generated by `php bin/generate-lists.php /path/to/WordPress-Coding-Standards`).

`mago lint --fix` makes the same fixes phpcbf makes for the non-formatting sniffs. It skips cases
where phpcbf's fix would change behaviour or break the file: a misspelling inside an interpolated
`{$...}`, an unqualified `time()` in a namespace, a literal `%%` in a translatable string.

| Rule | Fix | Applied by |
|:---|:---|:---|
| `type-casts` | `(double)`/`(real)` → `(float)` | `--fix` |
| `parentheses-spacing` | one space inside parentheses and brackets, none around a literal array key | `--fix --only wordpress/parentheses-spacing` |
| `capital-p-dangit` | misspelling → `WordPress` in comments | `--fix` |
| `capital-p-dangit` | misspelling → `WordPress` in strings and inline HTML (changes output) | `--fix --potentially-unsafe` |
| `wp-date-time` | `current_time( 'timestamp' \| 'U', true )` → `time()`, unless the call holds a comment | `--fix` |
| `wp-i18n` | superfluous `'default'` text domain removed, unless that would drop a comment | `--fix` |
| `wp-i18n` | unordered placeholders numbered, `%s %s` → `%1$s %2$s` (changes the msgid, so existing translations stop matching) | `--fix --potentially-unsafe` |
