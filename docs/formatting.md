# Formatting

The `extends` in [Install](../README.md#install) also sets Mago's formatter to the closest it gets to
`WordPress-Core`: tabs, braces on the same line, `! $x`, spaces inside grouping parentheses
`( $a + $b )`, aligned `=` and `=>`, and argument and parameter lists kept broken where you broke them.
It also sets `array-style` to `long`; `mago lint --fix --only array-style` rewrites `[]` as `array()`.
Override any of these under `[formatter]` in your own `mago.toml`.

`mago format` cannot add the spaces WordPress puts inside parentheses and brackets, and upstream has
declined an option for it ([#446](https://github.com/carthage-software/mago/issues/446),
[#490](https://github.com/carthage-software/mago/issues/490)). This package adds them with a lint fix
instead, `wordpress/parentheses-spacing`, which is off by default and runs only when named:

```sh
mago lint --fix --only array-style
mago format
mago lint --fix --only wordpress/parentheses-spacing
```

Run the three together, every time: `mago format` removes the spaces again, so on its own (or as an
editor's format-on-save) it undoes the third step. For the same reason `mago format --check` always
fails on code formatted this way; check with `mago lint --only wordpress/parentheses-spacing` instead.
Lines are not re-wrapped after the spaces go in, so a few may run past `print-width`.

Without the third step, `mago format` cannot produce WordPress formatting exactly. Measured on Akismet, Contact Form 7 and
Yoast SEO ([results](../bench/results/2026-09-30-formatter.md)), formatting with this preset leaves
95,040 phpcs-fixable `WordPress-Core` reports, against 334,577 with Mago's defaults. What remains:

| Cause | `WordPress-Core` sniff codes | Share |
|:---|:---|---:|
| **No spaces inside call, declaration, control-structure and array parentheses** (`foo( $a )`, `if ( $x )`, `array( 1 )`, `$a[ $i ]`). Mago has no option for this and upstream declined one. The `!` and cast reports are the same cause: `(! $x)` has no space after the parenthesis. `wordpress/parentheses-spacing` fixes all of these: with it, 7,579 reports remain (92 % fewer). | `PEAR.Functions.FunctionCallSignature.SpaceAfterOpenBracket`, `.SpaceBeforeCloseBracket`; `WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceAfterOpenParenthesis`, `.NoSpaceBeforeCloseParenthesis`; `Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingAfterOpen`, `.SpacingBeforeClose`; `WordPress.Arrays.ArrayKeySpacingRestrictions.NoSpacesAroundArrayKeys`; `NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerSingleLine`, `.SpaceBeforeArrayCloserSingleLine`, `.SpaceAfterArrayOpenerMultiLine`, `.SpaceBeforeArrayCloserMultiLine`; `WordPress.WhiteSpace.OperatorSpacing.NoSpaceBefore`; `WordPress.WhiteSpace.CastStructureSpacing.NoSpaceBeforeOpenParenthesis` | 92 % |
| **Alignment limits.** WPCS stops aligning `=>` past column 60 and `=` past 40 spaces of padding, and aligns across comments and blank lines; Mago aligns each run without a limit. | `WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow`, `.DoubleArrowNotAligned`; `Generic.Formatting.MultipleStatementAlignment.NotSameWarning` | 5 % |
| **Hugged last argument.** Mago keeps `foo( $a, array(` on one line; PEAR wants one argument per line once a call breaks. | `PEAR.Functions.FunctionCallSignature.ContentAfterOpenBracket`, `.CloseBracketLine`, `.MultipleArguments`, `.Indent` | 2 % |
| **Templates and alternative syntax.** Mago prints `if ( $x ):` without the space before `:`, and breaks long `<?php echo … ?>` lines inside HTML. | `WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceBetweenStructureColon`; `Squiz.ControlStructures.ControlSignature.SpaceAfterCloseParenthesis`; `Squiz.PHP.EmbeddedPhp.*`; `Generic.WhiteSpace.LanguageConstructSpacing.IncorrectSingle` | < 1 % |

On code already formatted for phpcs, `mago format` makes the phpcs result worse, not better:
Akismet goes from 88 reports to 5,594. Adopt the preset only if you are switching formatting to
Mago and accept its style. If you keep running phpcs for formatting while you move, either exclude
those codes from your ruleset or don't run `mago format` on the files phpcs still checks.
