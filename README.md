# mago-wordpress

WordPress Coding Standards for [Mago](https://github.com/carthage-software/mago), as a Mago extension.
It ports the WPCS lint sniffs (the `WordPress.*` rules phpcs runs) to Mago's linter, so a WordPress
plugin or theme can be checked in a fraction of a second instead of minutes.

Formatting sniffs (whitespace, alignment, braces) are not ported: that is `mago format`'s job.

## Install

```shell
composer require --dev carthage-software/mago rlorenzo/mago-wordpress
```

Then extend the shipped configuration from your `mago.toml`:

```toml
extends = "vendor/rlorenzo/mago-wordpress/wordpress.mago.toml"
```

That starts the extension worker and enables Mago's own `wordpress` integration (its eight core
WordPress rules: `nonce-verification`, `prepared-sql`, `validated-sanitized-input`,
`no-unescaped-output`, `use-wp-functions`, `no-direct-db-query`, `no-db-schema-change`,
`no-roles-as-capabilities`). Run `mago lint` as usual.

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
`posts_per_page`, `min_interval`, `additional_word_delimiters`, ...), so a project migrating from phpcs needs no new configuration.

Rules can be disabled or re-levelled from `mago.toml` like any other rule:

```toml
[linter.rules]
"wordpress/capital-p-dangit" = { enabled = false }
"wordpress/posts-per-page" = { level = "error" }
```

## Rules

| Rule | Ports | Checks |
|:---|:---|:---|
| `wordpress/capital-p-dangit` | `WordPress.WP.CapitalPDangit` | "Wordpress"/"word press" misspellings in strings and comments |
| `wordpress/cron-interval` | `WordPress.WP.CronInterval` | `cron_schedules` intervals under `min-cron-interval` (default 900 seconds) |
| `wordpress/discouraged-wp-functions` | `WordPress.WP.DiscouragedFunctions`, `WordPress.PHP.DiscouragedPHPFunctions`, `WordPress.PHP.DevelopmentFunctions` | `query_posts()`, `wp_reset_query()`, serialization, obfuscation, system calls, debug output |
| `wordpress/dont-extract` | `WordPress.PHP.DontExtract` | `extract()` |
| `wordpress/enqueued-resource-parameters` | `WordPress.WP.EnqueuedResourceParameters` | missing `$ver` / `$in_footer` on enqueue and register calls |
| `wordpress/enqueued-resources` | `WordPress.WP.EnqueuedResources` | hardcoded `<script src>` and `<link rel="stylesheet">` tags, in PHP strings or inline HTML |
| `wordpress/global-variables-override` | `WordPress.WP.GlobalVariablesOverride` | assignments to WordPress's protected globals (243 names) |
| `wordpress/posts-per-page` | `WordPress.WP.PostsPerPage` | `posts_per_page`/`numberposts` of `-1` or over `max-posts-per-page` (default 100), `nopaging => true` |
| `wordpress/prefix-all-globals` | `WordPress.NamingConventions.PrefixAllGlobals` | unprefixed global functions, classes, constants and hook names |
| `wordpress/prepared-sql-placeholders` | `WordPress.DB.PreparedSQLPlaceholders` | quoted or unsupported placeholders and count mismatches in `$wpdb->prepare()` |
| `wordpress/safe-redirect` | `WordPress.Security.SafeRedirect` | `wp_redirect()` instead of `wp_safe_redirect()` |
| `wordpress/slow-db-query` | `WordPress.DB.SlowDBQuery` | `meta_query`, `tax_query`, `meta_key`, `meta_value` in query arguments |
| `wordpress/valid-hook-name` | `WordPress.NamingConventions.ValidHookName` | hook names with uppercase letters or separators other than `_` and `additional-word-delimiters` |
| `wordpress/wp-date-time` | `WordPress.DateTime.RestrictedFunctions`, `WordPress.DateTime.CurrentTimeTimestamp` | `date()`, `date_default_timezone_set()`, `current_time('timestamp')` |
| `wordpress/wp-deprecated-classes` | `WordPress.WP.DeprecatedClasses` | deprecated core classes, gated by `minimum-wp-version` |
| `wordpress/wp-deprecated-functions` | `WordPress.WP.DeprecatedFunctions` | 386 deprecated core functions with their replacements, gated by `minimum-wp-version` |
| `wordpress/wp-i18n` | `WordPress.WP.I18n` | wrong or missing text domains, non-literal strings, placeholder mismatches in `_n()` |

All rules are enabled by default when the extension is installed. Function, class, constant and
capability lists come from WPCS 3.4.1 (`src/Internal/WordPress/Lists.php`).

## Benchmarks

`bench/run.sh <project> <text-domain> <prefix>` times phpcs (`WordPress-Extra`, WPCS 3.4.1,
`--parallel=8`) against `mago lint` running only this extension's rules, mean of three runs after a
warm-up, on the same machine (Apple M-series, PHP 8.4, Mago 1.50). Issue counts differ because the
tools do not agree on scope: WPCS honours `phpcs:ignore` comments and this extension does not, and
several rules here look deeper (for example `posts-per-page` follows `$args` variables into
`WP_Query`, which the sniff cannot).

| Codebase | PHP files | phpcs `WordPress-Extra` | `mago lint` + this extension | Speed-up |
|:---|---:|---:|---:|---:|
| Elementor | 1,460 | 11.0 s | 1.6 s | 7× |
| Yoast SEO | 1,511 | 15.3 s | 1.8 s | 9× |
| WooCommerce | 3,528 | 40.2 s | 4.9 s | 8× |
| a small theme | 88 | 1.0 s | 0.3 s | 3× |

Measured 2026-09-27 on the plugins' release zips (vendor and tests excluded), `mago` at 1.50.0 and this
package at 0.1.0. The mago column includes starting the PHP worker.

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
