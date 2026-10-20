# Benchmarks

`bench/run.sh <project> <text-domain> <prefix>` times phpcs (`WordPress`, WPCS 3.4.1,
`--parallel=8`) against `mago lint` running this extension's default rules and Mago's `missing-docs` (the WordPress-Docs sniffs). Each figure is the mean of
three runs after a warm-up, on the same machine (Apple M4 MacBook Air, PHP 8.4.24, Mago 1.57.0). Both
tools run on PHP with `opcache.enable_cli=1`.

The rule sets match closely. The extension ports 183 of the 186 sniffs in the `WordPress` standard.
The three left out are `Generic.PHP.Syntax` (which runs `php -l`), `Generic.Classes.DuplicateClassName`
and `WordPress.Utils.I18nTextDomainFixer`. Mago also parses each file itself, which phpcs does not.

The bake-off covers the top 10 plugins on WordPress.org by active installs, plus WordPress core
(`src/` from [wordpress-develop](https://github.com/WordPress/wordpress-develop), text domain
`default`, prefix `wp`). WPCS ships a narrower `WordPress-Core` ruleset for core, but the table runs
the same `WordPress` comparison throughout, for consistency. `classic-editor`, next in
active-install rank, was skipped (1 PHP file after excludes) in favour of `wp-mail-smtp`, the next
plugin down the list.

| Codebase | Version | Active installs | PHP files | phpcs `WordPress` | `mago lint` + this extension | Speed-up |
|:---|:---|---:|---:|---:|---:|---:|
| WooCommerce | 11.1.2 | 7,000,000+ | 3,528 | 26.33 s | 12.64 s | 2.1× |
| WordPress core | trunk | n/a¹ | 1,868 | 18.14 s | 14.63 s | 1.2× |
| Elementor | 4.3.2 | 10,000,000+ | 1,460 | 10.61 s | 4.96 s | 2.1× |
| Google Site Kit | 1.188.0 | 5,000,000+ | 1,869 | 12.47 s | 5.40 s | 2.3× |
| Yoast SEO | 28.5 | 10,000,000+ | 1,511 | 9.72 s | 3.59 s | 2.7× |
| WPForms Lite | 2.0.2.1 | 5,000,000+ | 963 | 7.97 s | 5.32 s | 1.5× |
| LiteSpeed Cache | 7.9.1 | 7,000,000+ | 212 | 2.19 s | 2.04 s | 1.1× |
| WP Mail SMTP | 4.9.0 | 4,000,000+ | 185 | 1.79 s | 1.68 s | 1.1× |
| All-in-One WP Migration | 7.111 | 5,000,000+ | 147 | 1.56 s | 0.74 s | 2.1× |
| Contact Form 7 | 6.1.7 | 10,000,000+ | 111 | 1.15 s | 0.89 s | 1.3× |
| Akismet | 5.7.2 | 5,000,000+ | 29 | 0.70 s | 0.57 s | 1.2× |
| **Total** | | | **11,883** | **92.63 s** | **52.46 s** | **1.8×** |

¹ WordPress.org publishes active-install counts for plugins only; core has no equivalent figure.

Measured 2026-10-20 on the plugins' release zips (vendor and tests excluded) and a wordpress-develop
checkout, with `mago` at 1.57.0, this package at 1.6.0 plus the speed-ups in this release and the rules
that are on by default. phpcs suppression comments are honoured. Load average was 4–8 from background
services, so expect some noise. The mago column includes starting the PHP worker.

The speed-up is 2.1-2.7× on the large plugins (WordPress core, 1.2×, is the exception) and about even
on the small ones, where starting the worker processes is a large share of the time. The extension has
about four times as many rules as the 46 that the first bake-off (2026-10-01, 8.1×) measured, so those
figures are not comparable with these.

Full output, per-codebase mago issue counts by rule and exact reproduction commands are in
[`bench/results/2026-10-20-bakeoff.md`](../bench/results/2026-10-20-bakeoff.md). Every rule PR
re-runs the bake-off (`bench/bakeoff.sh`) and commits the per-rule counts next to it; the newest
`*-bakeoff.md` in [`bench/results/`](../bench/results/) has the current counts.
