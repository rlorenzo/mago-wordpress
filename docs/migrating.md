# Migrating from phpcs

`vendor/bin/mago-wordpress migrate` reads `phpcs.xml` (or `.phpcs.xml`, `phpcs.xml.dist`,
`.phpcs.xml.dist`, or a path you pass). It prints the `mago.toml` and composer.json
`extra.mago-wordpress` block that reproduce it, then everything it could not migrate and why.

`--write` saves both next to the ruleset. It merges into composer.json, keeping its indentation, and
refuses to replace an existing `mago.toml` without `--force`.

```sh
vendor/bin/mago-wordpress migrate            # dry run
vendor/bin/mago-wordpress migrate --write
```

## What it maps

| phpcs.xml | Becomes |
|:---|:---|
| `<file>` | `[source] paths` (`vendor/*` is excluded when the whole project is listed) |
| `<exclude-pattern>` | `[source] excludes`, translated to a glob; a pattern a glob cannot express (lookarounds, alternation, classes) is listed for you to handle |
| `<rule ref="WordPress-Core">` / `WordPress-Extra` | `exclude-patterns: "*"` for the extension sniffs that standard leaves out, `enabled = false` for Mago core rules whose sniffs it leaves out (`WordPress` keeps everything) |
| `<exclude name="WordPress...">`, `<severity>0</severity>` | the same, for that code |
| `<exclude-pattern>` inside `<rule ref="WordPress...">` | `exclude-patterns` for this package's rules; `exclude` on a Mago core rule when the ref is the whole sniff |
| `<type>` on a whole sniff ported by a Mago core rule | `level` on that rule |
| `<rule ref="./other.xml"/>` (a path to a ruleset file that exists) | the file is loaded and merged, as phpcs does; its refs resolve relative to it, and a cycle is cut. Settings in `phpcs.xml` that the worker falls back to follow it too |
| `Generic.Metrics.CyclomaticComplexity` (`complexity`) | `cyclomatic-complexity` with `threshold` and `method-threshold` set to it, level `warning`; Mago counts `&&`/`\|\|`, phpcs does not |
| `Generic.Metrics.NestingLevel` (`nestingLevel`) | `excessive-nesting` `threshold = nestingLevel + 1` (measured: Mago counts the function body as level 1) |
| `Squiz.Commenting.{Function,Class,Variable}Comment.Missing` | `missing-docs` with `functions`/`methods`, `classes` or `properties` on, level `error` |
| WPCS properties | the matching `extra.mago-wordpress` setting |
| `<config name="minimum_wp_version">` | `minimum-wp-version` |

## What it lists as not migrated

- `<type>` on this package's rules or on a single message code.
- Exclusions of a message code that only a Mago core rule ports (core rules have no message codes).
- `<include-pattern>`.
- `type="relative"` patterns inside a rule.
- Properties with no setting (`customAllowedFunctionsList`, `exclude` on a sniff only a Mago core
  rule ports, ...).
- Custom or third-party standards (`WooCommerce-Core`, `Jetpack`). Their contents are not followed,
  so every extension rule stays on.
- Non-WordPress sniffs. See [Generic sniffs](wpcs-coverage.md#generic-sniffs) for the Mago rules
  that cover some.
- `PHPCompatibility` and `testVersion` (PHP 8.1+ target).
- `absoluteComplexity` and `absoluteNestingLevel`.
- `<arg>` and `<ini>`.

It ends with how to replace a phpcs hook: `mago lint --minimum-fail-level warning` to fail on
warnings, `--reporting-format short` for `phpcs --report=emacs`-style lines, and that `phpcs:ignore`
comments are honoured by this package's rules only.

Inline `// phpcs:set` comments are not read.

## Results on real rulesets

- **wordpress-develop** (`WordPress-Core`): 54 of 55 path exclusions migrate, and every rule this
  package ports then reports the same count as phpcs. 16 items are listed as not migrated.
- **WooCommerce** (`WooCommerce-Core`): exclusions, text domain and custom capabilities migrate.
  On the 11.1.1 release, `wordpress/file-name` drops from 4,536 to 691 reports and
  `wordpress/capabilities` from 189 to 0.
