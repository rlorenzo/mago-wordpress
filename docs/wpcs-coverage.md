# WPCS coverage

What each preset runs, then the WPCS sniffs this package does not port and what covers them
instead: Mago's own WordPress rules, `missing-docs` for `WordPress-Docs`, Mago's core rules and
`mago analyze` for the generic sniffs. The last section lists what nothing covers.

## What each standard runs

The three presets in [Install](../README.md#install) match the three WPCS standards. Each runs the
rules for the sniffs its standard includes and turns the rest off: this package's rules through the
[`standard`](configuration.md#settings) setting, Mago's core rules in the preset file.

- `WordPress-Core` (`wordpress-core.mago.toml`): the `WordPress-Core` sniffs, such as `Files.FileName`,
  `NamingConventions.Valid*Name`, `PHP.YodaConditions`, `PHP.StrictInArray`, `DB.PreparedSQL`,
  `WP.I18n`, plus the generic ones it pulls in.
- `WordPress-Extra` (`wordpress-extra.mago.toml`): Core, plus `CodeAnalysis.EscapedNotTranslated`,
  `NamingConventions.PrefixAllGlobals`, `NamingConventions.ValidPostTypeSlug`,
  `PHP.DevelopmentFunctions`, `PHP.DiscouragedPHPFunctions`, `PHP.IniSet`, `PHP.PregQuoteDelimiter`,
  `Security.EscapeOutput`, `Security.NonceVerification`, `Security.PluginMenuSlug`,
  `Security.SafeRedirect`, `WP.AlternativeFunctions`, `WP.Capabilities`, `WP.CronInterval`,
  `WP.Deprecated*`, `WP.DiscouragedConstants`, `WP.DiscouragedFunctions`,
  `WP.EnqueuedResourceParameters`, `WP.EnqueuedResources`, `WP.GetMetaSingle`,
  `WP.GlobalVariablesOverride`, `WP.PostsPerPage` (all `WordPress.*`), and the generic sniffs
  `ForLoopShouldBeWhileLoop`, `ForLoopWithTestFunctionCall`, `JumbledIncrementer`,
  `RequireExplicitBooleanOperatorPrecedence`, `UnconditionalIfStatement`, `UnnecessaryFinalModifier`,
  `UselessOverridingMethod`, `ForbiddenFunctions`, `UnnecessaryStringConcat`,
  `DisallowSizeFunctionsInLoops`, `ForeachUniqueAssignment`, `EmptyStatement`, `NonExecutableCode`,
  `StaticInFinalClass` and `SeparateFunctionsFromOO`.
- `WordPress` (`wordpress.mago.toml`): Extra, plus `WordPress-Docs` (`missing-docs`, below) and the
  three sniffs no group lists: `DB.DirectDatabaseQuery`, `DB.SlowDBQuery` and
  `Security.ValidatedSanitizedInput`.

All three turn off the Mago core rules that report what no WPCS sniff does (`strict-types`,
`no-isset`, `no-empty`, `literal-named-argument`, `cyclomatic-complexity`, `halstead`, `file-name`,
`no-request-variable`, `no-literal-password`, `no-insecure-comparison`, ... about ninety, among them
`class-name`, which wants PascalCase where PEAR's `ValidClassName`, ported as
`generic/valid-class-name`, accepts `My_Class`), and one that is mapped to a sniff but reports code
it accepts: `no-closing-tag` (`PSR2.Files.ClosingTag` skips files with inline HTML, so
templates). Turn any of them back on in your `mago.toml`, for example
`strict-types = { enabled = true }`.

Four security rules no WPCS sniff runs stay on, because they make code safer and almost never fire
(2 reports across the 10 bake-off plugins, wordpress-develop and one private site):
`tainted-data-to-sink`, `no-unsafe-finally`, `no-variable-variable` and `no-ffi`.
`no-request-variable` stays off: 1,380 reports there, mostly false positives, and it overlaps
`wordpress/validated-sanitized-input`. So do `no-literal-password` and `no-insecure-comparison`,
which match option names and any variable named `token` or `key`.

The core rules a preset keeps report at the level phpcs gives their sniff (`constant-name` and
`single-class-per-file` are errors, `no-goto` a warning). `bin/generate-presets.php` derives all of
this from `SniffMap`, `src/Internal/WordPress/Levels.php` and Mago's default rules.

The ported sniff lists match WPCS 3.4.1's `ruleset.xml` files, including
`WordPress.PHP.NoSilencedErrors` (`wordpress/no-silenced-errors`): Core allows `@` before the PHP
functions WPCS lists (`is_file()`, `fopen()`, `unserialize()`, ...), Extra and the full standard
don't. One difference inside a standard: Mago's core rules don't read `phpcs:ignore` comments.

## Mago's own WordPress rules

Mago's core linter has its own `wordpress` integration: eight rules, independent of this package's
`wordpress/*` rules, that run only when `[linter] integrations = ["wordpress"]` is set. The presets
don't set it: this package ports all of the WordPress security and database rules below as
`wordpress/*` rules. "Mago default" is with the integration on.

| Mago rule | Covers | Mago default |
|:---|:---|:---|
| `nonce-verification` | `WordPress.Security.NonceVerification`, ported as [`wordpress/nonce-verification`](rules.md) | off |
| `validated-sanitized-input` | `WordPress.Security.ValidatedSanitizedInput`, ported as [`wordpress/validated-sanitized-input`](rules.md) | off |
| `prepared-sql` | `WordPress.DB.PreparedSQL`, ported as [`wordpress/prepared-sql`](rules.md) | off |
| `no-unescaped-output` | `WordPress.Security.EscapeOutput`, ported as [`wordpress/escape-output`](rules.md) | on |
| `use-wp-functions` | `WordPress.WP.AlternativeFunctions`, ported as [`wordpress/alternative-functions`](rules.md) | on |
| `no-direct-db-query` | `WordPress.DB.DirectDatabaseQuery` (`DirectQuery`, `NoCaching`), ported as [`wordpress/direct-database-query`](rules.md) | on |
| `no-db-schema-change` | `WordPress.DB.DirectDatabaseQuery.SchemaChange`, ported as [`wordpress/direct-database-query`](rules.md) | on |
| `no-roles-as-capabilities` | `WordPress.WP.Capabilities` (its role-checking part); [`wordpress/capabilities`](rules.md) reports `RoleFound` | on |

Two core PHP rules cover the remaining `WordPress.PHP` sniffs; the Core preset turns them off,
as their sniffs are Extra-only (`WordPress.PHP.PregQuoteDelimiter` is
[`wordpress/preg-quote-delimiter`](rules.md); the presets turn off Mago's
`require-preg-quote-delimiter`):

| Mago rule | Covers |
|:---|:---|
| `no-ini-set` | `WordPress.PHP.IniSet` (partial: it reports every `ini_set()`, without WPCS's safe-option allowlist) |
| `no-debug-symbols` | `WordPress.PHP.DevelopmentFunctions` (partial; `wordpress/discouraged-wp-functions` covers the rest) |

## WordPress-Docs

The `WordPress` ruleset includes `WordPress-Docs`: eleven `Squiz.Commenting` and `Generic.Commenting`
sniffs, with the codes WPCS excludes. This package ports none of them.

Mago covers missing docblocks. `wordpress.mago.toml` turns on Mago's `missing-docs` for functions, methods, classes and properties, the declarations WPCS checks (not
interfaces, traits, enums or constants). On Akismet and Contact Form 7 it reports as many as
`FunctionComment`, `ClassComment` and `VariableComment` report as `Missing` or `WrongStyle` (156 and 512).

It reports at Mago's `help` level, so it does not fail a build. Raise it with
`missing-docs = { level = "warning" }` or turn it off with `{ enabled = false }`. The
`wordpress-core` and `wordpress-extra` presets turn it off, as those standards don't include
`WordPress-Docs`.

Mago mostly doesn't check what a docblock *contains* (tags, types, capitalisation, full stops):

| Sniff | Mago | Not covered |
|:---|:---|:---|
| `Squiz.Commenting.FunctionComment` | `Missing`: `missing-docs`. `ParamNameNoMatch`, `ExtraParamComment`: `mago analyze` (`invalid-param-tag`). Malformed types: `valid-docblock` | `MissingParamTag`, `MissingParamType`, `MissingParamComment`, `ParamCommentFullStop`, `ParamNameNoCaseMatch`, return and `@throws` tag checks, `WrongStyle` (a `//` comment where a docblock should be) |
| `Squiz.Commenting.ClassComment` | `Missing`: `missing-docs` | `WrongStyle`, `SpacingAfter` |
| `Squiz.Commenting.VariableComment` | `Missing`: `missing-docs` | `MissingVar`, `EmptyVar`, `DuplicateVar`, `EmptySees`, `WrongStyle` |
| `Squiz.Commenting.FileComment` | none | every code: a file docblock, its `@package` tag, spacing and style |
| `Squiz.Commenting.FunctionCommentThrowTag` | partial: `mago analyze` with `check-throws = true` (off by default) reports unhandled exceptions, not a missing `@throws` tag | `Missing`, `WrongNumber` |
| `Squiz.Commenting.EmptyCatchComment` | not equivalent: `no-empty-catch-clause` (on) reports an empty `catch` even when it holds a comment, which is what WPCS asks for | |
| `Squiz.Commenting.InlineComment` | `WrongStyle` (`#`): `no-hash-comment`. `Empty`: `no-empty-comment` | `InvalidEndChar` (full stop), `NoSpaceBefore`, `SpacingBefore`, `TabBefore` |
| `Squiz.Commenting.BlockComment` | `Empty`: `no-empty-comment` | `NoCapital`, `WrongEnd`, `NoNewLine`, blank-line checks |
| `Squiz.Commenting.DocCommentAlignment` | `SpaceBeforeStar`: `mago format` | `NoSpaceAfterStar` |
| `Squiz.Commenting.ClosingDeclarationComment` | none | reports only malformed `//end` comments; WPCS excludes `Missing` |
| `Generic.Commenting.DocComment` | `Empty`: `no-empty-comment` | `MissingShort`, `ShortNotCapital`, `LongNotCapital`, spacing between the short and long description and tags |

WPCS doesn't check `@since`, which the WordPress documentation standard asks for. `valid-docblock`
(on) reports docblock syntax errors WPCS doesn't, such as an unclosed `{@see`.

## Generic sniffs

`WordPress-Extra` and `WordPress-Core` also pull in generic (non-`WordPress.*`) sniffs from
`Generic`, `PEAR`, `PSR2`, `Squiz` and `Universal`. The bug-catching ones nothing else covers are
the `generic/*` rules in [Rules](rules.md).

Mago's own core lint rules cover some of the rest. They don't need the `wordpress` integration;
each preset keeps the ones its standard's sniffs map to, at the sniff's phpcs level (except
`no-closing-tag`, above), and `mago-wordpress migrate` maps a phpcs.xml exclusion of one of these
sniffs to its rule. Recall is measured on phpcs's own tests for the sniff (`bench/wpcs-parity.php`).
The Mago rules weren't written to mirror the sniffs, so most are close cousins rather than ports:

| WPCS sniff | Mago rule | Recall |
|:---|:---|---:|
| `Generic.CodeAnalysis.EmptyPHPStatement` | `no-noop` | 76% |
| `Generic.CodeAnalysis.ForLoopShouldBeWhileLoop` | `prefer-while-loop` | 100% |
| `Generic.CodeAnalysis.UnnecessaryFinalModifier` | `no-redundant-final` | 50% |
| `Generic.Files.OneObjectStructurePerFile` | `single-class-per-file` | 100% |
| `Generic.NamingConventions.UpperCaseConstantName` | `constant-name` | 46% |
| `Generic.PHP.BacktickOperator` | `no-shell-execute-string` | 100% |
| `Generic.PHP.DisallowShortOpenTag` | `no-short-opening-tag` | 67% |
| `Generic.PHP.DiscourageGoto` | `no-goto` | 100% |
| `Generic.PHP.ForbiddenFunctions` | `disallowed-functions` (lists no functions until configured) | 0% |
| `Generic.PHP.LowerCaseConstant` | `lowercase-keyword` | 92% |
| `Generic.PHP.LowerCaseKeyword` | `lowercase-keyword` | 93% |
| `Generic.PHP.LowerCaseType` | `lowercase-type-hint` | 67% |
| `Generic.Strings.UnnecessaryStringConcat` | `no-redundant-string-concat` | 57% |
| `PSR2.Files.ClosingTag` | `no-closing-tag` | 80% |
| `Squiz.PHP.Eval` | `no-eval` | 100% |
| `Universal.Arrays.DisallowShortArraySyntax` | `array-style` (set to `long` by the shipped config) | - |
| `Universal.Operators.DisallowShortTernary` | `no-shorthand-ternary` | - |

`mago analyze` covers these. They are analyzer issue codes, not lint rules, so the migration tool
doesn't map them:

| WPCS sniff | `mago analyze` |
|:---|:---|
| `Generic.Classes.DuplicateClassName` | `duplicate-definition` (across the whole codebase) |
| `Generic.CodeAnalysis.UnusedFunctionParameter` | `unused-parameter`, with `find-unused-parameters = true` under `[analyzer]` |
| `Generic.PHP.DeprecatedFunctions` | `deprecated-function` |
| `Generic.PHP.Syntax` | parse errors |
| `Squiz.Functions.FunctionDuplicateArgument` | a semantics error |
| `Universal.Arrays.DuplicateArrayKey` | `duplicate-array-key` |
| `Universal.CodeAnalysis.ConstructorDestructorReturn` | partly: a semantics error for a return type on `__construct`/`__destruct`, not a `return $value;` inside one |

Enable these, and any core rule the presets turn off, in `mago.toml`:

```toml
[linter.rules]
"no-request-variable" = { enabled = true }
```

## Not ported

Formatting sniffs (whitespace, alignment, braces, quote style, keyword and tag casing not listed
above) are not ported. That is `mago format`'s job (see [Formatting](formatting.md)).

<details>
<summary>Remaining WPCS sniffs with no Mago equivalent</summary>

Generic sniffs that enforce a convention rather than catch a bug. Left unported; checked against
`mago lint` with every core rule on and `mago analyze`, and neither covers them:

- `Squiz.PHP.CommentedOutCode`
- `Universal.Namespaces.DisallowDeclarationWithoutName`
- `Universal.Namespaces.OneDeclarationPerFile`
- `Universal.UseStatements.NoUselessAliases`

Low-value style sniffs, mostly formatting concerns `mago format` already makes moot:

- `Modernize.FunctionCalls.Dirname`
- `Modernize.FunctionCalls.Dirname.Nested`
- `PEAR.Files.IncludingFile`
- `PSR12.Files.FileHeader`
- `PSR12.Keywords.ShortFormTypeKeywords`
- `Universal.Attributes.DisallowAttributeParentheses`
- `Universal.Classes.ModifierKeywordOrder`
- `Universal.Constants.LowercaseClassResolutionKeyword`
- `Universal.Constants.ModifierKeywordOrder`
- `Universal.Constants.UppercaseMagicConstants`
- `Universal.PHP.LowercasePHPTag`
- `Universal.UseStatements.DisallowMixedGroupUse`
- `Universal.UseStatements.LowercaseFunctionConst`
- `Universal.UseStatements.NoLeadingBackslash`

</details>
