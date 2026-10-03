# 2026-10-01 formatter: `mago-wordpress format` with the new preset defaults

Re-run of [2026-09-30](2026-09-30-formatter.md) after adding `mago-wordpress format` and four
`[formatter]` defaults to `wordpress.mago.toml`: `print-width = 1000`,
`parentheses-in-exit-and-die = false`, `remove-trailing-close-tag = false` and
`empty-line-after-opening-tag = false`. `wordpress/parentheses-spacing` now also adds the space
before an alternative-syntax colon (`if ( $x ) :`).

Versions: mago 1.51.0 (the old preset was also re-run on 1.50.0 to reproduce the earlier number),
PHP_CodeSniffer 3.13.6 with WPCS 3.4.1, PHP 8.4.24. Plugins: Akismet 5.7.2, Contact Form 7 6.1.7,
Yoast SEO 28.5 (1,834 PHP files).

Method as before: a fresh copy of each plugin under `src/`, a `mago.toml` that extends the preset
with `[source] paths = ["src"]`, then either the three documented steps (old preset) or
`mago-wordpress format` (new). `phpcs --standard=WordPress-Core --report=json` runs on the result and
the reports phpcs marks fixable on `.php` files are counted. phpcs also scans the plugins' `.js` and
`.css` files by default; those reports are left out, as they were in the 2026-09-30 numbers.

| | akismet | contact-form-7 | wordpress-seo | Total |
|:---|---:|---:|---:|---:|
| Three steps, old preset, mago 1.50.0 (the 2026-09-30 number) | 576 | 1,765 | 5,238 | 7,579 |
| Three steps, old preset, mago 1.51.0 | 576 | 1,765 | 5,248 | 7,589 |
| `mago-wordpress format`, new preset, `print-width = 120` | 456 | 1,765 | 5,345 | 7,566 |
| `mago-wordpress format`, new preset | 114 | 1,658 | 5,568 | 7,340 |

Every file passes `php -l` after `mago-wordpress format`, and `mago-wordpress format --check` passes
on the result for all three plugins. With `print-width = 120`, the check fails on Yoast SEO: the
spaces the third step adds push lines past 120 columns, so the next `mago format` wraps them
differently. At 1000 columns that doesn't happen.

`print-width = 1000` is a net win, but not on every code. It adds 926
`LongIndexSpaceBeforeDoubleArrow` reports, 914 of them in one generated file
(`src/generated/container.php`, a dependency-injection map with 150-character keys) that Mago
leaves unaligned at 120 columns and aligns at 1000. It also leaves 124 more single-line associative
arrays (`AssociativeArrayFound`) that 120 columns happened to break. In exchange it removes 230
hugged-argument pairs (`ContentAfterOpenBracket`/`CloseBracketLine`), 120
`LanguageConstructSpacing` reports, and most embedded-PHP reports: a `<?php echo … ?>` stays on one
line.

## Reports left, by cause

| Cause | Reports | Share |
|:---|---:|---:|
| Alignment: WPCS stops aligning `=>` and `=` past a column limit, Mago never stops (`MultipleStatementAlignment`) | 5,256 | 72 % |
| Hugged last argument or several arguments per line in a broken call (`PEAR.Functions.FunctionCallSignature`) | 1,674 | 23 % |
| Associative array with several items on one line (`ArrayDeclarationSpacing.AssociativeArrayFound`) | 147 | 2 % |
| Fixable but not formatting: loose comparisons, `require_once` and similar | 116 | 2 % |
| Other: `echo` followed by a newline, scope indent, multi-catch `A\|B`, embedded PHP, inline tabs | 147 | 2 % |

The spaces inside parentheses and brackets (92 % of the reports after `mago format` alone) and the
alternative-syntax colon (`NoSpaceBetweenStructureColon` and its `ControlSignature` twins, 126
reports) are all fixed.

## bcap_website

The same command on a phpcs-clean WordPress site (107 PHP files in a theme, mu-plugins and a
plugin), against the findings of 2026-10-01:

| Run | Files | Lines changed | Non-whitespace (`git diff -w --ignore-blank-lines`) |
|:---|---:|---:|---:|
| Old preset + three steps | 107 | +4,067 / -2,802 | +2,247 / -1,135 |
| Same + `print-width = 1000` + `parentheses-in-exit-and-die = false` (findings) | 105 | +2,545 / -2,424 | +713 / -750 |
| `mago-wordpress format`, new preset | 81 | +1,919 / -2,116 | +579 / -733 |

`mago-wordpress format --check` passes on the result, a second `mago-wordpress format` changes
nothing, and every file passes `php -l`. Most of the remaining non-whitespace changes are a trailing
comma added to multi-line calls (WPCS allows it) and conditions or ternaries that had been broken
by hand being joined onto one line.
