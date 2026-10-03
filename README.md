# mago-wordpress

[![CI](https://github.com/rlorenzo/mago-wordpress/actions/workflows/ci.yml/badge.svg)](https://github.com/rlorenzo/mago-wordpress/actions/workflows/ci.yml)
[![Packagist version](https://img.shields.io/packagist/v/rlorenzo/mago-wordpress)](https://packagist.org/packages/rlorenzo/mago-wordpress)
[![Packagist downloads](https://img.shields.io/packagist/dt/rlorenzo/mago-wordpress)](https://packagist.org/packages/rlorenzo/mago-wordpress)
[![PHP 8.1+](https://img.shields.io/badge/php-%5E8.1-777bb4)](composer.json)
[![Mago 1.47+](https://img.shields.io/badge/mago-%5E1.47-0f766e)](https://github.com/carthage-software/mago)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

WordPress Coding Standards for [Mago](https://github.com/carthage-software/mago). This extension
ports the WPCS lint sniffs to Mago's linter, so a plugin or theme is checked in seconds, not
minutes.

## 2–12× faster than phpcs

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/benchmarks-dark.svg">
  <img alt="Bar chart: phpcs WordPress-Extra vs mago + mago-wordpress lint time on the top 10 WordPress.org plugins by active installs and WordPress core, from WooCommerce (33.40 s vs 3.32 s) down to Akismet (0.52 s vs 0.26 s)" src="docs/benchmarks-light.svg" width="800">
</picture>

8.1× faster overall on the top 10 WordPress.org plugins plus WordPress core. Method and numbers:
[Benchmarks](docs/benchmarks.md).

## Install

```shell
composer require --dev carthage-software/mago rlorenzo/mago-wordpress
```

```toml
# mago.toml
extends = "vendor/rlorenzo/mago-wordpress/wordpress-extra.mago.toml"
```

Pick the preset that matches the phpcs standard you used. If you're not sure, use
`wordpress-extra.mago.toml`: `WordPress-Extra` is what most plugins and themes run.

| phpcs `--standard` | Preset |
|:---|:---|
| `WordPress-Core` | `wordpress-core.mago.toml` |
| `WordPress-Extra` | `wordpress-extra.mago.toml` |
| `WordPress` (Core + Docs + Extra + the database and input sniffs) | `wordpress.mago.toml` |

Run `mago lint`. Each preset reports what its standard reports: the rules for sniffs outside it
are off, and so are Mago's own rules that no WPCS sniff runs (`strict-types`, `no-isset`,
`cyclomatic-complexity` and the like), so a project that was clean under phpcs starts clean.
All three include a WordPress formatter preset.

Each rule reports at WPCS's level, and `mago lint` fails only on errors. phpcs also failed on
warnings, so pass `--minimum-fail-level warning` to keep that. `phpcs -n` corresponds to
`--minimum-report-level error`. See [Migrating](docs/migrating.md).

Switching a project with phpcs in CI and a pre-commit hook: [Adopting](docs/adopting.md) walks
through config, suppressions, baseline, CI, hooks and formatting.

The worker runs as PHP inside your project and loads its Composer autoloader, like PHPUnit or
PHPStan, so only lint projects you trust.

## Configure

Settings go in `composer.json`:

```json
{
  "extra": {
    "mago-wordpress": {
      "text-domains": ["my-plugin"],
      "prefixes": ["myplugin"],
      "minimum-wp-version": "6.4"
    }
  }
}
```

- **Already using phpcs?** Without this block, the same settings and exclusions are read from your
  `phpcs.xml`.
- **Existing `phpcs:ignore` and `phpcs:disable` comments still work.**
- **Turn a rule off** with `exclude-patterns`, under its WPCS code. `[linter.rules]` doesn't accept
  `wordpress/*` codes.

All settings: [Configuration](docs/configuration.md).

## Migrate from phpcs

```sh
vendor/bin/mago-wordpress migrate          # print the mago.toml and composer.json settings
vendor/bin/mago-wordpress migrate --write  # save them
```

It also lists anything it couldn't carry over. See [Migrating from phpcs](docs/migrating.md).

## Format

```sh
vendor/bin/mago-wordpress format          # format
vendor/bin/mago-wordpress format --check  # CI: fail with a diff if anything would change
```

`mago format` alone can't add the spaces WordPress puts inside parentheses, so don't use it (or
an editor's Mago format-on-save) on WordPress code. See [Formatting](docs/formatting.md).

## Docs

- [Rules](docs/rules.md): the 57 rules, their levels and their autofixes.
- [Configuration](docs/configuration.md): every setting, the `phpcs.xml` fallback and suppression comments.
- [Formatting](docs/formatting.md): the preset and what still differs from WPCS.
- [Migrating from phpcs](docs/migrating.md): what `migrate` converts and what it can't.
- [Adopting](docs/adopting.md): moving a phpcs project over, step by step, with CI and hooks.
- [WPCS coverage](docs/wpcs-coverage.md): Mago's own WordPress rules, `WordPress-Docs`, the generic sniffs, and what isn't ported.
- [Benchmarks](docs/benchmarks.md): method and full results.

## Development

```shell
composer install   # also points git at .githooks (pre-commit runs `just check`)
just check         # composer validate, format-check, PHPUnit, mago lint + analyze, corpus
```

Rule tests run a real Mago worker against `tests/corpus/rules/<rule>/`. Each expected report is
marked with `// @mago-expect lint:wordpress/<rule>` on the line before it, and any report without
a matching expectation fails. CI runs `just check` on PHP 8.1, 8.4 and 8.5.

## Credits

Generic syntax helpers are adapted from [amateescu/mago-drupal](https://github.com/amateescu/mago-drupal)
(MIT) and the rule data from [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
(MIT); see `NOTICE.md`. The rules were first written in Rust for the Mago fork at
[rlorenzo/mago](https://github.com/rlorenzo/mago) and ported here after Mago gained worker extensions.
