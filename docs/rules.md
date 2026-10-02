# Rules

| Rule | Ports | Checks |
|:---|:---|:---|
| `wordpress/assignment-in-ternary-condition` | `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | a variable, array element or property assignment inside a parenthesized ternary condition |
| `wordpress/capabilities` | `WordPress.WP.Capabilities` | roles, deprecated capabilities and unknown capabilities passed to `current_user_can()`, `add_menu_page()` and the other capability checks; accepts `custom-capabilities` |
| `wordpress/capital-p-dangit` | `WordPress.WP.CapitalPDangit` | "Wordpress"/"wordpress"/"word press" misspellings in strings, inline HTML, comments, class-like and namespace names (not in URLs, paths, arrays or constant declarations) |
| `wordpress/class-name-case` | `WordPress.WP.ClassNameCase` | a WordPress core, default-theme or bundled-library (getID3, PHPMailer, Requests, SimplePie, Avifinfo, AI Client) class referenced with the wrong case (instantiation, static call, class constant, `extends`, `implements`) |
| `wordpress/cron-interval` | `WordPress.WP.CronInterval` | `cron_schedules` intervals under `min-cron-interval` (default 900 seconds); closures, arrow functions, and callbacks naming a function or method declared in the same file (string, array, or first-class callable) are inspected, and an unresolvable callback or interval gets a `ChangeDetected` warning |
| `wordpress/db-restricted-classes` | `WordPress.DB.RestrictedClasses` | `mysqli`, `PDO` and `PDOStatement` usage |
| `wordpress/db-restricted-functions` | `WordPress.DB.RestrictedFunctions` | raw `mysql`/`mysqli`/`mysqlnd`/`maxdb` extension function calls |
| `wordpress/discouraged-constants` | `WordPress.WP.DiscouragedConstants` | usage and (re-)declaration of discouraged WordPress constants such as `STYLESHEETPATH` or `PLUGINDIR` |
| `wordpress/discouraged-wp-functions` | `WordPress.WP.DiscouragedFunctions`, `WordPress.PHP.DiscouragedPHPFunctions`, `WordPress.PHP.DevelopmentFunctions` | `query_posts()`, `wp_reset_query()`, serialization, obfuscation, system calls, debug output |
| `wordpress/dont-extract` | `WordPress.PHP.DontExtract` | `extract()` |
| `wordpress/enqueued-resource-parameters` | `WordPress.WP.EnqueuedResourceParameters` | missing, `null`, or falsy `$ver` and missing `$in_footer` on enqueue and register calls |
| `wordpress/enqueued-resources` | `WordPress.WP.EnqueuedResources` | hardcoded `<script src>` and `<link rel="stylesheet">` tags, in PHP strings or inline HTML |
| `wordpress/escaped-not-translated` | `WordPress.CodeAnalysis.EscapedNotTranslated` | `esc_html()`/`esc_attr()` called with more than one argument, which likely should be `esc_html__()`/`esc_attr__()` |
| `wordpress/file-name` | `WordPress.Files.FileName` | file names not lowercase and hyphenated, a class file missing its `class-` prefix, a templated `wp-includes` file missing its `-template` suffix |
| `wordpress/get-meta-single` | `WordPress.WP.GetMetaSingle` | `get_*meta()`/`get_metadata*()` calls that pass the key parameter without also passing `$single` |
| `wordpress/global-variables-override` | `WordPress.WP.GlobalVariablesOverride` | assignments, foreach bindings, destructuring and `$GLOBALS[...]` writes (including array-element writes) to WordPress's protected globals (243 names), skipping unit-test classes |
| `wordpress/parentheses-spacing` | the single-line spacing part of `PEAR.Functions.FunctionCallSignature`, `Squiz.Functions.FunctionDeclarationArgumentSpacing`, `WordPress.WhiteSpace.ControlStructureSpacing`, `NormalizedArrays.Arrays.ArrayBraceSpacing`, `WordPress.Arrays.ArrayKeySpacingRestrictions` | one space inside call, declaration, control-structure and array parentheses and brackets (`foo( $a )`, `if ( $x )`, `array( 1 )`, `$a[ $i ]` but `$a['key']`); off by default, see [Formatting](formatting.md) |
| `wordpress/plugin-menu-slug` | `WordPress.Security.PluginMenuSlug` | `__FILE__` passed as the slug or parent-slug argument of `add_menu_page()` and the other admin menu-registration functions |
| `wordpress/posts-per-page` | `WordPress.WP.PostsPerPage` | `posts_per_page`/`numberposts` over `max-posts-per-page` (default 100; `-1` and `nopaging` are not flagged, as in WPCS) in any array literal, `$args['key'] = ...`/`??=` assignment, or `posts_per_page=999`-style query string (it does not follow `$args` variables into `WP_Query`) |
| `wordpress/prefix-all-globals` | `WordPress.NamingConventions.PrefixAllGlobals` | unprefixed global functions, classes, constants and hook names; inert until `prefixes` is configured |
| `wordpress/prepared-sql-placeholders` | `WordPress.DB.PreparedSQLPlaceholders` | quoted, unsupported or unescaped placeholders, SQL wildcards in `LIKE` operands, dynamic `IN ()` lists and count mismatches in `$wpdb->prepare()` |
| `wordpress/prepared-sql-unquoted-complex-placeholder` | `WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder` | unquoted complex placeholders (`%1$s`, `%05s`, `%'.10s`) in `$wpdb->prepare()` queries |
| `wordpress/restricted-php-functions` | `WordPress.PHP.RestrictedPHPFunctions` | `create_function()` |
| `wordpress/safe-redirect` | `WordPress.Security.SafeRedirect` | `wp_redirect()` instead of `wp_safe_redirect()` |
| `wordpress/slow-db-query` | `WordPress.DB.SlowDBQuery` | `meta_query`, `tax_query`, `meta_key`, `meta_value` as array-literal keys (it does not follow `$args` variables into `WP_Query`), `$args['meta_key'] = ...` assignments, and `'meta_key=...'` query strings |
| `wordpress/strict-in-array` | `WordPress.PHP.StrictInArray` | `in_array()`, `array_search()` and `array_keys()` called without `true` as the `$strict` argument |
| `wordpress/type-casts` | `WordPress.PHP.TypeCasts` | `(double)`/`(real)` normalized to `(float)`, `(unset)` forbidden, `(binary)` and binary string literals discouraged |
| `wordpress/valid-function-name` | `WordPress.NamingConventions.ValidFunctionName` | function and method names not in snake_case, and double-underscore names that are not PHP magic methods |
| `wordpress/valid-hook-name` | `WordPress.NamingConventions.ValidHookName` | hook names with uppercase letters or separators other than `_` and `additional-word-delimiters` |
| `wordpress/valid-post-type-slug` | `WordPress.NamingConventions.ValidPostTypeSlug` | invalid characters, reserved names, a reserved prefix, or a slug over 20 characters in `register_post_type()` |
| `wordpress/valid-variable-name` | `WordPress.NamingConventions.ValidVariableName` | variables, properties and object property accesses not in snake_case, including interpolated variables |
| `wordpress/wp-date-time` | `WordPress.DateTime.RestrictedFunctions`, `WordPress.DateTime.CurrentTimeTimestamp` | `date()`, `date_default_timezone_set()`, `current_time('timestamp')` |
| `wordpress/wp-deprecated-classes` | `WordPress.WP.DeprecatedClasses` | deprecated core classes; a deprecation newer than `minimum-wp-version` is reported with a note (WPCS lowers it to a warning) |
| `wordpress/wp-deprecated-functions` | `WordPress.WP.DeprecatedFunctions` | 386 deprecated core functions with their replacements; a deprecation newer than `minimum-wp-version` is reported with a note (WPCS lowers it to a warning) |
| `wordpress/wp-deprecated-parameter-values` | `WordPress.WP.DeprecatedParameterValues` | calls passing a deprecated value for a still-valid parameter (e.g. `bloginfo('home')`); newer than `minimum-wp-version` is reported with a note |
| `wordpress/wp-deprecated-parameters` | `WordPress.WP.DeprecatedParameters` | calls passing a non-default value for a now-ignored deprecated parameter; newer than `minimum-wp-version` is reported with a note |
| `wordpress/wp-i18n` | `WordPress.WP.I18n` | wrong, missing or empty text domains, missing or extra arguments, non-literal strings, placeholder-only or HTML-wrapped strings, placeholder mismatches in `_n()`, unordered placeholders, missing `translators:` comments, `_()` and the low-level `translate()` functions |
| `wordpress/yoda-conditions` | `WordPress.PHP.YodaConditions` | a comparison with a variable, array element or property on the left and a literal or constant on the right |

