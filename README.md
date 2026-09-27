# mago-wordpress

[![CI](https://github.com/rlorenzo/mago-wordpress/actions/workflows/ci.yml/badge.svg)](https://github.com/rlorenzo/mago-wordpress/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/rlorenzo/mago-wordpress)](https://github.com/rlorenzo/mago-wordpress/releases)
[![PHP 8.1+](https://img.shields.io/badge/php-%5E8.1-777bb4)](composer.json)
[![Mago 1.47+](https://img.shields.io/badge/mago-%5E1.47-0f766e)](https://github.com/carthage-software/mago)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

WordPress Coding Standards for [Mago](https://github.com/carthage-software/mago), as a Mago extension.
It ports the WPCS lint sniffs (the `WordPress.*` rules phpcs runs) to Mago's linter, so a WordPress
plugin or theme can be checked in a fraction of a second instead of minutes.

Formatting sniffs (whitespace, alignment, braces) are not ported: that is `mago format`'s job.

## 4–7× faster than phpcs

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/benchmarks-dark.svg">
  <img alt="Bar chart: phpcs WordPress-Extra vs mago + mago-wordpress lint time on Elementor (16.7 s vs 3.6 s), Yoast SEO (12.5 s vs 2.9 s) and WooCommerce (66.7 s vs 9.7 s)" src="docs/benchmarks-light.svg" width="760">
</picture>

Same machine, same code, mean of three runs; details and the reproducible script are in [Benchmarks](#benchmarks).

## Install

```shell
composer require --dev carthage-software/mago rlorenzo/mago-wordpress
```

Then extend the shipped configuration from your `mago.toml`:

```toml
extends = "vendor/rlorenzo/mago-wordpress/wordpress.mago.toml"
```

That starts the extension worker and enables Mago's own `wordpress` integration: eight core
WordPress rules (see [Mago's own WordPress rules](#magos-own-wordpress-rules) below), including the
three Mago ships switched off, plus this package's rules. Run `mago lint` as usual.

## Configuration

Mago does not pass custom options to extension rules, so this package reads the settings WPCS
takes as sniff properties from your project instead. Put them in `composer.json`:

```json
{
  "extra": {
    "mago-wordpress": {
      "text-domains": ["my-plugin"],
      "prefixes": ["myplugin", "mp_"],
      "minimum-wp-version": "6.4",
      "custom-escaping-functions": ["mp_esc"],
      "custom-auto-escaped-functions": [],
      "custom-sanitizing-functions": [],
      "custom-unslashing-sanitizing-functions": [],
      "custom-capabilities": [],
      "max-posts-per-page": 100,
      "min-cron-interval": 900,
      "additional-word-delimiters": ""
    }
  }
}
```

If there is no `extra.mago-wordpress` block, the worker reads the same values from your existing
`phpcs.xml` (`text_domain`, `prefixes`, `minimum_supported_wp_version`, `customEscapingFunctions`,
`posts_per_page`, `min_interval`, `additional_word_delimiters`, ...), so a project migrating from phpcs needs no new configuration. An explicitly present but empty `extra.mago-wordpress` block
(`{"extra": {"mago-wordpress": {}}}`) means "use the defaults" and does not fall back to `phpcs.xml`.

Properties are read only when set directly on the sniff's own `<rule ref="WordPress.WP.I18n">` (and
similar); a `<rule ref="WordPress-Extra">` or other ruleset that merely *includes* that sniff is not
followed, so set properties on the sniff ref itself, as phpcs recommends.

`custom-capabilities` lists capabilities `wordpress/capabilities` accepts. The other four `custom-*`
lists (`custom-escaping-functions`, `custom-auto-escaped-functions`, `custom-sanitizing-functions`,
`custom-unslashing-sanitizing-functions`) are parsed and stored but currently have no effect: no rule
in this package reads them yet. They mirror WPCS properties used by Mago's core rules `no-unescaped-output`,
`nonce-verification` and `validated-sanitized-input` (see
[Mago's own WordPress rules](#magos-own-wordpress-rules)), which cannot receive per-project options
from an extension. They are reserved for a planned port of those rules into this package.

Rules can be disabled or re-levelled from `mago.toml` like any other rule:

```toml
[linter.rules]
"wordpress/capital-p-dangit" = { enabled = false }
"wordpress/posts-per-page" = { level = "error" }
```

## Rules

| Rule | Ports | Checks |
|:---|:---|:---|
| `wordpress/assignment-in-ternary-condition` | `WordPress.CodeAnalysis.AssignmentInTernaryCondition` | a variable, array element or property assignment inside a parenthesized ternary condition |
| `wordpress/capabilities` | `WordPress.WP.Capabilities` | roles, deprecated capabilities and unknown capabilities passed to `current_user_can()`, `add_menu_page()` and the other capability checks; accepts `custom-capabilities` |
| `wordpress/capital-p-dangit` | `WordPress.WP.CapitalPDangit` | "Wordpress"/"word press" misspellings in strings and comments |
| `wordpress/class-name-case` | `WordPress.WP.ClassNameCase` | a WordPress core class referenced with the wrong case (instantiation, static call, class constant, `extends`, `implements`) |
| `wordpress/cron-interval` | `WordPress.WP.CronInterval` | `cron_schedules` intervals under `min-cron-interval` (default 900 seconds); only inline callbacks (closures and arrow functions) are inspected |
| `wordpress/db-restricted-classes` | `WordPress.DB.RestrictedClasses` | `mysqli`, `PDO` and `PDOStatement` usage |
| `wordpress/db-restricted-functions` | `WordPress.DB.RestrictedFunctions` | raw `mysql`/`mysqli`/`mysqlnd`/`maxdb` extension function calls |
| `wordpress/discouraged-constants` | `WordPress.WP.DiscouragedConstants` | usage and (re-)declaration of discouraged WordPress constants such as `STYLESHEETPATH` or `PLUGINDIR` |
| `wordpress/discouraged-wp-functions` | `WordPress.WP.DiscouragedFunctions`, `WordPress.PHP.DiscouragedPHPFunctions`, `WordPress.PHP.DevelopmentFunctions` | `query_posts()`, `wp_reset_query()`, serialization, obfuscation, system calls, debug output |
| `wordpress/dont-extract` | `WordPress.PHP.DontExtract` | `extract()` |
| `wordpress/enqueued-resource-parameters` | `WordPress.WP.EnqueuedResourceParameters` | missing `$ver` / `$in_footer` on enqueue and register calls |
| `wordpress/enqueued-resources` | `WordPress.WP.EnqueuedResources` | hardcoded `<script src>` and `<link rel="stylesheet">` tags, in PHP strings or inline HTML |
| `wordpress/escaped-not-translated` | `WordPress.CodeAnalysis.EscapedNotTranslated` | `esc_html()`/`esc_attr()` called with more than one argument, which likely should be `esc_html__()`/`esc_attr__()` |
| `wordpress/file-name` | `WordPress.Files.FileName` | file names not lowercase and hyphenated, a class file missing its `class-` prefix, a templated `wp-includes` file missing its `-template` suffix |
| `wordpress/get-meta-single` | `WordPress.WP.GetMetaSingle` | `get_*meta()`/`get_metadata*()` calls that pass the key parameter without also passing `$single` |
| `wordpress/global-variables-override` | `WordPress.WP.GlobalVariablesOverride` | assignments, foreach bindings, destructuring and `$GLOBALS[...]` writes to WordPress's protected globals (243 names) |
| `wordpress/plugin-menu-slug` | `WordPress.Security.PluginMenuSlug` | `__FILE__` passed as the slug or parent-slug argument of `add_menu_page()` and the other admin menu-registration functions |
| `wordpress/posts-per-page` | `WordPress.WP.PostsPerPage` | `posts_per_page`/`numberposts` of `-1` or over `max-posts-per-page` (default 100), `nopaging => true`, in any array literal (it does not follow `$args` variables into `WP_Query`) |
| `wordpress/prefix-all-globals` | `WordPress.NamingConventions.PrefixAllGlobals` | unprefixed global functions, classes, constants and hook names; inert until `prefixes` is configured |
| `wordpress/prepared-sql-placeholders` | `WordPress.DB.PreparedSQLPlaceholders` | quoted or unsupported placeholders and count mismatches in `$wpdb->prepare()` |
| `wordpress/prepared-sql-unquoted-complex-placeholder` | `WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder` | unquoted complex placeholders (`%1$s`, `%05s`, `%'.10s`) in `$wpdb->prepare()` queries |
| `wordpress/restricted-php-functions` | `WordPress.PHP.RestrictedPHPFunctions` | `create_function()` |
| `wordpress/safe-redirect` | `WordPress.Security.SafeRedirect` | `wp_redirect()` instead of `wp_safe_redirect()` |
| `wordpress/slow-db-query` | `WordPress.DB.SlowDBQuery` | `meta_query`, `tax_query`, `meta_key`, `meta_value` in any array literal (it does not follow `$args` variables into `WP_Query`) or passed to `set_query_var()` |
| `wordpress/strict-in-array` | `WordPress.PHP.StrictInArray` | `in_array()`, `array_search()` and `array_keys()` called without `true` as the `$strict` argument |
| `wordpress/type-casts` | `WordPress.PHP.TypeCasts` | `(double)`/`(real)` normalized to `(float)`, `(unset)` forbidden, `(binary)` and binary string literals discouraged |
| `wordpress/valid-function-name` | `WordPress.NamingConventions.ValidFunctionName` | function and method names not in snake_case, and double-underscore names that are not PHP magic methods |
| `wordpress/valid-hook-name` | `WordPress.NamingConventions.ValidHookName` | hook names with uppercase letters or separators other than `_` and `additional-word-delimiters` |
| `wordpress/valid-post-type-slug` | `WordPress.NamingConventions.ValidPostTypeSlug` | invalid characters, reserved names, a reserved prefix, or a slug over 20 characters in `register_post_type()` |
| `wordpress/valid-variable-name` | `WordPress.NamingConventions.ValidVariableName` | variables, properties and object property accesses not in snake_case, including interpolated variables |
| `wordpress/wp-date-time` | `WordPress.DateTime.RestrictedFunctions`, `WordPress.DateTime.CurrentTimeTimestamp` | `date()`, `date_default_timezone_set()`, `current_time('timestamp')` |
| `wordpress/wp-deprecated-classes` | `WordPress.WP.DeprecatedClasses` | deprecated core classes, gated by `minimum-wp-version` |
| `wordpress/wp-deprecated-functions` | `WordPress.WP.DeprecatedFunctions` | 386 deprecated core functions with their replacements, gated by `minimum-wp-version` |
| `wordpress/wp-deprecated-parameter-values` | `WordPress.WP.DeprecatedParameterValues` | calls passing a deprecated value for a still-valid parameter (e.g. `bloginfo('home')`), gated by `minimum-wp-version` |
| `wordpress/wp-deprecated-parameters` | `WordPress.WP.DeprecatedParameters` | calls passing a non-default value for a now-ignored deprecated parameter, gated by `minimum-wp-version` |
| `wordpress/wp-i18n` | `WordPress.WP.I18n` | wrong or missing text domains, non-literal strings, placeholder mismatches in `_n()`, unordered placeholders, missing `translators:` comments |
| `wordpress/yoda-conditions` | `WordPress.PHP.YodaConditions` | a comparison with a variable, array element or property on the left and a literal or constant on the right |

All 37 rules are enabled by default when the extension is installed, and report at `Warning`
except `wordpress/capital-p-dangit` (`Note`) and eleven rules that report at `Error`:
`db-restricted-classes`, `db-restricted-functions`, `dont-extract`, `file-name`,
`global-variables-override`, `prepared-sql-placeholders`, `restricted-php-functions`,
`type-casts`, `valid-function-name`, `valid-post-type-slug`, `valid-variable-name`. Function,
class, constant and capability lists come from WPCS 3.4.1 (`src/Internal/WordPress/Lists.php`).

## Mago's own WordPress rules

Mago's core linter has its own `wordpress` integration: eight rules, independent of this package's
`wordpress/*` rules. Mago ships three of them disabled; the `extends` in [Install](#install) turns
them on so a project keeps the security checks WPCS gave it.

| Mago rule | Covers | Mago default |
|:---|:---|:---|
| `nonce-verification` | `WordPress.Security.NonceVerification` | off (on via this package's config) |
| `validated-sanitized-input` | `WordPress.Security.ValidatedSanitizedInput` | off (on via this package's config) |
| `prepared-sql` | `WordPress.DB.PreparedSQL` | off (on via this package's config) |
| `no-unescaped-output` | `WordPress.Security.EscapeOutput` | on |
| `use-wp-functions` | `WordPress.WP.AlternativeFunctions` | on |
| `no-direct-db-query` | `WordPress.DB.DirectDatabaseQuery` (`DirectQuery`, `NoCaching`) | on |
| `no-db-schema-change` | `WordPress.DB.DirectDatabaseQuery.SchemaChange` | on |
| `no-roles-as-capabilities` | `WordPress.WP.Capabilities` (its role-checking part; overlaps `wordpress/capabilities` above) | on |

## Coming from WPCS

`WordPress-Extra` and `WordPress-Core` also pull in generic (non-`WordPress.*`) sniffs from
`Generic`, `PEAR`, `PSR2`, `Squiz` and `Universal`. Some of those are already covered by one of
Mago's own core lint rules, which run on every PHP project regardless of the `wordpress`
integration:

| WPCS sniff | Mago rule |
|:---|:---|
| `Generic.CodeAnalysis.AssignmentInCondition` | `no-assign-in-condition` |
| `Generic.CodeAnalysis.EmptyPHPStatement` | `no-noop` |
| `Generic.CodeAnalysis.ForLoopShouldBeWhileLoop` | `prefer-while-loop` |
| `Generic.CodeAnalysis.UnconditionalIfStatement` | `constant-condition` |
| `Generic.CodeAnalysis.UnnecessaryFinalModifier` | `no-redundant-final` |
| `Generic.CodeAnalysis.UselessOverridingMethod` | `no-redundant-method-override` |
| `Generic.Files.OneObjectStructurePerFile` | `single-class-per-file` |
| `Generic.NamingConventions.UpperCaseConstantName` | `constant-name` |
| `Generic.PHP.BacktickOperator` | `no-shell-execute-string` |
| `Generic.PHP.DisallowShortOpenTag` | `no-short-opening-tag` |
| `Generic.PHP.DiscourageGoto` | `no-goto` |
| `Generic.PHP.ForbiddenFunctions` | `disallowed-functions` |
| `Generic.PHP.LowerCaseConstant` | `lowercase-keyword` |
| `Generic.PHP.LowerCaseKeyword` | `lowercase-keyword` |
| `Generic.PHP.LowerCaseType` | `lowercase-type-hint` |
| `Generic.Strings.UnnecessaryStringConcat` | `no-redundant-string-concat` |
| `PEAR.NamingConventions.ValidClassName` | `class-name` |
| `PSR2.Files.ClosingTag` | `no-closing-tag` |
| `Squiz.PHP.DisallowMultipleAssignments` | `no-multi-assignments` |
| `Squiz.PHP.Eval.Discouraged` | `no-eval` |
| `Universal.Arrays.DisallowShortArraySyntax` | `array-style` |
| `Universal.Operators.DisallowShortTernary` | `no-shorthand-ternary` |

Enable these (and the rest of Mago's ~100 core rules) the normal way, in `mago.toml`:

```toml
[linter.rules]
"no-assign-in-condition" = { enabled = true }
```

## Not ported

Formatting sniffs (whitespace, alignment, braces, quote style, keyword and tag casing not listed
above) are not ported: that is `mago format`'s job.

<details>
<summary>Remaining WPCS sniffs with no Mago equivalent</summary>

Generic/PHP correctness sniffs with nothing similar in Mago's core rule set: `Generic.Classes.DuplicateClassName`,
`Generic.CodeAnalysis.EmptyStatement`, `Generic.CodeAnalysis.ForLoopWithTestFunctionCall`,
`Generic.CodeAnalysis.JumbledIncrementer`, `Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence`,
`Generic.CodeAnalysis.UnusedFunctionParameter`, `Generic.Files.ByteOrderMark`, `Generic.PHP.DeprecatedFunctions`,
`Generic.PHP.DisallowAlternativePHPTags`, `Generic.PHP.Syntax`, `Generic.VersionControl.GitMergeConflict`,
`Squiz.Functions.FunctionDuplicateArgument`, `Squiz.PHP.CommentedOutCode`, `Squiz.PHP.DisallowSizeFunctionsInLoops`,
`Squiz.PHP.NonExecutableCode`, `Squiz.Scope.MethodScope`, `Universal.Arrays.DuplicateArrayKey`,
`Universal.CodeAnalysis.ConstructorDestructorReturn`, `Universal.CodeAnalysis.ForeachUniqueAssignment`,
`Universal.CodeAnalysis.NoDoubleNegative`, `Universal.Namespaces.DisallowDeclarationWithoutName`,
`Universal.Namespaces.OneDeclarationPerFile`, `Universal.NamingConventions.NoReservedKeywordParameterNames`,
`Universal.UseStatements.NoUselessAliases`.

Low-value style sniffs, mostly formatting concerns `mago format` already makes moot:
`Generic.Commenting.DocComment`, `Generic.Strings.UnnecessaryHeredoc`, `Modernize.FunctionCalls.Dirname`,
`Modernize.FunctionCalls.Dirname.Nested`, `PEAR.Files.IncludingFile`, `PSR12.Files.FileHeader`,
`PSR12.Keywords.ShortFormTypeKeywords`, `PSR2.Classes.PropertyDeclaration`, `PSR2.ControlStructures.ElseIfDeclaration`,
`PSR2.Methods.MethodDeclaration`, `Squiz.Classes.SelfMemberReference`, `Squiz.Commenting`,
`Squiz.Operators.IncrementDecrementUsage`, `Squiz.Operators.ValidLogicalOperators`, `Squiz.Strings.DoubleQuoteUsage`,
`Universal.Attributes.DisallowAttributeParentheses`, `Universal.Classes.ModifierKeywordOrder`,
`Universal.CodeAnalysis.NoEchoSprintf`, `Universal.CodeAnalysis.StaticInFinalClass`,
`Universal.Constants.LowercaseClassResolutionKeyword`, `Universal.Constants.ModifierKeywordOrder`,
`Universal.Constants.UppercaseMagicConstants`, `Universal.ControlStructures.DisallowLonelyIf`,
`Universal.Files.SeparateFunctionsFromOO`, `Universal.Operators.DisallowStandalonePostIncrementDecrement`,
`Universal.PHP.LowercasePHPTag`, `Universal.UseStatements.DisallowMixedGroupUse`,
`Universal.UseStatements.LowercaseFunctionConst`, `Universal.UseStatements.NoLeadingBackslash`.

</details>

## Benchmarks

`bench/run.sh <project> <text-domain> <prefix>` times phpcs (`WordPress-Extra`, WPCS 3.4.1,
`--parallel=8`) against `mago lint` running only this extension's rules, mean of three runs after a
warm-up, on the same machine (Apple M-series, PHP 8.4, Mago 1.50). This is not an apples-to-apples
comparison of the same rule set: `WordPress-Extra` is the full phpcs standard (all of `WordPress`,
`WordPress-Core`, and `WordPress-Docs`), while the mago side only runs this extension's rules. Issue
counts also differ because WPCS honours `phpcs:ignore` comments and this extension does not.

| Codebase | PHP files | phpcs `WordPress-Extra` | `mago lint` + this extension | Speed-up |
|:---|---:|---:|---:|---:|
| Elementor | 1,460 | 16.7 s | 3.6 s | 4.7× |
| Yoast SEO | 1,511 | 12.5 s | 2.9 s | 4.4× |
| WooCommerce | 3,528 | 66.7 s | 9.7 s | 6.9× |

Measured 2026-09-27 on the plugins' release zips (vendor and tests excluded), `mago` at 1.50.0 and this
package at 0.2.0 (28 rules). The mago column includes starting the PHP worker.

## Development

```shell
composer install   # also points git at .githooks (pre-commit runs `just check`)
just check         # composer validate, format-check, PHPUnit, mago lint + analyze, corpus
```

Rule behaviour is tested with a real Mago worker against `tests/corpus/rules/<rule>/`: each expected
report is marked with `// @mago-expect lint:wordpress/<rule>` on the line before it, and any report
without a matching expectation fails the run. CI runs `just check` on PHP 8.1, 8.4 and 8.5.

## Credits

Generic syntax helpers are adapted from [amateescu/mago-drupal](https://github.com/amateescu/mago-drupal)
(MIT) and the rule data from [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
(MIT); see `NOTICE.md`. The rules were first written in Rust for the Mago fork at
[rlorenzo/mago](https://github.com/rlorenzo/mago) and ported here after Mago gained worker extensions.
