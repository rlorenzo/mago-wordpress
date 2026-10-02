# Benchmarks

`bench/run.sh <project> <text-domain> <prefix>` times phpcs (`WordPress-Extra`, WPCS 3.4.1,
`--parallel=8`) against `mago lint` running only this extension's rules, mean of three runs after a
warm-up, on the same machine (Apple M4 MacBook Air, PHP 8.4.24, Mago 1.51.0). This is not an
apples-to-apples comparison of the same rule set: `WordPress-Extra` also runs WPCS's formatting and
generic sniffs, while the mago side runs this extension's rules only.

The bake-off covers the top 10 plugins on WordPress.org by active installs, plus WordPress core
itself (`src/` from [wordpress-develop](https://github.com/WordPress/wordpress-develop), text
domain `default`, prefix `wp`). WPCS ships a narrower `WordPress-Core` ruleset for core, but this
table runs the same `WordPress-Extra` comparison as the plugins throughout, for consistency.
`classic-editor`, next in active-install rank, was skipped (1 PHP file after excludes) in favour of
`wp-mail-smtp`, the next plugin down the list.

| Codebase | Version | Active installs | PHP files | phpcs `WordPress-Extra` | `mago lint` + this extension | Speed-up |
|:---|:---|---:|---:|---:|---:|---:|
| WooCommerce | 11.1.2 | 7,000,000+ | 3,528 | 33.40 s | 3.32 s | 10.1× |
| WordPress core | trunk | — | 1,868 | 25.89 s | 3.78 s | 6.8× |
| Elementor | 4.3.2 | 10,000,000+ | 1,460 | 13.02 s | 1.35 s | 9.6× |
| Google Site Kit | 1.188.0 | 5,000,000+ | 1,869 | 15.92 s | 1.33 s | 12.0× |
| Yoast SEO | 28.5 | 10,000,000+ | 1,511 | 12.32 s | 1.25 s | 9.9× |
| WPForms Lite | 2.0.2.1 | 5,000,000+ | 963 | 11.11 s | 1.68 s | 6.6× |
| LiteSpeed Cache | 7.9.1 | 7,000,000+ | 212 | 2.53 s | 0.62 s | 4.1× |
| WP Mail SMTP | 4.9.0 | 4,000,000+ | 185 | 2.35 s | 0.56 s | 4.2× |
| All-in-One WP Migration | 7.111 | 5,000,000+ | 147 | 1.67 s | 0.35 s | 4.8× |
| Contact Form 7 | 6.1.7 | 10,000,000+ | 111 | 1.40 s | 0.34 s | 4.1× |
| Akismet | 5.7.2 | 5,000,000+ | 29 | 0.52 s | 0.26 s | 2.0× |
| **Total** | | | **11,883** | **120.13 s** | **14.84 s** | **8.1×** |

Measured 2026-10-01 on the plugins' release zips (vendor and tests excluded) and a wordpress-develop
checkout, `mago` at 1.51.0 and this package at 1.2.0, with the rules that are on by default (phpcs
suppression comments honoured; load average 5–9 from background services, so expect some noise). The
mago column includes starting the PHP worker. mago never lost a single-codebase comparison. Full
output, per-codebase mago issue counts by rule, and exact reproduction commands are in
[`bench/results/2026-10-01-bakeoff.md`](../bench/results/2026-10-01-bakeoff.md).
Every rule PR re-runs the bake-off (`bench/bakeoff.sh`) and commits the per-rule counts next to it;
the newest `*-bakeoff.md` in [`bench/results/`](../bench/results/) has the current counts.