`WordPress-Core` and `WordPress-Extra` also pull in generic sniffs that catch real bugs and that
nothing in Mago covers. These are ported as `generic/*` rules, matched against phpcs's own tests
(`bench/results/wpcs-parity.md`):

| Rule | Ports | Checks |
|:---|:---|:---|
| `generic/byte-order-mark` | `Generic.Files.ByteOrderMark` | a UTF-8 or UTF-16 byte order mark at the start of the file |
| `generic/disallow-alternative-php-tags` | `Generic.PHP.DisallowAlternativePHPTags` | `<%`, `<%=` and `<script language="php">` in inline HTML (removed in PHP 7, so the code inside is output as HTML) |
| `generic/disallow-size-functions-in-loops` | `Squiz.PHP.DisallowSizeFunctionsInLoops` | `count()`, `sizeof()` or `strlen()` in a `while`/do-`while` condition or a `for` loop's test part |
| `generic/for-loop-with-test-function-call` | `Generic.CodeAnalysis.ForLoopWithTestFunctionCall` | any function or method call in a `for` loop's test part |
| `generic/foreach-unique-assignment` | `Universal.CodeAnalysis.ForeachUniqueAssignment` | `foreach ($a as $k => $k)`, or a key reused as a destructuring target (the key wins, so the value is lost); no fix, since phpcbf's changes which value the variable gets |
| `generic/git-merge-conflict` | `Generic.VersionControl.GitMergeConflict` | merge conflict markers at the start of a line, including in inline HTML, comments and heredocs, where the file still parses |
| `generic/jumbled-incrementer` | `Generic.CodeAnalysis.JumbledIncrementer` | a nested `for` loop incrementing the outer loop's variable |
| `generic/require-explicit-boolean-operator-precedence` | `Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence` | `&&`, `\|\|`, `and`, `or`, `xor` mixed without parentheses, as in `$a && $b \|\| $c` |

