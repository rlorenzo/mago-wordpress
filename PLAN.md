# Gap plan: mago-wordpress as a full replacement for phpcs + WPCS

State as of 2026-09-30: `main` at v1.1.0 (37 rules; WPCS test-suite recall 89 %).
WPCS 3.4.1 is the current WPCS release (nothing unreleased). Every `WordPress.*` sniff that is
not a formatting sniff is covered by an extension rule or a Mago core rule, or is deliberately
outside the support cut-off below; the remaining gaps are in *how well* they replace phpcs, not
*whether* a rule exists.

## Support cut-off and defaults

The target is the out-of-the-box `WordPress` ruleset (Core + Docs + Extra) on code that runs on
**PHP 8.1+** and the WordPress versions that support it. Consequences:

- **Defaults mirror WPCS 3.4.1.** `minimum-wp-version` defaults to `6.7`, WPCS's own default
  (was `6.0`). Any rule whose sniff the `WordPress` ruleset excludes ships disabled or is not
  ported.
- **No rules for features PHP 8.1 no longer has.** `WordPress.PHP.POSIXFunctions` (functions
  removed in PHP 7.0; WPCS excludes it too) is not ported; the branch's port was dropped. The
  two shipped rules in this category, `restricted-php-functions` (`create_function`, gone in
  8.0) and the `(real)`/`(unset)` half of `type-casts`, stay because `WordPress-Core` runs them
  and removing shipped rules breaks 1.x users; Mago's analyzer reports the same calls as
  undefined on `php-version >= 8.1` anyway. Sprint F drops `Generic.PHP.DeprecatedFunctions`
  (PHP-level deprecations Mago's analyzer covers) and `Generic.PHP.DisallowAlternativePHPTags`
  (ASP/script tags, removed in PHP 7.0) for the same reason. The migration tool (Sprint C)
  ignores `PHPCompatibility.*` rules and `testVersion`.
- **WP deprecation lists are not trimmed.** A function deprecated in WP 2.8 is still a bug in
  WP 6.7 code and WPCS reports it (as an error once the minimum reaches it); the lists are gated
  data, not rules, and cost nothing to keep.
- **Deprecations are reported regardless of `minimum-wp-version`** (fixed 2026-09-30). WPCS
  reports every deprecated usage, as an error once the minimum reaches the deprecation and a
  warning before. Mago issues carry no per-issue level, so a not-yet-reached deprecation is
  reported at the rule's level with a note saying WPCS would lower it to a warning.
- **`namespace\foo()` relative calls are never matched** (fixed 2026-09-30, in `Calls`). Mago
  resolves them; WPCS cannot and documents it as a limitation to lift. Strict parity wins until
  WPCS lifts it, at which point removing the one check in `Calls::matchWanted` restores Mago's
  behaviour.

A team can drop phpcs + WPCS when all of these hold:

1. `mago lint` reports what `phpcs --standard=WordPress-Extra` (or `WordPress`) reports, with the
   same suppressions and per-project options.
2. `mago fmt` produces WordPress-Core formatting, or documents exactly where it diverges.
3. `mago lint --fix` covers what `phpcbf` fixes.
4. A `phpcs.xml` migrates to `mago.toml` mechanically.

Ordered by how many teams each gap blocks.

## Conventions for every PR

Unchanged from before: one sniff per rule class, `CallRule` for name-matched calls, rules read
`Settings` via constructor, corpus fixture per rule under `tests/corpus/rules/<code>/` with
`PhpcsCodes.php`, README table row, `just check` green.

**Bake-off per rule PR.** Every PR that adds or changes a rule re-runs `bench/run.sh` on the 11
bake-off codebases (`~/Projects/bakeoff-data`: top 10 plugins + wordpress-develop `src/`) and
commits the per-rule issue counts into `bench/results/<yyyy-mm>-bakeoff.md`. A new rule's counts
on real plugins are its false-positive review: triage every rule whose count jumps, spot-check
20 reports, fix the pattern before merge. Timings are refreshed in the README table and SVG when
the total changes by more than 5 %; counts always.

```
PHPCS=~/.config/composer/vendor/bin/phpcs bench/run.sh <dir> <text-domain> <prefix>
bench/profile-rules.sh --counts <dir> <text-domain> <prefix>   # before/after diff of a change
```

## Sprint A: suppression and option parity for Mago's core WordPress rules (launch blocker)

Eight WPCS sniffs are covered by Mago core rules (`nonce-verification`, `prepared-sql`,
`validated-sanitized-input`, `no-unescaped-output`, `use-wp-functions`, `no-direct-db-query`,
`no-db-schema-change`, `no-roles-as-capabilities`). Three of them are the reason teams run WPCS
at all, and they are the ones with the weakest parity:

- `// phpcs:ignore WordPress.Security.EscapeOutput` does not silence `no-unescaped-output`. The
  extension's phpcs-comment support only reaches its own rules. A codebase migrating with
  hundreds of existing ignores gets them all back as reports.
- `no-unescaped-output` has no `custom-escaping-functions` / `custom-auto-escaped-functions`;
  `prepared-sql` has no options. `Settings` parses those lists but nothing reads them.
- WPCS message codes (`EscapeOutput.OutputNotEscaped`, `NonceVerification.Missing`, …) are lost,
  so `<exclude name="…">` in phpcs.xml has no equivalent.

**A.1 Decide with upstream** (carthage-software/mago#2399, open since 2026-09-27). Two outcomes:

- Upstream moves the eight rules out of core → port them here (`wordpress/escape-output`,
  `wordpress/nonce-verification`, `wordpress/validated-sanitized-input`, `wordpress/prepared-sql`,
  `wordpress/alternative-functions`, `wordpress/direct-database-query`), reusing
  `ESCAPING_FUNCTIONS`, `AUTO_ESCAPED_FUNCTIONS`, `PRINTING_FUNCTIONS`, `SANITIZING_FUNCTIONS`,
  `UNSLASHING_*`, `ARRAY_WALKING_FUNCTIONS`, `ALTERNATIVE_FUNCTIONS` from `Lists.php`, the
  settings lists, and the shared linear scope scan (extract to `src/Internal/` when the second
  rule needs it). `wordpress.mago.toml` then stops enabling the core three.
- Upstream keeps them → send Mago PRs for the two option lists on `no-unescaped-output`, and
  accept the suppression gap; README states it plainly under "Coming from WPCS".

Set a deadline (two weeks from the issue date); if no answer, take the first path. Ports are
the higher-value outcome regardless, because they also restore message codes and phpcs comments.

**A.2 Verify core-rule parity — done** via `bench/wpcs-parity.php` (table under E.1): the four
security-critical core rules sit at 34–58% line recall on WPCS's own tests, with dozens of
extras. That is the case for porting them, whatever upstream decides.

## Sprint B: formatter preset

`WordPress-Core` is roughly two-thirds formatting sniffs (`WhiteSpace`, `Arrays`, brace and
spacing rules from `Generic`/`Squiz`/`PSR2`/`Universal`). Until B.1 the README said "that is
`mago fmt`'s job" but shipped no `[formatter]` block, so a team running `mago fmt` got Mago's
default style and then failed `phpcs --standard=WordPress-Core` on every file.

**B.1 `[formatter]` block in `wordpress.mago.toml`: done (2026-09-30).** Tabs, same-line braces
for functions, methods and classes, `! $x`, spaces inside grouping parentheses, `align-assignment-like`,
and preserved argument and parameter line breaks. All of these exist in Mago 1.47.1. It also sets
`array-style = { style = "long" }`: Mago's default of `short` flagged every `array()`, the opposite
of `Universal.Arrays.DisallowShortArraySyntax`. Measured on Akismet, Contact Form 7 and Yoast SEO
(`bench/results/2026-09-30-formatter.md`): 334,577 phpcs-fixable `WordPress-Core` reports after
`mago format` with Mago's defaults, 95,040 with the preset. Mago has no formatter option for
`array()`, the 60-column `=>` alignment limit, or unhugging the last argument.

**B.2 Divergences documented: done (2026-09-30).** The README's Formatting section groups the codes
that remain by cause: spaces inside parentheses and brackets are 92 % of what's left (refused
upstream, #446 and #490); then alignment limits (5 %), the hugged last argument (2 %), and
templates or alternative syntax (under 1 %).

**B.3 Upstream a `wordpress` preset** once B.1 stabilizes (issue #2399, question 2). A preset
survives Mago's option renames; a TOML block in this package does not.

## Sprint C: `phpcs.xml` migration

`SettingsDiscovery` reads sniff properties from `phpcs.xml`. It ignores everything else a real
ruleset carries:

| phpcs.xml element | Today | Mago equivalent |
|:---|:---|:---|
| `<exclude-pattern>` | ignored | `[source] excludes` |
| `<file>` | ignored | `[source] paths` |
| `<exclude name="WordPress.X.Y">` on a `<rule>` | ignored | `[linter.rules] "wordpress/x-y" = { enabled = false }` (needs the WPCS→rule map `Report` already has) |
| `<exclude name="…MessageCode">` | ignored | no equivalent; document |
| `<severity>`, `<type>` | ignored | `level` |
| `<config name="testVersion">` | ignored | `php-version` |
| `<arg name="extensions">`, `<arg name="parallel">` | ignored | n/a |
| `// phpcs:set Sniff prop value` inline | ignored | none; document |

What real rulesets contain (surveyed 2026-09-29: wordpress-develop, WooCommerce, Jetpack,
LiteSpeed Cache; release zips of the other bake-off plugins strip their `phpcs.xml.dist`):

- `<exclude-pattern>` dominates: 102 in wordpress-develop, 54 in WooCommerce. Straight map to
  `[source] excludes` (phpcs patterns are regex-ish globs; translate `*` and anchors).
- `<rule ref="WordPress.Files.FileName.InvalidClassFileName"><severity>0</severity>` or
  `<exclude-pattern>` scoped to a message code: disable or scope one WPCS message code. Maps to
  `{ enabled = false }` only when the code is the whole rule; a message-code exclusion inside a
  rule has no Mago equivalent unless the rule exposes it (see `Report` codes) — list these in
  the tool's "could not map" output.
- `<type>warning</type>` on an error sniff: maps to `{ level = "warning" }`.
- `<properties>`: `text_domain`, `prefixes`, `custom_capabilities`, `allowed_custom_properties`,
  `customAllowedFunctionsList` (WordPress.PHP.NoSilencedErrors), `minimum_wp_version`. The first
  three already map; the rest need `Settings` keys.
- `<rule ref="WordPress-Extra"/>` vs `WordPress-Core` vs `WordPress`: choose which Mago core
  rules and extension rules to enable (Extra-only rules off for a Core-only project).
- Custom standards (`WooCommerce-Core`, `Jetpack`, `PHPCompatibility`): report as unmapped.
- `<config name="testVersion">`, `<arg>`: ignored on purpose (PHP 8.1+ cut-off; CLI flags).

**C.1 `bin/mago-wordpress migrate`** (or `composer exec mago-wordpress-migrate`): reads
`phpcs.xml(.dist)`, writes `mago.toml` (`extends`, `[source] paths/excludes`, disabled rules,
levels) and the `extra.mago-wordpress` block into `composer.json`, and prints every element it
could not map with the reason. `Report`'s sniff→rule map is the source of truth; expose it
(`Report::rulesFor(sniff)`). Dry-run by default; `--write` to apply. Verify by running the tool
on wordpress-develop's and WooCommerce's rulesets and diffing `mago lint` before/after against
`phpcs` on the same tree.

**C.2 Migration guide** in the README, driven by the tool's output on those two rulesets.

## Sprint D: `phpcbf` parity

WPCS fixes five things; the extension fixes one:

| WPCS fixable | Extension |
|:---|:---|
| `PHP.TypeCasts` (`(double)`→`(float)`) | fixed |
| `WP.CapitalPDangit` (`Wordpress`→`WordPress`) | report only |
| `DateTime.CurrentTimeTimestamp` (`current_time('timestamp')`→`time()`) | report only |
| `WP.I18n.MissingSingularPlaceholder` / `TranslatorsCommentWrongStyle` | report only |
| `Utils.I18nTextDomainFixer` | out of scope (migration tool) |

**D.1 Add `withEdit()`** to `capital-p-dangit` (string and comment spans, `Safety::Safe`),
`wp-date-time` (the timestamp form, `Safety::Safe`; the `'U'` form maps to `time()` too),
`wp-i18n` translators-comment style (`Safety::Unsafe`, comment rewrite). Corpus fixtures assert
the edit text via `mago lint --fix` on a copy, or by a `@mago-expect` plus a PHPUnit test of the
issue's edits if the corpus runner cannot see edits.

## Sprint E: parity inside ported rules

Known, documented shortfalls against the WPCS sniff each rule ports. Each is a small PR; each
gets a WPCS `.inc` case added to the corpus.

- `wordpress/cron-interval`: only inline closures are inspected; WPCS follows a named callback
  declared in the same file. Resolve function/method names to declarations in the file.
- `wordpress/posts-per-page`, `wordpress/slow-db-query`: any array literal is checked; an
  `$args` variable passed to `WP_Query`/`get_posts` is not followed. WPCS is also literal-only,
  so this is parity today; only add if the bake-off shows misses.
- `wordpress/capabilities`: WPCS's `Undetermined` warning (non-literal capability) is hidden by
  phpcs's default severity and not ported. Leave it.
- `wordpress/valid-variable-name`: WPCS's `allowed_custom_properties` property (properties on
  objects the project does not control) is not read; add it to `Settings` and `PhpcsRuleset`.
- `wordpress/discouraged-wp-functions`, `db-restricted-*`, `wp-date-time` and every other
  `AbstractFunctionRestrictionsSniff` port: WPCS's per-sniff `exclude` property drops named
  groups (`<element value="obfuscation"/>`). Not read; add `exclude-groups` keyed by sniff.
- Defaults mirror the out-of-the-box `WordPress` ruleset (Core + Docs + Extra). That is why
  `wordpress/posix-functions` ships disabled: the `WordPress` ruleset excludes that sniff.
  Any future rule whose sniff is not in `WordPress` ships disabled too.
- `wordpress/file-name`: WPCS properties `strict_class_file_names` (default true) and `is_theme` are not read.

**E.1 WPCS `.inc` sweep — done (2026-09-30).** `bench/wpcs-parity.php` runs every WPCS sniff's
own test files through the mapped rule; results in `bench/results/wpcs-parity.md`. Suite recall
went 76% → 89% over four waves of rule fixes (PRs #2 and #3). Every extension rule with a WPCS
test is at 100% except the accepted cases: test-only sniff groups (`RestrictedClasses` .1/.2/.3,
`RestrictedFunctions` line 94), `phpcs:set exclude[]` / `custom_test_classes` /
`treat_files_as_scoped` (no setting; candidates for Sprint C), WPCS's deliberate parse-error
files, and severity-3 "undetermined" warnings phpcs hides at its default severity
(`Capabilities`, `ValidPostTypeSlug`). What remains short is Mago's core rules, which is Sprint
A's evidence:

| Core rule | WPCS sniff | Recall |
|:---|:---|---:|
| `validated-sanitized-input` | `Security.ValidatedSanitizedInput` | 34% |
| `use-wp-functions` | `WP.AlternativeFunctions` | 32% (no `minimum_wp_version` gating) |
| `no-unescaped-output` | `Security.EscapeOutput` | 57% (75 missed, 65 extra) |
| `nonce-verification` | `Security.NonceVerification` | 58% |
| `require-preg-quote-delimiter` | `PHP.PregQuoteDelimiter` | 80% |
| `prepared-sql` | `DB.PreparedSQL` | 85% |
| `no-direct-db-query` | `DB.DirectDatabaseQuery` | 97% |

Re-run after every rule change; a row that drops is a regression.

## Sprint F: generic sniffs that `WordPress-Extra` pulls in

The README lists 24 non-`WordPress.*` sniffs with no Mago rule. Several are analyzer findings
in Mago rather than lint rules, so the README's list needs re-checking against `mago analyze`
output, and the rest need a decision:

- Likely covered by `mago analyze` already (verify, then move to the covered table):
  `Generic.PHP.Syntax`, `Generic.PHP.DeprecatedFunctions`, `Generic.CodeAnalysis.UnusedFunctionParameter`,
  `Squiz.PHP.NonExecutableCode`, `Universal.Arrays.DuplicateArrayKey`, `Generic.Classes.DuplicateClassName`,
  `Squiz.Functions.FunctionDuplicateArgument`, `Universal.CodeAnalysis.ConstructorDestructorReturn`.
- Worth porting as cheap `Rule`s (real bugs, no Mago equivalent):
  `Generic.CodeAnalysis.JumbledIncrementer`, `Generic.CodeAnalysis.RequireExplicitBooleanOperatorPrecedence`,
  `Squiz.PHP.DisallowSizeFunctionsInLoops`, `Generic.CodeAnalysis.ForLoopWithTestFunctionCall`,
  `Universal.CodeAnalysis.ForeachUniqueAssignment`, `Generic.VersionControl.GitMergeConflict`,
  `Generic.Files.ByteOrderMark`, `Generic.PHP.DisallowAlternativePHPTags`.
  Batch as `generic/*` rule codes so they can be disabled as a group.
- Leave unported (style, or `mago fmt` makes them moot): the rest of the README list.

## Sprint G: `WordPress-Docs`

Nothing is ported. Map what exists: Mago `valid-docblock` and `missing-docs` cover the
structural half of `Squiz.Commenting.FunctionComment` / `Generic.Commenting.DocComment`.
Document the mapping and the unported half (`@since` tags, `FileComment`, capitalisation) in
the README. Port only if a team asks; WordPress core is the main consumer of `WordPress-Docs`.

## Sprint H: maintenance

- **`Lists.php` generator**: done (2026-09-30), `bin/generate-lists.php <wpcs-dir> [--check]`
  regenerates `Lists.php` and `CoreClasses.php` from the WPCS source (tokenizer, no phpcs
  needed). The original script was never committed and was lost; the rewrite reproduces the
  committed files byte-for-byte except `post_id`, which WPCS lists beside `post_ID` and the
  old script had deduped away (phpcs flags `$post_id` overrides, so this is a parity fix).
- **WPCS version tracking**: done, `wpcs-lists` CI job clones the `WPCS_VERSION` tag (pinned in
  `bin/generate-lists.php`), runs `--check`, and fails when WPCS tags a newer release.
- **Mago version tracking**: done, `latest-mago` CI job (beside `lowest-mago`) runs `just check`
  after `composer update carthage-software/mago`.
- **Bake-off automation**: done, `bench/bakeoff.sh` (2026-09-29); `--report` regenerates the
  markdown from saved outputs.
- **`phpcs.xml` config name**: `PhpcsRuleset` now reads WPCS 3's `minimum_wp_version` as well as
  the pre-3.0 `minimum_supported_wp_version` (fixed 2026-09-29).
- **Worker crash**: one of three timed runs on WordPress core (1,868 files) died with
  `zend_mm_heap corrupted` in a PHP extension worker on 2026-09-29 (PHP 8.4.24, Mago 1.50.0).
  Not reproduced on the re-run. Try to reproduce under `USE_ZEND_ALLOC=0` and valgrind, or
  with `workers = 1`; if it is a PHP `ext-dom`/opcache issue, document the workaround, else file
  it upstream with the worker protocol dump.

## Done since the previous plan

Everything in the previous PLAN.md shipped in 1.0.x except PR 1.5 (custom escapers; now
Sprint A) and the list generator (Sprint H). `WordPress.PHP.POSIXFunctions` was ported and then
dropped on 2026-09-29 under the support cut-off above. README rows for the four Mago core PHP
rules (`no-ini-set`, `require-preg-quote-delimiter`, `no-error-control-operator`,
`no-debug-symbols`) were added the same day.
