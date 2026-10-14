# Line-level parity on real code

`bench/line-parity.php` compares, rule by rule, the (file, line) pairs phpcs and this package report.
Sniffs that share one rule are compared together; only sniffs that `SniffMap` maps to a rule are in it.

Measured 2026-10-14 on Akismet 5.7.2, Contact Form 7 6.1.7 and Yoast SEO 28.5 (1,651 PHP files,
`vendor` directories left out) with the `WordPress-Extra` ruleset (WPCS 3.4.1, phpcs 3.13.6), without
`WordPress.WP.I18n` and `WordPress.NamingConventions.PrefixAllGlobals` (they need per-plugin settings), against
`wordpress.mago.toml` on Mago 1.51.0 (`main` at `ebdf75f`):

```
9322 lines compared, 0 differences
```

Across 45 rules, every line phpcs reports is reported here and the other way round. The phpcs sniffs left
out of the comparison are formatting sniffs (`mago format` and the opt-in format steps cover them, see
[Formatting](../../docs/formatting.md)), and the `missing-docs` rule (673 reports) comes from `WordPress-Docs`,
which `WordPress-Extra` does not include.

```sh
phpcs --standard=<ruleset> --extensions=php --report=json -q <dir> > phpcs.json
mago lint --ignore-baseline --minimum-fail-level note --reporting-format json > mago.json
php bench/line-parity.php phpcs.json mago.json <dir>
```
