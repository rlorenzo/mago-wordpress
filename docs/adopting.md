# Adopting mago-wordpress in a phpcs project

This is the path for a project that already runs phpcs with WPCS in CI and in a pre-commit hook.
Each step lists its commands. All of them were run on Mago 1.51.0 against a phpcs-clean WordPress
site (theme + mu-plugins, about 100 PHP files), and the numbers below come from that run. The
per-rule counts for that site are in [the adoption benchmark](../bench/results/2026-10-01-adoption.md).

## 1. Install

```sh
composer remove --dev squizlabs/php_codesniffer wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
composer require --dev carthage-software/mago rlorenzo/mago-wordpress
```

The first `vendor/bin/mago` run downloads the Mago binary for your platform (about 10 MB). Keep
`phpcs.xml` until step 2 has read it.

## 2. Generate the config

```sh
vendor/bin/mago-wordpress migrate          # dry run: prints mago.toml, the composer.json block, and what it can't migrate
vendor/bin/mago-wordpress migrate --write  # writes them
```

`mago.toml` gets an `extends` of the preset that matches the standard in your `phpcs.xml`
(`wordpress-core.mago.toml`, `wordpress-extra.mago.toml` or `wordpress.mago.toml`), your `<file>`
paths, and the complexity, nesting and docblock sniffs as Mago rules. Settings and exclusions go
into `composer.json` under `extra.mago-wordpress`. Read the "Not migrated" list it prints. What
each line means: [Migrating from phpcs](migrating.md).

Once `composer.json` has an `extra.mago-wordpress` block, the worker stops reading `phpcs.xml`.
Deleting `phpcs.xml` (and any ruleset it includes) changed nothing on the sample site.

