# Tokenizer consolidation 2026-10-01: v1.2.0 vs before vs after, against phpcs `WordPress`

What a consumer gets: `mago lint` (all enabled rules, no `--only`) with a `mago.toml` that extends the
package's `wordpress.mago.toml` (`bench/lib.sh` now writes that instead of `integrations = ["wordpress"]`),
text domain and prefix in `composer.json`. Each column runs the same mago binary (1.51.0, PHP 8.4.24)
against that package's own `wordpress.mago.toml` and worker:

- **v1.2.0**: `main` at `669c417` (v1.2.0 + docs/migrate). Its `wordpress.mago.toml` turns on Mago's
  core WordPress rules (Rust) and leaves most other core rules on.
- **before**: `integration/adoption` at `312653d`. The core WordPress rules are PHP ports in the worker
  and the shipped config turns off the core rules no WPCS sniff runs; three tokenizers.
- **after**: `perf/one-tokenizer`. Input, nonce, PreparedSQL and DirectDatabaseQuery share
  `Internal\PhpcsTokens`; escape-output skips files it cannot fire on.

Wall clock, best of 3 after a warm-up. v1.2.0 was timed at 20:36 (load average 2.9–4.1); before and
after were timed alternately, run by run, at 20:5x (load 4.6–10.2, other agents were running). phpcs:
one run per codebase with the full `WordPress` standard (WPCS 3.4.1, PHP_CodeSniffer 3.13.6,
`--parallel=8`, same ignores as `bench/run.sh`), load 7–8. The earlier `bench/results` phpcs numbers
use `WordPress-Extra`, so they were not reused.

| Codebase | phpcs `WordPress` | v1.2.0 | before | after | Speed-up v1.2.0 | before | after |
|:---|---:|---:|---:|---:|---:|---:|---:|
| WooCommerce | 26.32 s | 2.46 s | 3.45 s | 3.37 s | 10.7× | 7.6× | 7.8× |
| WordPress core (`src/`) | 21.94 s | 2.86 s | 4.15 s | 3.92 s | 7.7× | 5.3× | 5.6× |
| Elementor | 13.22 s | 1.05 s | 1.40 s | 1.41 s | 12.6× | 9.4× | 9.4× |
| Yoast SEO | 8.84 s | 0.92 s | 1.23 s | 1.15 s | 9.6× | 7.2× | 7.7× |
| **Total** | **70.32 s** | **7.29 s** | **10.23 s** | **9.85 s** | **9.6×** | **6.9×** | **7.1×** |

## The five token rules alone

`--only` the five rules plus `nonce-verification-warning`, and `--only wordpress/cron-interval` as the
floor (mago start-up, parsing, worker start), best of 3, alternated:

| Codebase | five, before | five, after | floor |
|:---|---:|---:|---:|
| WooCommerce | 1.37 s | 1.25 s | 0.39 s |
| WordPress core | 1.45 s | 1.29 s | 0.33 s |
| Elementor | 0.64 s | 0.64 s | 0.26 s |
| Yoast SEO | 0.57 s | 0.51 s | 0.28 s |

Per rule (`--only <rule>`, median of 5, sequential, load 3–10): escape-output 1.19 → 1.11 s on
WooCommerce and 1.15 → 1.07 s on core; the other four are 0.63–0.73 s each before and after, within
noise of each other, i.e. 0.3 s or so over the floor.

Single-process CPU over WooCommerce + core (5,396 files, `PhpToken::tokenize` alone 0.35 s):
`Internal\PhpcsTokens` 2.9 s, `PhpcsTokenStream` (escape-output) 4.6 s, the deleted
`Internal\WordPress\PhpcsTokens` 2.9 s. The escape-output gate skips 3,956 of 8,367 files across the four
codebases (24 % of the bytes).

## Reading

- The tokenizer work saves 0.1–0.2 s on the large codebases (3–6 %); it does not bring back v1.2.0's
  speed. The gap from v1.2.0 (about 1 s on WooCommerce and core) comes from moving the core WordPress
  rules from Rust into the PHP worker and adding rules, not from tokenizing three times.
- Against phpcs's full `WordPress` standard the extension is now 7.1× faster over these four codebases
  (5.6×–9.4× each), versus 9.6× for v1.2.0. The README's "8.1×" was `WordPress-Extra` on eleven
  codebases with v1.2.0 and is not reproduced here.

## Behaviour

Parity stays 100 % for EscapeOutput (176/176), ValidatedSanitizedInput (106/106), NonceVerification
(66/66), PreparedSQL (33/33) and DirectDatabaseQuery (70/70). The sorted `--reporting-format short`
output of the five rules is identical before and after on WooCommerce, Elementor and Yoast SEO. On
WordPress core it gains 4 lines: two `DirectDatabaseQuery` statements in `wp-admin/includes/ms.php`
(lines 946 and 955) that phpcs also reports. The old DB tokenizer got out of step with that file's
strings and brackets. It had three bugs: an attribute's `]` paired with an open `(` or `{`, a
`?>"<?php` inline-HTML quote opened a string, and an embed's index skip could run past its string.
The shared tokenizer has none of them (see `tests/Unit/PhpcsTokensTest.php`).
