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
- `<arg>` and `<ini>`.

Inline `// phpcs:set` comments are not read.

## Results on real rulesets

**wordpress-develop's `phpcs.xml.dist` (`WordPress-Core`).** 54 of 55 `<exclude-pattern>`s become
globs (`/themes/(?!twenty)*` does not). The Extra-only and WordPress-only rules are turned off, and
the file- and message-scoped exclusions carry over.

Linting the migrated project, every rule this package ports reports the same count as phpcs with
that ruleset: file-name 10, valid-hook-name 1, wp-date-time 1, valid-variable-name 0,
prepared-sql-placeholders 2. The gaps are Mago's core rules: `prepared-sql` 242 vs phpcs's 215, and
`no-error-control-operator` 151 because `customAllowedFunctionsList` has no setting.

16 items are listed as not migrated, mostly `Generic`/`PEAR` sniff refs and `<type>` on extension
rules.

**WooCommerce's `phpcs.xml` (`WooCommerce-Core`).** All 17 path exclusions, the text domain,
`minimum_supported_wp_version`, the 18 custom capabilities and the file-name and hook-name exclusions
migrate. The custom standard, its own sniffs, `PHPCompatibility` and 13 generic sniff refs are
listed.

On the 11.1.1 release zip, the migrated config drops `wordpress/file-name` from 4,536 to 691 reports
and `wordpress/capabilities` from 189 to 0.
