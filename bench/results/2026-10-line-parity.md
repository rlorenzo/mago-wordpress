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
| WordPress 6.8.3 (`wp-admin`, `wp-includes`, the root files) | 1,065 | 25,489 | 13 |

Seven more plugins, from wordpress.org on 2026-10-14 (`vendor` directories left out):

| Plugin | Files | Lines compared | Differences |
|:---|---:|---:|---:|
| All-in-One WP Migration | 147 | 676 | 0 |
| LiteSpeed Cache | 212 | 1,544 | 25 |
| WP Mail SMTP | 319 | 3,639 | 1 |
| WPForms Lite | 964 | 12,907 | 23 |
| Elementor | 1,462 | 17,003 | 0 |
| WooCommerce | 3,027 | 9,783 | 9 |
| Google Site Kit | 1,879 | 23,261 | 5 |

All 63 differences on these plugins are of two kinds, and none is a bug in a rule of this package:
`array-style`, `no-shorthand-ternary`, `no-redundant-final` and `no-redundant-string-concat` are Mago's own
rules, which do not read `phpcs:ignore` or `phpcs:ignoreFile`, so the code the plugins exempt from the
sniffs is reported here (`mago-wordpress convert-comments` turns those comments into Mago pragmas). The
rest is upstream behaviour in `prefer-while-loop` (`for (;;)`, 4 lines), `no-redundant-string-concat` (1 line
phpcs reports and Mago misses) and `no-shorthand-ternary` (a multi-line `?:` reported on the line before phpcs's).
The first run found one difference in a rule of this package, `wordpress/enqueued-resources` on Elementor:
phpcs reports a `<link>` at the line of its `rel` attribute, not of `<link`, and matches inline HTML a line at
a time, so a `<script` whose `src=` is on a later line is not seen. Both are fixed; Elementor is at 0.

Core is the harder test: it has code from every era of PHP, and phpcs reports 234,000 messages on it.
A first run showed 857 differences, 836 of them message codes of the two partly ported sniffs. That left
two rules with a real difference of their own, both fixed:

- `wordpress/global-variables-override`: phpcs counts a variable before `=>` as assigned (`=>` is one of
  its assignment tokens), so `array( $plugin => time() )` in the global scope is a write to `$plugin`.
  The rule now reports it, except in a destructuring pattern, where phpcs does not.
- `generic/disallow-standalone-post-increment-decrement`: a statement that is the unbraced body of an
  `if`, `while`, `for`, `foreach`, `else` or `do` (`while ( $j < 3 ) $j++;`) is not reported by phpcs, as its
  statement starts at the keyword. The rule reported it.

The 13 left are in two rules of Mago itself, not in a rule of this package:

- 11 in `no-redundant-string-concat` (Mago's rule): it reports `'?' . '>'`, which the sniff allows so the
  closing tag is not written out, and misses 2 concatenations of strings with different quotes.
- 2 in `prefer-while-loop` (Mago's rule): it reports `for (;;)`; the sniff only reports a `for` with a
  condition and no other part.

`wordpress/global-variables-override` also reports `Foo::$post = 1` and `array( Foo::$post => 1 )`, as phpcs
does (it only skips a property after `->`), and no longer reports `$query_string`, which WPCS's list omits.
Two deliberate differences remain that none of the compared code exercises: `$GLOBALS['$wp_query']` (a different
key from `'wp_query'`, which phpcs reports) and `fn() => $post = ...` (a function scope here, global for phpcs).

```sh
phpcs --standard=<ruleset> --extensions=php --report=json -q <dir> > phpcs.json
mago lint --ignore-baseline --minimum-fail-level note --reporting-format json > mago.json
php -d memory_limit=4G bench/line-parity.php phpcs.json mago.json <dir>
```

## Update, 2026-10-15

The four sniffs that caused the plugin differences above (`Universal.Arrays.DisallowShortArraySyntax`,
`Universal.Operators.DisallowShortTernary`, `Generic.CodeAnalysis.UnnecessaryFinalModifier` and
`Generic.Strings.UnnecessaryStringConcat`) and `Generic.CodeAnalysis.ForLoopShouldBeWhileLoop` are now ported as `generic/*` rules, so
they read `phpcs:ignore` and report where phpcs does. On WordPress 6.8.3 (`wp-admin` and `wp-includes`, WPCS's
Mago-core equivalents replaced), phpcs and this package report the same 1,243 lines for the first four and the same 249
lines for the five generic sniffs that are still covered by a Mago core rule (`no-noop`, `no-goto`, `no-eval`, `lowercase-keyword`,
`lowercase-type-hint`, `no-short-opening-tag`, `no-shell-execute-string`) plus this one. Two defects in the string-concat port were
found on core and fixed: a lone `"` of inline HTML between PHP tags read as the start of a string (5 missed lines), and a multi-line string
on the left of the dot, which phpcs compares by its last line (1 extra line).
