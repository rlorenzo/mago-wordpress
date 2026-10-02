# Formatting

The `extends` in [Install](../README.md#install) also sets Mago's formatter to the closest it gets to
`WordPress-Core`: tabs, braces on the same line, `! $x`, spaces inside grouping parentheses
`( $a + $b )`, aligned `=` and `=>`, and argument and parameter lists kept broken where you broke them.
It also sets `array-style` to `long`; `mago lint --fix --only array-style` rewrites `[]` as `array()`.
Override any of these under `[formatter]` in your own `mago.toml`.

## Spaces inside parentheses

`mago format` cannot add the spaces WordPress puts inside parentheses and brackets, and upstream has
declined an option for it ([#446](https://github.com/carthage-software/mago/issues/446),
[#490](https://github.com/carthage-software/mago/issues/490)). This package adds them with a lint fix,
`wordpress/parentheses-spacing`. It is off by default and runs only when named:

```sh
mago lint --fix --only array-style
mago format
mago lint --fix --only wordpress/parentheses-spacing
```

Run all three, every time. `mago format` removes the spaces again, so running it alone (or as an
editor's format-on-save) undoes the third step. For the same reason `mago format --check` always
fails on code formatted this way; check with `mago lint --only wordpress/parentheses-spacing` instead.
Lines are not re-wrapped after the spaces go in, so a few may run past `print-width`.

## How close it gets

On Akismet, Contact Form 7 and Yoast SEO, phpcs-fixable `WordPress-Core` reports drop from
334,577 (Mago's default formatting) to 95,040 with the preset, and to 7,579 with the third step.
What's left:

| Cause | Share |
|:---|---:|
| Missing spaces inside parentheses and brackets, which the third step fixes | 92 % |
| Alignment: WPCS stops aligning `=>` past column 60 and `=` past 40 spaces, Mago never stops | 5 % |
| Hugged last argument: Mago keeps `foo( $a, array(` on one line | 2 % |
| Templates: no space before `:` in `if ( $x ):`, long `<?php echo … ?>` lines broken | < 1 % |

Sniff codes and per-plugin numbers: [formatter results](../bench/results/2026-09-30-formatter.md).

## Existing phpcs-clean code

On code already formatted for phpcs, `mago format` makes the phpcs result worse: Akismet goes from
88 reports to 5,594. Adopt the preset only if you are switching formatting to Mago and accept its
style. If you keep running phpcs for formatting while you move, either exclude those codes from your
ruleset or don't run `mago format` on files phpcs still checks.
