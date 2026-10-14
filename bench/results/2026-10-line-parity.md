# Line-level parity on real code

`bench/line-parity.php` compares, rule by rule, the (file, line) pairs phpcs and this package report.
Sniffs that share one rule are compared together; only sniffs that `SniffMap` maps to a rule are in it,
and of a sniff a rule ports in part (`ControlSignature`, `SwitchDeclaration`) only the message codes it
reports. Measured 2026-10-14 against `WordPress-Extra` (WPCS 3.4.1, phpcs 3.13.6) without
`WordPress.WP.I18n` and `WordPress.NamingConventions.PrefixAllGlobals` (they need per-project settings), and
`wordpress.mago.toml` on Mago 1.51.0.

| Code | Files | Lines compared | Differences |
|:---|---:|---:|---:|
| Akismet 5.7.2, Contact Form 7 6.1.7, Yoast SEO 28.5 | 1,651 | 9,322 | 0 |
| WordPress 6.8.3 (`wp-admin`, `wp-includes`, the root files) | 1,065 | 25,489 | 16 |

Core is the harder test: it has code from every era of PHP, and phpcs reports 234,000 messages on it.
A first run showed 857 differences, 836 of them message codes of the two partly ported sniffs. That left
two rules with a real difference of their own, both fixed:

- `wordpress/global-variables-override`: phpcs counts a variable before `=>` as assigned (`=>` is one of
  its assignment tokens), so `array( $plugin => time() )` in the global scope is a write to `$plugin`.
  The rule now reports it, except in a destructuring pattern, where phpcs does not.
- `generic/disallow-standalone-post-increment-decrement`: a statement that is the unbraced body of an
  `if`, `while`, `for`, `foreach`, `else` or `do` (`while ( $j < 3 ) $j++;`) is not reported by phpcs, as its
  statement starts at the keyword. The rule reported it.

The 16 left are known and are not bugs in the ported rules:

- 3 in `wordpress/global-variables-override`: `$query_string`. WordPress sets it, but WPCS's list omits
  it; the rule reports it on purpose (`EXTRA_GLOBALS`).
- 11 in `no-redundant-string-concat` (Mago's rule): it reports `'?' . '>'`, which the sniff allows so the
  closing tag is not written out, and misses 2 concatenations of strings with different quotes.
- 2 in `prefer-while-loop` (Mago's rule): it reports `for (;;)`; the sniff only reports a `for` with a
  condition and no other part.

Static properties as an array key (`array( Foo::$post => 1 )`) are reported by phpcs and not here; no
file in these codebases has one.

```sh
phpcs --standard=<ruleset> --extensions=php --report=json -q <dir> > phpcs.json
mago lint --ignore-baseline --minimum-fail-level note --reporting-format json > mago.json
php -d memory_limit=4G bench/line-parity.php phpcs.json mago.json <dir>
```
