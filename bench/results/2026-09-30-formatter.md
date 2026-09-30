# 2026-09-30 formatter preset: `mago format` vs phpcs `WordPress-Core`

How close `mago format` gets to WordPress formatting with the `[formatter]` block in
`wordpress.mago.toml`. Versions: mago 1.50.0 (the preset was also checked on 1.47.1, the lowest
supported version), PHP_CodeSniffer 3.13.6 with WPCS 3.4.1, PHP 8.4.24. Plugins: Akismet 5.7.2,
Contact Form 7 6.1.7, Yoast SEO 28.5 (1,834 PHP files).

Method: for each plugin, a fresh copy of the release is formatted (for the preset row,
`mago lint --fix --only array-style` first, then `mago format`), and then
`phpcs --standard=WordPress-Core --report=json` runs on the result. The counts are the reports phpcs
marks fixable, which stands in for "formatting". Non-fixable reports (naming, Yoda, file names and
so on, about 10,000) don't change with formatting and are left out.

Akismet and Contact Form 7 are close to WordPress-formatted as shipped. Yoast SEO indents with
spaces, so its unformatted count is high too.

| | akismet | contact-form-7 | wordpress-seo | Total |
|:---|---:|---:|---:|---:|
| Unformatted (as shipped) | 88 | 3,682 | 66,052 | 69,822 |
| `mago fmt`, Mago defaults | 12,874 | 35,554 | 286,149 | 334,577 |
| `mago fmt` + this preset | 5,594 | 15,473 | 73,973 | 95,040 |

The preset removes 72 % of the reports Mago's default style causes. Of the 95,040 left, 87,501
(92 %) are spaces inside call, declaration, control-structure and array parentheses, which Mago
cannot produce (mago #446, #490). The README's Formatting section groups the remaining codes by
cause. On a codebase already formatted for phpcs, `mago format` makes things worse: Akismet goes
from 88 reports to 5,594.

Tried and dropped: `print-width = 200` (joins more calls onto one line and adds about 4,200 reports),
and preserving every other kind of line break (`preserve-breaking-*` for member-access chains,
conditionals and binary expressions: 200 fewer, not worth four more settings).

## Reports left after the preset, by sniff code

| Sniff code | After preset |
|:---|---:|
| `PEAR.Functions.FunctionCallSignature.SpaceAfterOpenBracket` | 24,742 |
| `PEAR.Functions.FunctionCallSignature.SpaceBeforeCloseBracket` | 24,742 |
| `WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceAfterOpenParenthesis` | 9,971 |
| `WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceBeforeCloseParenthesis` | 9,971 |
| `Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingAfterOpen` | 4,672 |
| `Squiz.Functions.FunctionDeclarationArgumentSpacing.SpacingBeforeClose` | 4,672 |
| `WordPress.Arrays.MultipleStatementAlignment.LongIndexSpaceBeforeDoubleArrow` | 2,348 |
| `WordPress.WhiteSpace.OperatorSpacing.NoSpaceBefore` | 2,238 |
| `WordPress.Arrays.ArrayKeySpacingRestrictions.NoSpacesAroundArrayKeys` | 2,115 |
| `NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerSingleLine` | 2,103 |
| `NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceBeforeArrayCloserSingleLine` | 2,103 |
| `WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned` | 1,864 |
| `PEAR.Functions.FunctionCallSignature.ContentAfterOpenBracket` | 820 |
| `PEAR.Functions.FunctionCallSignature.CloseBracketLine` | 820 |
| `PEAR.Functions.FunctionCallSignature.MultipleArguments` | 569 |
| `Generic.Formatting.MultipleStatementAlignment.NotSameWarning` | 341 |
| `Generic.WhiteSpace.LanguageConstructSpacing.IncorrectSingle` | 164 |
| `WordPress.WhiteSpace.CastStructureSpacing.NoSpaceBeforeOpenParenthesis` | 158 |
| `Universal.Operators.StrictComparisons.LooseEqual` | 83 |
| `Squiz.PHP.EmbeddedPhp.ContentAfterOpen` | 73 |
| `Squiz.PHP.EmbeddedPhp.ContentBeforeEnd` | 72 |
| `WordPress.WhiteSpace.ControlStructureSpacing.NoSpaceBetweenStructureColon` | 63 |
| `Squiz.PHP.EmbeddedPhp.ContentBeforeOpen` | 62 |
| `Squiz.ControlStructures.ControlSignature.SpaceAfterCloseParenthesis` | 55 |
| `Generic.WhiteSpace.ScopeIndent.Incorrect` | 36 |
| `Universal.Operators.StrictComparisons.LooseNotEqual` | 30 |
| `WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound` | 23 |
| `PEAR.Functions.FunctionCallSignature.Indent` | 21 |
| `WordPress.WhiteSpace.OperatorSpacing.NoSpaceAfter` | 20 |
| `Squiz.Functions.MultiLineFunctionDeclaration.OneParamPerLine` | 11 |
| `Squiz.PHP.EmbeddedPhp.ContentAfterEnd` | 10 |
| `Squiz.ControlStructures.ControlSignature.SpaceAfterKeyword` | 8 |
| `Universal.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed` | 7 |
| `NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceAfterArrayOpenerMultiLine` | 7 |
| `WordPress.Arrays.ArrayDeclarationSpacing.ArrayItemNoNewLine` | 7 |
| `NormalizedArrays.Arrays.CommaAfterLast.MissingMultiLineCloserSameLine` | 7 |
| `NormalizedArrays.Arrays.ArrayBraceSpacing.SpaceBeforeArrayCloserMultiLine` | 7 |
| `WordPress.Arrays.ArrayIndentation.ItemNotAligned` | 6 |
| `PSR2.Classes.ClassDeclaration.SpaceBeforeName` | 5 |
| `Squiz.Functions.MultiLineFunctionDeclaration.ContentAfterBrace` | 3 |
| `Squiz.Functions.MultiLineFunctionDeclaration.UseOneParamPerLine` | 3 |
| `WordPress.Arrays.ArrayIndentation.CloseBraceNotAligned` | 2 |
| `PEAR.Files.IncludingFile.UseRequireOnce` | 1 |
| `Generic.Formatting.MultipleStatementAlignment.IncorrectWarning` | 1 |
| `Squiz.Strings.DoubleQuoteUsage.NotRequired` | 1 |
| `Generic.WhiteSpace.ScopeIndent.IncorrectExact` | 1 |
| `Universal.Operators.DisallowStandalonePostIncrementDecrement.PostIncrementFound` | 1 |
| `PSR2.Classes.ClassDeclaration.ImplementsLine` | 1 |
