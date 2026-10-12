# Rules

| Rule | Level | Ports | Checks |
|:---|:---|:---|:---|
| `wordpress/alternative-functions` | Warning | `WordPress.WP.AlternativeFunctions` | PHP functions with a WordPress alternative (cURL, `parse_url()`, `json_encode()`, `file_get_contents()` on a remote URL, direct filesystem calls, `strip_tags()`, `rand()`), once `minimum-wp-version` has the alternative; replaces Mago's core `use-wp-functions` |
| `wordpress/assignment-in-ternary-condition` | Warning | `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | a variable, array element or property assignment inside a parenthesized ternary condition |
| `wordpress/capabilities` | Error | `WordPress.WP.Capabilities` | roles and deprecated capabilities (unknown ones: `capabilities-warning`) passed to `current_user_can()`, `add_menu_page()` and the other capability checks; accepts `custom-capabilities` |
| `wordpress/capabilities-warning` | Warning | `WordPress.WP.Capabilities.Unknown` | the `Unknown` part of `wordpress/capabilities`: a capability that is neither core nor in `custom-capabilities` |
| `wordpress/capital-p-dangit` | Warning | `WordPress.WP.CapitalPDangit` | "Wordpress"/"wordpress"/"word press" misspellings in strings, inline HTML, comments, class-like and namespace names (not in URLs, paths, arrays or constant declarations) |
| `wordpress/class-name-case` | Warning | `WordPress.WP.ClassNameCase` | a WordPress core, default-theme or bundled-library (getID3, PHPMailer, Requests, SimplePie, Avifinfo, AI Client) class referenced with the wrong case (instantiation, static call, class constant, `extends`, `implements`) |
| `wordpress/cron-interval` | Warning | `WordPress.WP.CronInterval` | `cron_schedules` intervals under `min-cron-interval` (default 900 seconds); closures, arrow functions, and callbacks naming a function or method declared in the same file (string, array, or first-class callable) are inspected, and an unresolvable callback or interval gets a `ChangeDetected` warning |
| `wordpress/db-restricted-classes` | Error | `WordPress.DB.RestrictedClasses` | `mysqli`, `PDO` and `PDOStatement` usage |
| `wordpress/db-restricted-functions` | Error | `WordPress.DB.RestrictedFunctions` | raw `mysql`/`mysqli`/`mysqlnd`/`maxdb` extension function calls |
| `wordpress/direct-database-query` | Warning | `WordPress.DB.DirectDatabaseQuery` | `$wpdb` query calls (`DirectQuery`), the ones without `wp_cache_get()`/`wp_cache_set()` or a cache delete in the same function (`NoCaching`; extra cache functions in `custom-cache-get-functions`, `custom-cache-set-functions`, `custom-cache-delete-functions`), and `ALTER`/`CREATE`/`DROP` queries (`SchemaChange`); replaces Mago's core `no-direct-db-query` and `no-db-schema-change` |
| `wordpress/discouraged-constants` | Warning | `WordPress.WP.DiscouragedConstants` | usage and (re-)declaration of discouraged WordPress constants such as `STYLESHEETPATH` or `PLUGINDIR` |
| `wordpress/discouraged-wp-functions` | Warning | `WordPress.WP.DiscouragedFunctions`, `WordPress.PHP.DiscouragedPHPFunctions`, `WordPress.PHP.DevelopmentFunctions` | `query_posts()`, `wp_reset_query()`, serialization, obfuscation, system calls, debug output |
| `wordpress/dont-extract` | Error | `WordPress.PHP.DontExtract` | `extract()` |
| `wordpress/ini-set` | Error | `WordPress.PHP.IniSet` | `ini_set()` and `ini_alter()` of an option WordPress sets another way (`memory_limit`, `display_errors`, `error_reporting`, `short_open_tag` turned off, ...; the `<option>_Disallowed` codes); the options WPCS lets through are skipped |
| `wordpress/ini-set-warning` | Warning | `WordPress.PHP.IniSet.Risky` | the `Risky` part of `wordpress/ini-set`: any other runtime configuration change |
| `wordpress/no-silenced-errors` | Warning | `WordPress.PHP.NoSilencedErrors` | the `@` operator; under `WordPress-Core` not before the PHP functions WPCS allows (`is_file()`, `fopen()`, ...); `custom-allowed-functions-list` adds names |
| `wordpress/enqueued-resource-parameters` | Warning | `WordPress.WP.EnqueuedResourceParameters` | missing, `null`, or falsy `$ver` and missing `$in_footer` on enqueue and register calls |
| `wordpress/enqueued-resources` | Error | `WordPress.WP.EnqueuedResources` | hardcoded `<script src>` and `<link rel="stylesheet">` tags, in PHP strings or inline HTML |
| `wordpress/escape-output` | Error | `WordPress.Security.EscapeOutput` | unescaped output from `echo`, `print`, `<?=`, `exit`/`die`, uncaught `throw` and the printing functions (`_e()`, `printf()`, `wp_die()`, ...); WPCS's escaping and auto-escaped lists, plus `custom-escaping-functions`, `custom-auto-escaped-functions` and `custom-printing-functions`. Replaces Mago's `no-unescaped-output`, which the shipped config turns off |
| `wordpress/escaped-not-translated` | Warning | `WordPress.CodeAnalysis.EscapedNotTranslated` | `esc_html()`/`esc_attr()` called with more than one argument, which likely should be `esc_html__()`/`esc_attr__()` |
| `wordpress/file-name` | Error | `WordPress.Files.FileName` | file names not lowercase and hyphenated, a class file missing its `class-` prefix, a templated `wp-includes` file missing its `-template` suffix |
| `wordpress/get-meta-single` | Warning | `WordPress.WP.GetMetaSingle` | `get_*meta()`/`get_metadata*()` calls that pass the key parameter without also passing `$single` |
| `wordpress/global-variables-override` | Error | `WordPress.WP.GlobalVariablesOverride` | assignments, foreach bindings, destructuring and `$GLOBALS[...]` writes (including array-element writes) to WordPress's protected globals (243 names), skipping unit-test classes |
| `wordpress/nonce-verification` | Error | `WordPress.Security.NonceVerification` | `$_POST`/`$_FILES` (`Missing`; `$_GET`/`$_REQUEST` are `nonce-verification-warning`) read without `wp_verify_nonce()`, `check_admin_referer()`, `check_ajax_referer()` or a `custom-nonce-verification-functions` entry earlier in the function or file; an `isset()`, comparison or plain sanitizing may come before the check |
| `wordpress/nonce-verification-warning` | Warning | `WordPress.Security.NonceVerification.Recommended` | the `Recommended` part of `wordpress/nonce-verification`: `$_GET`/`$_REQUEST` read without a nonce check |
| `wordpress/array-alignment` | Warning | `WordPress.Arrays.MultipleStatementAlignment` | the `=>` of a multi-line array not aligned to one column up to column 60, or a longer key without a single space before its arrow; one space before each arrow of a single-line array. Off by default; autofix, run after `mago format` (`mago-wordpress format` does) |
| `wordpress/function-call-signature` | Error | the layout part of `PEAR.Functions.FunctionCallSignature` for a call whose parentheses span lines (`ContentAfterOpenBracket`, `MultipleArguments`, `CloseBracketLine`): `add_action( 'init', function () {` becomes one argument per line with `)` on its own line, and the lines of a moved argument go with it. Off by default; autofix, run after `mago format` (`mago-wordpress format` does); the indentation checks stay with `mago format` |
| `wordpress/array-declaration-spacing` | Error | `WordPress.Arrays.ArrayDeclarationSpacing` | an array with several items and explicit keys on one line (`AssociativeArrayFound`), or an item of a multi-line array that shares a line with the one before (`ArrayItemNoNewLine`). Off by default; autofix puts each item on its own line, one tab in from the line the array starts on, as phpcbf does with the indentation sniffs. Run after `mago format` (`mago-wordpress format` does) |
| `wordpress/language-construct-spacing` | Error | `Generic.WhiteSpace.LanguageConstructSpacing` | `echo`, `print`, `return`, `include`, `require`, `new`, `yield`, `throw`, `namespace`, `use` or `goto` not followed by exactly one space (`IncorrectSingle`, `Incorrect`, `IncorrectYieldFrom`), such as the line break `mago format` puts after a long `echo`; a `(` or `;` right after is fine. Off by default; autofix, run after `mago format` (`mago-wordpress format` does) |
| `wordpress/parentheses-spacing` | Error | the single-line spacing part of `PEAR.Functions.FunctionCallSignature`, `Squiz.Functions.FunctionDeclarationArgumentSpacing`, `WordPress.WhiteSpace.ControlStructureSpacing`, `NormalizedArrays.Arrays.ArrayBraceSpacing`, `WordPress.Arrays.ArrayKeySpacingRestrictions` | one space inside call, declaration, control-structure and array parentheses and brackets (`foo( $a )`, `if ( $x )`, `array( 1 )`, `$a[ $i ]` but `$a['key']`) and before an alternative-syntax colon (`if ( $x ) :`); off by default, see [Formatting](formatting.md) |
| `wordpress/plugin-menu-slug` | Warning | `WordPress.Security.PluginMenuSlug` | `__FILE__` passed as the slug or parent-slug argument of `add_menu_page()` and the other admin menu-registration functions |
| `wordpress/posts-per-page` | Warning | `WordPress.WP.PostsPerPage` | `posts_per_page`/`numberposts` over `max-posts-per-page` (default 100; `-1` and `nopaging` are not flagged, as in WPCS) in any array literal, `$args['key'] = ...`/`??=` assignment, or `posts_per_page=999`-style query string (it does not follow `$args` variables into `WP_Query`) |
| `wordpress/preg-quote-delimiter` | Warning | `WordPress.PHP.PregQuoteDelimiter` | `preg_quote()` called with arguments but no `$delimiter`; replaces Mago's core `require-preg-quote-delimiter` |
| `wordpress/prefix-all-globals` | Error | `WordPress.NamingConventions.PrefixAllGlobals` | unprefixed global functions, classes, constants, hook names and file-scope variables (`NonPrefixedVariableFound`), and prefixes shorter than three characters (`ShortPrefixPassed`); inert until `prefixes` is configured. See [Theme templates](#prefix-all-globals-in-theme-templates) |
| `wordpress/prepared-sql` | Error | `WordPress.DB.PreparedSQL` | variables, function calls and interpolated variables in the query passed to `$wpdb->query()`, `get_var()`, `get_col()`, `get_row()`, `get_results()` and `prepare()`, unless escaped (`esc_sql()`, `absint()`, `intval()`, `(int)`) or `$wpdb` itself; replaces Mago's core `prepared-sql` |
| `wordpress/prepared-sql-placeholders` | Error | `WordPress.DB.PreparedSQLPlaceholders` | quoted, unsupported or unescaped placeholders, SQL wildcards in `LIKE` operands, dynamic `IN ()` lists and count mismatches in `$wpdb->prepare()` |
| `wordpress/prepared-sql-placeholders-warning` | Warning | `WordPress.DB.PreparedSQLPlaceholders` (`LikeWithoutWildcards`, `ReplacementsWrongNumber`, `UnfinishedPrepare`, `UnnecessaryPrepare`) | the warning part of `wordpress/prepared-sql-placeholders`: `LIKE` without wildcards, count mismatches, a query with no placeholders or a stray `%`, and a `prepare()` with nothing to prepare |
| `wordpress/prepared-sql-unquoted-complex-placeholder` | Warning | `WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder` | unquoted complex placeholders (`%1$s`, `%05s`, `%'.10s`) in `$wpdb->prepare()` queries |
| `wordpress/restricted-php-functions` | Error | `WordPress.PHP.RestrictedPHPFunctions` | `create_function()` |
| `wordpress/safe-redirect` | Warning | `WordPress.Security.SafeRedirect` | `wp_redirect()` instead of `wp_safe_redirect()` |
| `wordpress/slow-db-query` | Warning | `WordPress.DB.SlowDBQuery` | `meta_query`, `tax_query`, `meta_key`, `meta_value` as array-literal keys (it does not follow `$args` variables into `WP_Query`), `$args['meta_key'] = ...` assignments, and `'meta_key=...'` query strings |
| `wordpress/strict-in-array` | Warning | `WordPress.PHP.StrictInArray` | `in_array()`, `array_search()` and `array_keys()` called without `true` as the `$strict` argument |
| `wordpress/type-casts` | Error | `WordPress.PHP.TypeCasts` | `(double)`/`(real)` normalized to `(float)`, `(unset)` forbidden, `(binary)` and binary string literals discouraged |
| `wordpress/valid-function-name` | Error | `WordPress.NamingConventions.ValidFunctionName` | function and method names not in snake_case, and double-underscore names that are not PHP magic methods |
| `wordpress/valid-hook-name` | Error | `WordPress.NamingConventions.ValidHookName` | hook names with uppercase letters (other separators: `valid-hook-name-warning`) |
| `wordpress/valid-hook-name-warning` | Warning | `WordPress.NamingConventions.ValidHookName.UseUnderscores` | the `UseUnderscores` part of `wordpress/valid-hook-name`: separators other than `_` and `additional-word-delimiters` |
| `wordpress/valid-post-type-slug` | Error | `WordPress.NamingConventions.ValidPostTypeSlug` | invalid characters, reserved names, a reserved prefix, or a slug over 20 characters in `register_post_type()` |
| `wordpress/valid-variable-name` | Error | `WordPress.NamingConventions.ValidVariableName` | variables, properties and object property accesses not in snake_case, including interpolated variables |
| `wordpress/validated-sanitized-input` | Error | `WordPress.Security.ValidatedSanitizedInput` | superglobal array elements read without an `isset()`/`empty()`/`array_key_exists()`/`??` check, without `wp_unslash()`, or without a sanitizing function (`custom-sanitizing-functions`, `custom-unslashing-sanitizing-functions`), and superglobals interpolated into strings |
| `wordpress/wp-date-time` | Error | `WordPress.DateTime.RestrictedFunctions`, `WordPress.DateTime.CurrentTimeTimestamp` | `date()`, `date_default_timezone_set()`, `current_time('timestamp')` |
| `wordpress/wp-deprecated-classes` | Error | `WordPress.WP.DeprecatedClasses` | deprecated core classes; a deprecation newer than `minimum-wp-version` is reported with a note (WPCS lowers it to a warning) |
| `wordpress/wp-deprecated-functions` | Error | `WordPress.WP.DeprecatedFunctions` | 386 deprecated core functions with their replacements; a deprecation newer than `minimum-wp-version` is reported with a note (WPCS lowers it to a warning) |
| `wordpress/wp-deprecated-parameter-values` | Error | `WordPress.WP.DeprecatedParameterValues` | calls passing a deprecated value for a still-valid parameter (e.g. `bloginfo('home')`); newer than `minimum-wp-version` is reported with a note |
| `wordpress/wp-deprecated-parameters` | Error | `WordPress.WP.DeprecatedParameters` | calls passing a non-default value for a now-ignored deprecated parameter; newer than `minimum-wp-version` is reported with a note |
| `wordpress/wp-i18n` | Error | `WordPress.WP.I18n` | wrong, missing or empty text domains, missing or extra arguments, non-literal strings, placeholder-only or HTML-wrapped strings, placeholder mismatches in `_n()`, unordered placeholders, missing `translators:` comments, `_()` and the low-level `translate()` functions |
| `wordpress/yoda-conditions` | Error | `WordPress.PHP.YodaConditions` | a comparison with a variable, array element or property on the left and a literal or constant on the right |

### prefix-all-globals in theme templates

WordPress loads theme templates through `load_template()`, inside a function, so a template's
file-scope variables aren't globals. WPCS doesn't know that and reports them, and so does this
rule. Exclude the template directories for that one message code in `composer.json`:

```json
"exclude-patterns": {
  "WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound": ["*/template_parts/*", "*/page-templates/*"]
}
```

Add root-level templates (`*/my-theme/index.php`, `header.php`, ...) the same way. On a theme with
113 linted files this took the variable reports from 525 to 251; the rest were root-level templates
and admin partials.

A prefix shorter than three characters (a legacy `am`) is reported as too short, and, as in WPCS,
it is then not accepted: `am_` names are still reported as unprefixed. WPCS reports the bad prefix
once per run (once per process with `--parallel`); this rule reports it once per worker, at the top
of the first file that worker lints. Mago's default pool starts up to one worker per thread, so
expect between one report and one per thread; `workers = 1` under `[extension-hosts.wordpress]` in
`mago.toml` makes it exactly one. The same holds for `ForbiddenPrefixPassed` and
`InvalidPrefixPassed`. Silencing the message keeps the prefix rejected and drops only the reminder:

```json
"exclude-patterns": {
  "WordPress.NamingConventions.PrefixAllGlobals.ShortPrefixPassed": ["*"]
}
```

`WordPress-Core` and `WordPress-Extra` also pull in generic sniffs that catch real bugs and that
Mago doesn't cover. They are ported as `generic/*` rules and matched against phpcs's own tests
(`bench/results/wpcs-parity.md`):

| Rule | Level | Ports | Checks |
|:---|:---|:---|:---|
| `generic/assignment-in-condition` | Warning | `Generic.CodeAnalysis.AssignmentInCondition` | an assignment in an `if`, `elseif`, `switch`, `case`, `while`, `match` or `for` condition, including `! $a = f()` |
| `generic/byte-order-mark` | Error | `Generic.Files.ByteOrderMark` | a UTF-8 or UTF-16 byte order mark at the start of the file |
| `generic/class-modifier-keyword-order` | Error | `Universal.Classes.ModifierKeywordOrder` | `readonly final class` instead of `final readonly class` (and the same for `abstract`); autofix |
| `generic/constant-modifier-keyword-order` | Error | `Universal.Constants.ModifierKeywordOrder` | `public final const` instead of `final public const`; autofix |
| `generic/control-signature` | Error | `Squiz.ControlStructures.ControlSignature` | only `SpaceAfterCloseBrace`: a closing brace not followed by one space before `else`, `elseif`, `catch`, `finally` or a do-`while`, such as `} // note` then `else` on the next line, which `mago format` keeps; fixed where no comment is in the way. The sniff's other codes are `mago format`'s job |
| `generic/disallow-alternative-php-tags` | Warning | `Generic.PHP.DisallowAlternativePHPTags` | `<%`, `<%=` and `<script language="php">` in inline HTML (removed in PHP 7, so the code inside is output as HTML) |
| `generic/disallow-attribute-parentheses` | Error | `Universal.Attributes.DisallowAttributeParentheses` | `#[Attr()]` instead of `#[Attr]` (`WordPress-Extra`; autofix unless a comment is inside the parentheses) |
| `generic/disallow-lonely-if` | Error | `Universal.ControlStructures.DisallowLonelyIf` | an `else` block whose only statement is an `if`; the fix rewrites the brace form to `elseif` as phpcbf does (comments around the inner `if` and the alternative syntax are not fixed) |
| `generic/disallow-mixed-group-use` | Error | `Universal.UseStatements.DisallowMixedGroupUse` | a group `use` that imports more than one kind of construct (classes, functions, constants); no fix, unlike the sniff, which splits the statement |
| `generic/disallow-multiple-assignments` | Error | `Squiz.PHP.DisallowMultipleAssignments` | an assignment that is not first in its statement: `$a = $b = 1`, `if ( $a = f() )`, `f( $a = 1 )` |
| `generic/disallow-namespace-without-name` | Error | `Universal.Namespaces.DisallowDeclarationWithoutName` | `namespace { ... }`, which declares the global namespace |
| `generic/disallow-size-functions-in-loops` | Error | `Squiz.PHP.DisallowSizeFunctionsInLoops` | `count()`, `sizeof()` or `strlen()` in a `while`/do-`while` condition or a `for` loop's test part |
| `generic/disallow-standalone-post-increment-decrement` | Warning | `Universal.Operators.DisallowStandalonePostIncrementDecrement` | `$i++;` or `$i--;` as a statement of its own (WordPress-Core makes it a warning); the fix moves the operator to the front |
| `generic/double-quote-usage` | Error | `Squiz.Strings.DoubleQuoteUsage` | only `NotRequired` (the code WordPress-Core includes): a double-quoted string that needs no double quotes; the fix single-quotes it as phpcbf does |
| `generic/else-if-declaration` | Warning | `PSR2.ControlStructures.ElseIfDeclaration` | `else if` instead of `elseif` (fixed) |
| `generic/empty-statement` | Error | `Generic.CodeAnalysis.EmptyStatement` | an `if`, `elseif`, `else`, loop, `try`, `catch`, `finally`, `switch` or `match` whose body is empty or only comments |
| `generic/for-loop-with-test-function-call` | Warning | `Generic.CodeAnalysis.ForLoopWithTestFunctionCall` | any function or method call in a `for` loop's test part |
| `generic/foreach-unique-assignment` | Error | `Universal.CodeAnalysis.ForeachUniqueAssignment` | `foreach ($a as $k => $k)`, or a key reused as a destructuring target (the key wins, so the value is lost); no fix, since phpcbf's changes which value the variable gets |
| `generic/git-merge-conflict` | Error | `Generic.VersionControl.GitMergeConflict` | merge conflict markers at the start of a line, including in inline HTML, comments and heredocs, where the file still parses |
| `generic/increment-decrement-usage` | Error | `Squiz.Operators.IncrementDecrementUsage` | `$i = $i + 1`, `$i += 1` or `$i -= 1` where `++`/`--` would do, and `++`/`--` inside arithmetic or an unbracketed concatenation; no fix, as in phpcs |
| `generic/inline-control-structure` | Error | `Generic.ControlStructures.InlineControlStructure` | an `if`, `elseif`, `else`, `foreach`, `for`, `while` or `do` body without braces; the fix adds them as phpcbf does |
| `generic/jumbled-incrementer` | Warning | `Generic.CodeAnalysis.JumbledIncrementer` | a nested `for` loop incrementing the outer loop's variable |
| `generic/lowercase-class-resolution-keyword` | Error | `Universal.Constants.LowercaseClassResolutionKeyword` | `Foo::CLASS` or `$object::Class` instead of `::class` (autofix) |
| `generic/lowercase-php-tag` | Error | `Universal.PHP.LowercasePHPTag` | `<?PHP` or `<?Php` instead of `<?php` (autofix) |
| `generic/lowercase-use-function-const` | Error | `Universal.UseStatements.LowercaseFunctionConst` | `use FUNCTION foo;` or `CONST` inside a group: the keyword is not lowercase (autofix) |
| `generic/method-declaration-warning` | Warning | `PSR2.Methods.MethodDeclaration` | a method name with a single leading underscore (`Underscore`; off in `WordPress-Core`, which silences it) |
| `generic/method-declaration` | Error | `PSR2.Methods.MethodDeclaration` | `final` or `abstract` after a method's visibility, `static` before it (fixed) |
| `generic/method-scope` | Error | `Squiz.Scope.MethodScope` | a method declared without `public`, `protected` or `private` |
| `generic/no-double-negative` | Error | `Universal.CodeAnalysis.NoDoubleNegative` | `!!` (use a `(bool)` cast) or `!!!` and more (use one `!`); fixed as phpcbf does, except before `instanceof` or with a comment in the chain |
| `generic/no-echo-sprintf` | Error | `Universal.CodeAnalysis.NoEchoSprintf` | `echo sprintf(...)` or `echo vsprintf(...)`; the fix drops the `echo` and calls `printf()`/`vprintf()`, as phpcbf does |
| `generic/no-useless-aliases` | Error | `Universal.UseStatements.NoUselessAliases` | an import aliased to the name it already has, such as `use Foo\Bar as Bar;` (names compare case-insensitively, constants case-sensitively; autofix unless a comment is in the way) |
| `generic/non-executable-code` | Warning | `Squiz.PHP.NonExecutableCode` | code after a `return`, `break`, `continue`, `throw`, `exit` or `goto` in the same block (one report per line), and a bare `return;` ending a function |
| `generic/one-namespace-declaration-per-file` | Error | `Universal.Namespaces.OneDeclarationPerFile` | the second and later namespace declarations of a file |
| `generic/property-declaration-warning` | Warning | `PSR2.Classes.PropertyDeclaration` | a property name with a leading underscore (`Underscore`; off in `WordPress-Core`, which silences it) |
| `generic/property-declaration` | Error | `PSR2.Classes.PropertyDeclaration` | a property without visibility, declared with `var`, several per statement, modifiers out of order, not one space after its type (fixes the last two) |
| `generic/require-explicit-boolean-operator-precedence` | Error | `Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence` | `&&`, `\|\|`, `and`, `or`, `xor` mixed without parentheses, as in `$a && $b \|\| $c` |
| `generic/closing-tag` | Error | `PSR2.Files.ClosingTag` | a `?>` at the end of a file with no inline HTML (autofix); templates are left alone, as the sniff leaves them |
| `generic/commented-out-code` | Warning | `Squiz.PHP.CommentedOutCode` | a comment whose text is more than 40 % PHP code tokens (WordPress-Extra's `maxPercentage`), usually code commented out instead of deleted |
| `generic/dirname` | Error | `Modernize.FunctionCalls.Dirname` | `dirname(__FILE__)`, which is `__DIR__` (`FileConstant`; autofix); not the `Nested` code, which WordPress-Extra turns off |
| `generic/forbidden-functions` | Error | `Generic.PHP.ForbiddenFunctions` | `sizeof()` and `delete()`, the sniff's default list, which WordPress-Extra keeps |
| `generic/function-comment` | Error | `Squiz.Commenting.FunctionComment` | the docblock checks WordPress-Docs keeps, on functions and methods: `@param` tags missing, extra, misnamed, without a type, name or comment, or a comment without a full stop; `@return` and `@throws` tags without a type or with a bad comment; a duplicate `@return`, `@return void` with a `return` value, an empty `@see`, a blank line before the declaration, and the parameter-type spacing. Fixes the spacing and blank lines as phpcbf does. `Missing` and `WrongStyle` stay with `missing-docs`. Part of the full `WordPress` standard only (WordPress-Core and -Extra skip it) |
| `generic/closing-declaration-comment` | Error | `Squiz.Commenting.ClosingDeclarationComment` | an `//end class` or `//end foo()` comment after a closing brace that is wrong (`Incorrect`) or on a later line (`Misplaced`); a declaration without one is fine, as WordPress-Docs turns off `Missing`. Fixes both, as phpcbf does. Part of the full `WordPress` standard only |
| `generic/empty-catch-comment` | Error | `Squiz.Commenting.EmptyCatchComment` | a `catch` block with nothing in it, not even a comment. Replaces Mago's `no-empty-catch-clause`, which also reports a `catch` that has a comment. Part of the full `WordPress` standard only |
| `generic/doc-comment-alignment` | Error | `Squiz.Commenting.DocCommentAlignment` | docblock asterisks not one column in from the opening tag (`SpaceBeforeStar`) and an asterisk with no space after it (`NoSpaceAfterStar`), in the docblock of a class, interface, enum, function, method or property, or right after the opening tag. Fixes both, as phpcbf does. Part of the full `WordPress` standard only |
| `generic/doc-comment` | Error | `Generic.Commenting.DocComment` | a docblock that is empty, has no short description (`MissingShort`) or starts its short or long description in lower case, with other than one blank line between the descriptions, before the tags, between tag groups, or any at the end. Fixes the four spacing codes, as phpcbf does. Part of the full `WordPress` standard only |
| `generic/function-comment-throw-tag` | Error | `Squiz.Commenting.FunctionCommentThrowTag` | a documented function or method that throws without a `@throws` tag (`Missing`), with fewer or more tags than exceptions (`WrongNumber`), or with no tag naming an exception class it throws. Reads the exceptions as the sniff does: `throw new X`, a rethrown `catch` variable, closures and anonymous classes skipped. No fix. Part of the full `WordPress` standard only |
| `generic/inline-comment` | Error | `Squiz.Commenting.InlineComment` | `#` comments (`WrongStyle`), no space, several spaces or a tab before the text of a `//` comment (`NoSpaceBefore`, `SpacingBefore`, `TabBefore`), blank `//` comments (`Empty`), a comment that does not end in `.`, `!` or `?` (`InvalidEndChar`; a run of `//` lines is one comment), and a blank line after the last comment in a function (`SpacingAfterAtFunctionEnd`). Fixes all of these but `InvalidEndChar`, as phpcbf does. Part of the full `WordPress` standard only |
| `generic/block-comment` | Error | `Squiz.Commenting.BlockComment` | a `/* */` comment that is empty (`Empty`, on one line or several) or, if it has several lines, whose text starts on the opening line (`NoNewLine`), that starts with a blank line (`HasEmptyLine`) or, without stars, in lower case (`NoCapital`), whose closer shares a line with text, or that has no blank line before it (`NoEmptyLineBefore`), or has one right after `{` or the opening tag (`HasEmptyLineBefore`). Fixes `Empty`, `NoNewLine` and `HasEmptyLine`, as phpcbf does. Part of the full `WordPress` standard only |
| `generic/class-comment` | Error | `Squiz.Commenting.ClassComment` | blank lines between a class docblock and the class (`SpacingAfter`; compared with the line of the `class` keyword, as phpcs does). `Missing` stays with `missing-docs`. No fix. Part of the full `WordPress` standard only |
| `generic/variable-comment` | Error | `Squiz.Commenting.VariableComment` | a property docblock with no `@var` tag (`MissingVar`), an empty `@var` (`EmptyVar`) or `@see` (`EmptySees`), or more than one `@var` (`DuplicateVar`). `Missing` and `WrongStyle` stay with `missing-docs`. No fix. Part of the full `WordPress` standard only |
| `generic/file-comment` | Error | `Squiz.Commenting.FileComment` | the file docblock WordPress-Docs asks for, on the first PHP open tag: none (`Missing`, also when the docblock documents the declaration after it), `//` or `/*` in its place (`WrongStyle`), a blank line before it or not exactly one after it, a missing `@package` tag, and a duplicate or empty `@package`, `@subpackage`, `@author` or `@copyright` tag. No fix. Part of the full `WordPress` standard only |
| `generic/file-header` | Error | `PSR12.Files.FileHeader` | header statements out of order or ungrouped: the file docblock, `declare`, namespace, then `use`, `use function`, `use const` (`IncorrectOrder`, `IncorrectGrouping`; the blank-line spacing stays with `mago format`) |
| `generic/including-file-warning` | Warning | `PEAR.Files.IncludingFile` | an `include`/`include_once` outside any scope, condition or assignment, which should be `require`/`require_once` (`UseRequire`, `UseRequireOnce`; autofix) |
| `generic/including-file` | Error | `PEAR.Files.IncludingFile` | `require( 'file.php' )`: parentheses around the path (`BracketsNotRequired`; autofix) |
| `generic/no-leading-backslash` | Error | `Universal.UseStatements.NoLeadingBackslash` | `use \Foo\Bar;`: an import name with a leading backslash (autofix) |
| `generic/no-reserved-keyword-parameter-names` | Warning | `Universal.NamingConventions.NoReservedKeywordParameterNames` | a parameter named after a reserved keyword or a type (`$string`, `$default`, `$class`), confusing with named arguments |
| `generic/one-object-structure-per-file` | Error | `Generic.Files.OneObjectStructurePerFile` | a second class, interface, trait or enum in a file, conditional declarations included |
| `generic/self-member-reference` | Error | `Squiz.Classes.SelfMemberReference` | the enclosing class named instead of `self::`, `self` in another case, spaces around `::` (fixed; `NotUsed` is off in `WordPress-Core`) |
| `generic/separate-functions-from-oo` | Error | `Universal.Files.SeparateFunctionsFromOO` | a file that declares both functions and classes, interfaces, traits or enums (once per file) |
| `generic/short-form-type-keywords` | Error | `PSR12.Keywords.ShortFormTypeKeywords` | the casts `(boolean)` and `(integer)` instead of `(bool)` and `(int)` (autofix) |
| `generic/static-in-final-class` | Error | `Universal.CodeAnalysis.StaticInFinalClass` | `static` return types, `static::`, `new static` and `instanceof static` in a final class, anonymous class or enum, where `self` means the same (fixed) |
| `generic/strict-comparisons` | Warning | `Universal.Operators.StrictComparisons` | a loose `==`, `!=` or `<>` comparison; no fix, as WordPress-Core marks the sniff `phpcs-only` (phpcbf leaves it) and `===` can change behaviour |
| `generic/switch-declaration` | Error | `PSR2.ControlStructures.SwitchDeclaration` | only `TerminatingComment`: a non-empty `case` that falls through to the next one without a comment (a body ending in `return`, `break`, `throw`, ... on every path is not a fall-through). The sniff's other codes are `mago format`'s job |
| `generic/unconditional-if-statement` | Warning | `Generic.CodeAnalysis.UnconditionalIfStatement` | an `if` or `elseif` whose condition is the literal `true` or `false`; replaces Mago's `constant-condition` |
| `generic/unnecessary-heredoc` | Warning | `Generic.Strings.UnnecessaryHeredoc` | a heredoc with nothing interpolated and no escape a nowdoc would lose; the fix turns it into a nowdoc as phpcbf does |
| `generic/unused-function-parameter` | Warning | `Generic.CodeAnalysis.UnusedFunctionParameter` | parameters the body never uses after the last used one (`Found`, `FoundAfterLastUsed`); like WordPress-Extra, not earlier ones and not methods of a class that extends or implements |
| `generic/upper-case-constant-name` | Error | `Generic.NamingConventions.UpperCaseConstantName` | a `const` or `define()` constant whose name is not all uppercase |
| `generic/uppercase-magic-constants` | Error | `Universal.Constants.UppercaseMagicConstants` | `__line__`, `__Dir__` and the other magic constants not in uppercase (autofix) |
| `generic/useless-overriding-method` | Warning | `Generic.CodeAnalysis.UselessOverridingMethod` | a method whose body only calls the parent method of the same name with its own parameters, unchanged; replaces Mago's `no-redundant-method-override` |
| `generic/valid-class-name` | Error | `PEAR.NamingConventions.ValidClassName` | a class, interface, trait or enum name that does not start with a capital, or has an `_`-separated word that does not (`My_Class` and `MyClass` pass, `My_class` does not); replaces Mago's `class-name` |
| `generic/valid-logical-operators` | Error | `Squiz.Operators.ValidLogicalOperators` | the `and` and `or` operators; no fix, as `&&` and `\|\|` bind tighter |

The generic rules honour phpcs comments and `exclude-patterns` under their own sniff codes. For
example, `"Generic": ["*"]` turns off every `Generic.*` rule. The `WordPress-Core`-only ruleset of the
phpcs.xml fallback leaves out the generic rules that only `WordPress-Extra` includes.

122 of the 127 rules are on by default. `parentheses-spacing`, `language-construct-spacing`, `array-declaration-spacing`, `function-call-signature` and `array-alignment` run only with `--only`; see
[Formatting](formatting.md). Levels are in the tables above and follow WPCS. Each rule takes the level phpcs
gives its sniff's message codes (`src/Internal/WordPress/Levels.php`, generated from the WPCS source).
Some sniffs report some codes as errors and others as warnings. For eight of them the warning
codes have their own rule, so each half reports at WPCS's level: `nonce-verification-warning`
(`Recommended`), `valid-hook-name-warning` (`UseUnderscores`), `capabilities-warning` (`Unknown`),
`prepared-sql-placeholders-warning` (`LikeWithoutWildcards`, `ReplacementsWrongNumber`,
`UnfinishedPrepare`, `UnnecessaryPrepare`), `ini-set-warning` (`Risky`), `generic/including-file-warning` (`UseRequire`,
`UseRequireOnce`), and `generic/property-declaration-warning` and
`generic/method-declaration-warning` (`Underscore`, which `WordPress-Core` silences and
`WordPress-Extra` restores; under the `WordPress-Core` standard they report nothing, as does
`generic/self-member-reference`'s `NotUsed`). Each pair analyses a file once and shares the result.
A phpcs comment or exclusion that names the message code applies to whichever rule reports it.
The other mixed rules take the majority level and report the minority codes at it:
`capabilities` (`Deprecated` newer than `minimum-wp-version`),
`enqueued-resource-parameters` (`NoExplicitVersion` is a WPCS error),
`prefix-all-globals` (dynamic names, `InvalidPrefixPassed`), `type-casts` (`BinaryFound`),
`valid-post-type-slug` (`PartiallyDynamic`; `NotStringLiteral` is not reported, as phpcs hides its severity 3), `wp-date-time` (`Requested`),
`wp-i18n` (`LowLevelTranslationFunction`, `MismatchedPlaceholders`, `MissingArgDomainDefault`,
`NoHtmlWrappedStrings`, `SuperfluousDefaultTextDomain`), the deprecation rules (newer than
`minimum-wp-version`) and `generic/disallow-alternative-php-tags` (`ScriptOpenTagFound` is a WPCS
error). Use the `levels` setting ([Configuration](configuration.md))
to change a rule's level.

Function, class, constant and capability lists come from WPCS 3.4.1 (`src/Internal/WordPress/Lists.php`,
generated by `php bin/generate-lists.php /path/to/WordPress-Coding-Standards`).

`mago lint --fix` makes the same fixes phpcbf makes for the non-formatting sniffs. It skips cases
where phpcbf's fix would change behaviour or break the file: a misspelling inside an interpolated
`{$...}`, an unqualified `time()` in a namespace, a literal `%%` in a translatable string.

| Rule | Fix | Applied by |
|:---|:---|:---|
| `type-casts` | `(double)`/`(real)` → `(float)` | `--fix` |
| `array-declaration-spacing` | one item per line in keyed and multi-line arrays | `--fix --only wordpress/array-declaration-spacing` |
| `language-construct-spacing` | one space after `echo`, `return`, `new`, `use`, ... | `--fix --only wordpress/language-construct-spacing` |
| `parentheses-spacing` | one space inside parentheses and brackets, none around a literal array key | `--fix --only wordpress/parentheses-spacing` |
| `function-call-signature` | each argument of a multi-line call on its own line, `)` on its own line | `--fix --only wordpress/function-call-signature` |
| `array-alignment` | `=>` aligned to one column up to column 60, one space past it | `--fix --only wordpress/array-alignment` |
| `capital-p-dangit` | misspelling → `WordPress` in comments | `--fix` |
| `capital-p-dangit` | misspelling → `WordPress` in strings and inline HTML (changes output) | `--fix --potentially-unsafe` |
| `wp-date-time` | `current_time( 'timestamp' \| 'U', true )` → `time()`, unless the call holds a comment | `--fix` |
| `wp-i18n` | superfluous `'default'` text domain removed, unless that would drop a comment | `--fix` |
| `wp-i18n` | unordered placeholders numbered, `%s %s` → `%1$s %2$s` (changes the msgid, so existing translations stop matching) | `--fix --potentially-unsafe` |