Without a `phpcs.xml`, write the `extends` line yourself ([Install](../README.md#install)) and put
your `text-domains` and `prefixes` in `composer.json` ([Configuration](configuration.md)).

## 3. Measure before you fix

```sh
vendor/bin/mago lint --reporting-format code-count
```

This prints one line per rule, such as `warning[wordpress/nonce-verification-warning]: 20`. On the sample site,
phpcs had reported 0 errors and hidden 1,027 warnings with `-n`. After `migrate` alone, Mago
reported 83 issues in 9 rules. Most of the 1,027 were alignment warnings that formatting handles
(step 7).

Expect three kinds of rows: warnings phpcs hid, settings you never had in `phpcs.xml`, and rules
that count differently (step 9). The common ones:

| Row | Usual cause | Knob |
|:---|:---|:---|
| `wordpress/*` warnings you never saw | phpcs ran with `-n` | `--minimum-report-level error` (step 6), or `levels` |
| `wordpress/prefix-all-globals` in the hundreds | `prefixes` set now, never in `phpcs.xml` (sample: 0 → 606 after adding one prefix) | fix, or `exclude-patterns` per message code ([theme templates](rules.md#prefix-all-globals-in-theme-templates)) |
| `wordpress/wp-i18n` | `text-domains` set now, never in `phpcs.xml` (sample: 0 → 27) | fix, or `exclude-patterns` |
| `wordpress/file-name` | file-name exclusions missing from `exclude-patterns` | `exclude-patterns` |
| rules for sniffs your standard didn't run | wrong preset | `standard`, or `extends` of the right preset |
| `cyclomatic-complexity` | Mago counts `&&` and `\|\|` and scores classes (sample: 29, phpcs 12) | `threshold`, `method-threshold`, `exclude` |
| `excessive-nesting` | Mago counts the function body as a level | `threshold` |
| `missing-docs` | a function preceded by a `//` comment, not a docblock | `level`, `exclude`, `enabled = false` |
| `unfulfilled-expect` | a pragma for a rule that no longer reports there | remove the pragma |

This package's rules are tuned in `composer.json`, because `[linter.rules]` rejects `wordpress/*`
codes:

```json
{
  "extra": {
    "mago-wordpress": {
      "standard": "WordPress-Extra",
      "levels": { "wordpress/nonce-verification": "warning" },
      "exclude-patterns": {
        "WordPress.WP.AlternativeFunctions.json_encode_json_encode": ["*"],
        "WordPress.WP.DiscouragedFunctions": ["*/template_parts/*"]
      }
    }
  }
}
```

- `standard` picks which `wordpress/*` rules run (`WordPress-Core`, `WordPress-Extra`, `WordPress`).
- `levels` sets a rule's level. A misspelled rule code stops the run with exit code 2 and names the entry.
- `exclude-patterns` maps a WPCS code (sniff or message code) to phpcs-style patterns. Add to the
  block `migrate` wrote rather than replacing it: on the sample site, a new block without the
  migrated entries brought back 39 `wordpress/file-name` reports.

Some WPCS sniffs report both errors and warnings. Their warning-level messages are reported by a
companion rule named `<rule>-warning`, so each half has its own level: `NonceVerification.Missing`
is `wordpress/nonce-verification` (error), `NonceVerification.Recommended` is
`wordpress/nonce-verification-warning`. See [Rules](rules.md).

Mago's own rules are tuned in `mago.toml`:

```toml
[linter.rules]
cyclomatic-complexity = { enabled = true, threshold = 12, method-threshold = 12, level = "warning" }
```

Raising both thresholds from 8 to 12 took the sample from 29 reports to 6.

If you change `standard` after step 4, pragmas for rules the new standard drops report
`unfulfilled-expect` (39 on the sample when switching to `WordPress-Core`).

## 4. Convert suppression comments

This package's rules honour `phpcs:ignore` and `phpcs:disable`, but Mago's own rules don't, and a
stale phpcs comment never reports. Convert them to Mago pragmas:

```sh
vendor/bin/mago-wordpress convert-comments          # dry run: one line per comment
vendor/bin/mago-wordpress convert-comments --write  # rewrite, re-lint, fail if a pragma is unfulfilled
```

On the sample: 39 converted, 4 regions, 2 kept as plain comments, 1 dropped, issue counts unchanged.
Then set `"honor-phpcs-comments": false` in `extra.mago-wordpress`, so a leftover phpcs comment no
longer hides anything. How each comment is converted: [Migrating](migrating.md#converting-phpcs-comments-to-mago-pragmas).

Pragma cheat sheet:

| Pragma | Effect |
|:---|:---|
| `// @mago-expect lint:<rule>` on the line before | the next statement must have that issue; it is suppressed. If it has none, a `unfulfilled-expect` warning |
| `// @mago-expect lint:<rule> -- reason` | the same, with a reason |
| `// @mago-expect lint:<rule>(3)` | expects 3 issues; fewer gives "only partially fulfilled" |
| `// @mago-ignore lint:<rule>` | suppresses without expecting; an unused one is an `unused-pragma` note |
| ` * @mago-expect lint:<rule>(5)` in a function's docblock | covers the whole function |
| `<?php echo x(); // @mago-expect lint:<rule> ?>` | after code on the same line, in a template |
| `<?php` / `// @mago-expect lint:<rule>` / code / `?>` on separate lines | inline HTML; a one-line `<?php // ... ?>` block doesn't reach the HTML after it |

`phpcs:ignoreFile` has no Mago equivalent. Use `[source] excludes` in `mago.toml` or `exclude-patterns`.

## 5. Baseline what's left

```toml
# mago.toml
[linter]
baseline = "linter-baseline.toml"
```

```sh
vendor/bin/mago lint --generate-baseline
```

With the path in `mago.toml`, plain `mago lint` applies the baseline and `--generate-baseline`
needs no `--baseline` flag. Without it, `--generate-baseline` only prints a warning and exits 1.

The default baseline is `loose`: it matches an issue by file, rule and message with a count, not by
line. What happened on the sample site after baselining all 83 issues:

| Change | Result |
|:---|:---|
| 5 lines inserted above baselined issues | still suppressed; `--verify-baseline` exits 0 |
| whole site reformatted (step 7) | still suppressed |
| a line with a baselined issue duplicated in the same file | the extra issue is reported |
| a new issue in a baselined file | reported |
| a file with 2 baselined issues deleted | `mago lint` exits 0; `--fail-on-out-of-sync-baseline` and `--verify-baseline` exit 1 |
| then `--remove-outdated-baseline-entries` | drops the 2 stale entries, adds nothing, exits 0 |

`--verify-baseline` also fails when there is any issue the baseline doesn't list, even a `help`.
Run `vendor/bin/mago lint --ignore-baseline` to see everything, and `--generate-baseline` again
after a cleanup.

## 6. CI and hooks

`mago lint` exits 0 when nothing reaches the fail level, 1 when something does, and 2 when the run
itself fails (a bad setting, a worker that won't start). The fail level is `error` by default.

| phpcs | Mago |
|:---|:---|
| `phpcs` (fails on warnings) | `mago lint --minimum-fail-level warning` |
| `phpcs -n` | `mago lint --minimum-report-level error` |
| `--report=emacs` | `--reporting-format short` (`file:line:col: level[rule]: message`) |
| GitHub annotations | `--reporting-format github` |

On the sample, a run with only warnings exited 0 by default and 1 with `--minimum-fail-level warning`.

CI:

```sh
vendor/bin/mago lint --minimum-fail-level warning --reporting-format github
vendor/bin/mago-wordpress format --check   # if you adopt the formatter (step 7)
```

Add `--fail-on-out-of-sync-baseline` to the lint step to make people prune the baseline as they fix
things.

A pre-commit hook that lints the staged files whole:

```sh
vendor/bin/mago lint --staged --minimum-fail-level warning
```

To fail only on issues in the lines a commit adds or changes, save this as `.git/hooks/pre-commit`
(or call it from your hook manager):

```bash
#!/usr/bin/env bash
# Fails only on Mago issues in the lines this commit adds or changes.
set -uo pipefail
failed=0
while IFS= read -r file; do
    # "@@ -a,b +start,count @@": the staged lines are start .. start+count-1 (count defaults to 1).
    lines=$(git diff --cached --unified=0 -- "$file" \
        | sed -nE 's/^@@ [^+]*\+([0-9]+)(,([0-9]+))? @@.*/\1 \3/p' \
        | while read -r start count; do seq "$start" $((start + ${count:-1} - 1)); done)
    [[ -n $lines ]] || continue
    # Match "file:line:" exactly; a bare ":line:" would also match column numbers.
    issues=$(vendor/bin/mago lint --minimum-report-level warning --reporting-format short "$file" 2>/dev/null \
        | awk -F: -v file="$file" 'NR == FNR { changed[$1]; next } $1 == file && $2 in changed' <(echo "$lines") -)
    if [[ -n $issues ]]; then echo "$issues"; failed=1; fi
done < <(git diff --cached --name-only --diff-filter=ACMR -- '*.php')
exit $failed
```

It lints the working-tree copy of each file, so with a partly staged file the line numbers can be
off. On the sample it passed a commit that touched a file full of baselined issues, and failed one
that added an unverified `$_GET` read.

## 7. Formatting (optional)

Mago's formatter replaces phpcbf. Reformat once, in a commit of its own:

```sh
vendor/bin/mago-wordpress format          # format everything mago.toml lists
vendor/bin/mago-wordpress format --check  # exits 1 with a diff if a file would change
```

On the sample site it changed 68 files (+1,254/-1,412 lines; +296/-418 ignoring whitespace), and
`--check` passed afterwards.

- Template whitespace changes, so the HTML a template prints changes too. Regenerate HTML or visual
  snapshots after the reformat.
- Regenerate the baseline after the reformat if you use `--verify-baseline`.
- Format staged files in the pre-commit hook with `vendor/bin/mago-wordpress format --staged`.
- Don't use `mago format` or an editor's Mago format-on-save: they strip the spaces inside
  parentheses. Point format-on-save at `vendor/bin/mago-wordpress format <file>` instead
  ([Formatting](formatting.md#in-an-editor)).

## 8. Mago rules the presets turn off

The presets turn off Mago's own rules that no WPCS sniff runs (91 on Mago 1.51.0, listed between the
`BEGIN generated` and `END generated` lines in `wordpress.mago.toml`), so a phpcs-clean project
starts clean. Among them are `strict-types`, `no-isset`, `class-name`, `no-closing-tag`,
`cyclomatic-complexity` and `no-request-variable`. Turn one back on in your `mago.toml`:

```toml
[linter.rules]
no-request-variable = { enabled = true }
```

`no-request-variable` (reading `$_REQUEST`) is worth it: it reported 6 errors on the sample site
and had found a real bug there. `strict-types` (94 warnings) and `no-closing-tag` (19) fight
WordPress conventions; leave them off.

## 9. Known differences from phpcs

| Area | phpcs | Mago |
|:---|:---|:---|
| Cyclomatic complexity | counts branches, not `&&`/`\|\|`; scores functions and methods | counts `&&`/`\|\|`; also scores a class as the sum of its methods, with no setting to turn that off alone (use `exclude` or a pragma) |
| Nesting | the function body is level 0 | the function body is level 1; `migrate` writes `nestingLevel + 1` |
| Missing docblocks | a `//` comment before a function is `FunctionComment.WrongStyle`, a separate code | `missing-docs` reports it as missing |
| Levels | per message | per rule; some sniffs' warning-level messages go to a `<rule>-warning` companion rule |
| `ShortPrefixPassed`, `InvalidPrefixPassed` | once per run | once per worker process ([details](rules.md#prefix-all-globals-in-theme-templates)) |
| Absolute limits | `absoluteComplexity`, `absoluteNestingLevel` | none |
| `<arg>`, `<ini>`, `phpcs:set` | read | ignored |
