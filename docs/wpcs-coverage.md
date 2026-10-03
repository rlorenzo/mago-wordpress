# WPCS coverage

The WPCS sniffs this package does not port, and what covers them instead: Mago's own WordPress
rules, `missing-docs` for `WordPress-Docs`, Mago's core rules and `mago analyze` for the generic
sniffs. The last section lists what nothing covers.

## Mago's own WordPress rules

Mago's core linter has its own `wordpress` integration: eight rules, independent of this package's
`wordpress/*` rules. Mago ships three disabled. The `extends` in [Install](../README.md#install) turns
on nothing: this package ports all of the WordPress security and database rules below as
`wordpress/*` rules, and the `extends` turns off the ones that ship on.

| Mago rule | Covers | Mago default |
|:---|:---|:---|
| `nonce-verification` | `WordPress.Security.NonceVerification`, ported as [`wordpress/nonce-verification`](rules.md) | off |
| `validated-sanitized-input` | `WordPress.Security.ValidatedSanitizedInput`, ported as [`wordpress/validated-sanitized-input`](rules.md) | off |
| `prepared-sql` | `WordPress.DB.PreparedSQL`; replaced by [`wordpress/prepared-sql`](rules.md), so the `extends` leaves it off | off |
| `no-unescaped-output` | `WordPress.Security.EscapeOutput`, ported as [`wordpress/escape-output`](rules.md); the `extends` turns this one off | on |
| `use-wp-functions` | `WordPress.WP.AlternativeFunctions`; the `extends` turns it off for [`wordpress/alternative-functions`](rules.md) | on |
| `no-direct-db-query` | `WordPress.DB.DirectDatabaseQuery` (`DirectQuery`, `NoCaching`); the `extends` turns it off for [`wordpress/direct-database-query`](rules.md) | on |
| `no-db-schema-change` | `WordPress.DB.DirectDatabaseQuery.SchemaChange`; the `extends` turns it off for [`wordpress/direct-database-query`](rules.md) | on |
| `no-roles-as-capabilities` | `WordPress.WP.Capabilities` (its role-checking part); the `extends` turns it off, since [`wordpress/capabilities`](rules.md) reports `RoleFound` | on |

Three core PHP rules, on for every project, cover the remaining `WordPress.PHP` sniffs
(`WordPress.PHP.PregQuoteDelimiter` is [`wordpress/preg-quote-delimiter`](rules.md); the `extends`
turns off Mago's `require-preg-quote-delimiter`):

| Mago rule | Covers |
|:---|:---|
| `no-error-control-operator` | `WordPress.PHP.NoSilencedErrors` |
| `no-ini-set` | `WordPress.PHP.IniSet` (partial: it reports every `ini_set()`, without WPCS's safe-option allowlist) |
| `no-debug-symbols` | `WordPress.PHP.DevelopmentFunctions` (partial; `wordpress/discouraged-wp-functions` covers the rest) |

## WordPress-Docs

The `WordPress` ruleset includes `WordPress-Docs`: eleven `Squiz.Commenting` and `Generic.Commenting`
sniffs, with the codes WPCS excludes. This package ports none of them.

Mago covers missing docblocks. The `extends` in [Install](../README.md#install) turns on Mago's
`missing-docs` for functions, methods, classes and properties, the declarations WPCS checks (not
interfaces, traits, enums or constants). On Akismet and Contact Form 7 it reports as many as
`FunctionComment`, `ClassComment` and `VariableComment` report as `Missing` or `WrongStyle` (156 and 512).

It reports at Mago's `help` level, so it does not fail a build. Raise it with
`missing-docs = { level = "warning" }` or turn it off with `{ enabled = false }`.
`mago-wordpress migrate` does the latter for a `WordPress-Core` or `WordPress-Extra` ruleset without
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

Mago's own core lint rules cover some of the rest. They run on every PHP project, regardless of the
`wordpress` integration, and `mago-wordpress migrate` maps a phpcs.xml exclusion of one of these
sniffs to its rule. Recall is measured on phpcs's own tests for the sniff (`bench/wpcs-parity.php`).
The Mago rules weren't written to mirror the sniffs, so most are close cousins rather than ports:

| WPCS sniff | Mago rule | Recall |
|:---|:---|---:|
| `Generic.CodeAnalysis.AssignmentInCondition` | `no-assign-in-condition` | 82% |
| `Generic.CodeAnalysis.EmptyPHPStatement` | `no-noop` | 76% |
| `Generic.CodeAnalysis.ForLoopShouldBeWhileLoop` | `prefer-while-loop` | 100% |
| `Generic.CodeAnalysis.UnconditionalIfStatement` | `constant-condition` | 40% |
| `Generic.CodeAnalysis.UnnecessaryFinalModifier` | `no-redundant-final` | 50% |
| `Generic.CodeAnalysis.UselessOverridingMethod` | `no-redundant-method-override` | 36% |
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
| `PEAR.NamingConventions.ValidClassName` | `class-name` | 21% |
| `PSR2.Files.ClosingTag` | `no-closing-tag` | 80% |
| `Squiz.PHP.DisallowMultipleAssignments` | `no-multi-assignments` | 22% |
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
| `Squiz.PHP.NonExecutableCode` | `unevaluated-code` |
| `Universal.Arrays.DuplicateArrayKey` | `duplicate-array-key` |
| `Generic.CodeAnalysis.EmptyStatement` | partly: `no-empty-loop` (lint) covers empty loops, not an empty `if` |
| `Universal.CodeAnalysis.ConstructorDestructorReturn` | partly: a semantics error for a return type on `__construct`/`__destruct`, not a `return $value;` inside one |

Enable these, and the rest of Mago's ~100 core rules, in `mago.toml`:

```toml
[linter.rules]
"no-assign-in-condition" = { enabled = true }
```

## Not ported

Formatting sniffs (whitespace, alignment, braces, quote style, keyword and tag casing not listed
above) are not ported. That is `mago format`'s job (see [Formatting](formatting.md)).

<details>
<summary>Remaining WPCS sniffs with no Mago equivalent</summary>

Generic sniffs that enforce a convention rather than catch a bug. Left unported; checked against
`mago lint` with every core rule on and `mago analyze`, and neither covers them:

- `Squiz.PHP.CommentedOutCode`
- `Squiz.Scope.MethodScope`
- `Universal.CodeAnalysis.NoDoubleNegative`
- `Universal.Namespaces.DisallowDeclarationWithoutName`
- `Universal.Namespaces.OneDeclarationPerFile`
- `Universal.NamingConventions.NoReservedKeywordParameterNames`
- `Universal.UseStatements.NoUselessAliases`

Low-value style sniffs, mostly formatting concerns `mago format` already makes moot:

- `Generic.Strings.UnnecessaryHeredoc`
- `Modernize.FunctionCalls.Dirname`
- `Modernize.FunctionCalls.Dirname.Nested`
- `PEAR.Files.IncludingFile`
- `PSR12.Files.FileHeader`
- `PSR12.Keywords.ShortFormTypeKeywords`
- `PSR2.Classes.PropertyDeclaration`
- `PSR2.ControlStructures.ElseIfDeclaration`
- `PSR2.Methods.MethodDeclaration`
- `Squiz.Classes.SelfMemberReference`
- `Squiz.Operators.IncrementDecrementUsage`
- `Squiz.Operators.ValidLogicalOperators`
- `Squiz.Strings.DoubleQuoteUsage`
- `Universal.Attributes.DisallowAttributeParentheses`
- `Universal.Classes.ModifierKeywordOrder`
- `Universal.CodeAnalysis.NoEchoSprintf`
- `Universal.CodeAnalysis.StaticInFinalClass`
- `Universal.Constants.LowercaseClassResolutionKeyword`
- `Universal.Constants.ModifierKeywordOrder`
- `Universal.Constants.UppercaseMagicConstants`
- `Universal.ControlStructures.DisallowLonelyIf`
- `Universal.Files.SeparateFunctionsFromOO`
- `Universal.Operators.DisallowStandalonePostIncrementDecrement`
- `Universal.PHP.LowercasePHPTag`
- `Universal.UseStatements.DisallowMixedGroupUse`
- `Universal.UseStatements.LowercaseFunctionConst`
- `Universal.UseStatements.NoLeadingBackslash`

</details>