The generic rules honour phpcs comments and `exclude-patterns` under their own sniff codes (for
example `"Generic": ["*"]` turns off the `Generic.*` ones); the `WordPress-Core`-only ruleset of the
phpcs.xml fallback leaves out the five that only `WordPress-Extra` includes.

45 of the 46 rules are enabled by default when the extension is installed (`parentheses-spacing`
runs only with `--only`, see [Formatting](formatting.md)), and report at `Warning` except
`wordpress/capital-p-dangit` (`Note`) and seventeen rules that report at `Error`:
`db-restricted-classes`, `db-restricted-functions`, `dont-extract`, `file-name`,
`global-variables-override`, `parentheses-spacing`, `prepared-sql-placeholders`, `restricted-php-functions`,
`type-casts`, `valid-function-name`, `valid-post-type-slug`, `valid-variable-name`,
`generic/byte-order-mark`, `generic/disallow-size-functions-in-loops`,
`generic/foreach-unique-assignment`, `generic/git-merge-conflict` and
`generic/require-explicit-boolean-operator-precedence`. Function,
class, constant and capability lists come from WPCS 3.4.1 (`src/Internal/WordPress/Lists.php`,
generated by `php bin/generate-lists.php /path/to/WordPress-Coding-Standards`).

`mago lint --fix` makes the same fixes phpcbf makes for the non-formatting sniffs, except where
phpcbf's fix would change behaviour or break the file (a misspelling inside an interpolated
`{$...}`, an unqualified `time()` in a namespace, a literal `%%` in a translatable string):

| Rule | Fix | Applied by |
|:---|:---|:---|
| `type-casts` | `(double)`/`(real)` → `(float)` | `--fix` |
| `parentheses-spacing` | one space inside parentheses and brackets, none around a literal array key | `--fix --only wordpress/parentheses-spacing` |
| `capital-p-dangit` | misspelling → `WordPress` in comments | `--fix` |
| `capital-p-dangit` | misspelling → `WordPress` in strings and inline HTML (changes output) | `--fix --potentially-unsafe` |
| `wp-date-time` | `current_time( 'timestamp' \| 'U', true )` → `time()`, unless the call holds a comment | `--fix` |
| `wp-i18n` | superfluous `'default'` text domain removed, unless that would drop a comment | `--fix` |
| `wp-i18n` | unordered placeholders numbered, `%s %s` → `%1$s %2$s` (changes the msgid, so existing translations stop matching) | `--fix --potentially-unsafe` |
