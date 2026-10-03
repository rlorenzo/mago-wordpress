# Formatting

The `extends` in [Install](../README.md#install) also sets Mago's formatter to the closest it gets to
`WordPress-Core`: tabs, braces on the same line, `! $x`, spaces inside grouping parentheses
`( $a + $b )`, aligned `=` and `=>`, argument and parameter lists kept broken where you broke them,
a broken concatenation kept broken (one operand per line; joined, `'a' . 'b'` on one line is what
`Generic.Strings.UnnecessaryStringConcat` reports), no line limit (`print-width = 1000`), `exit;` rather than `exit();`, a final `?>` kept, and no blank
line added after `<?php`. Trailing commas in multi-line lists stay on: WordPress-Core requires one
after the last item of a multi-line array. It also sets `array-style` to `long`. Override any of
these under `[formatter]` in your own `mago.toml`.

## Format and check

```sh
vendor/bin/mago-wordpress format            # format the files your mago.toml lists
vendor/bin/mago-wordpress format src/ x.php # or just these paths
vendor/bin/mago-wordpress format --check    # change nothing; fail with a diff if a file would change
vendor/bin/mago-wordpress format --staged   # only the PHP files staged in git, then re-stage them
```

This is the only supported way to format and to check. `mago format` alone can't produce WordPress
formatting, and `mago format --check` always fails on code that is.

The command runs three steps in order:

```sh
mago lint --fix --only array-style                  # [] to array()
mago format
mago lint --fix --only wordpress/parentheses-spacing # the spaces mago format removes
```

`mago format` can't add the spaces WordPress puts inside parentheses and brackets, and upstream has
declined an option for it ([#446](https://github.com/carthage-software/mago/issues/446),
[#490](https://github.com/carthage-software/mago/issues/490)). The third step adds them, plus the
space before an alternative-syntax colon (`if ( $x ) :`, `else :`). Since `mago format` removes them
again, the command is idempotent only as a whole: running `mago format` on its own undoes step 3.
`mago format` is also not always stable on its own (a comment between `<?php` and `endif;` moves a
little on each run), so the command repeats it until `mago format --check` passes.

Formatting must not create findings. After the steps, the command lints each file they changed,
formatted and as it was, and puts back the original of any file that gained a finding, with
`<file>: left unformatted: formatting adds <rule> findings`. The known case: a `/* translators: */`
comment inside a multi-line call that `mago format` moves so it is no longer on the line before its
`__()` (`WordPress.WP.I18n.MissingTranslatorsComment`). Format such a file by hand, or leave it;
`--check` does not count it as unformatted.

`--check` copies the files into a temporary directory with your top-level files (`mago.toml`,
`composer.json`, the baseline) and a link to `vendor/`, runs the same steps there, and prints a
unified diff for each file that would change. `--staged` refuses a file that also has unstaged
changes, since re-staging it would stage those too.

The command uses `vendor/bin/mago`; set `MAGO=/path/to/mago` to use another binary.

### In CI and git hooks

```sh
vendor/bin/mago-wordpress format --check            # CI
vendor/bin/mago-wordpress format --staged           # pre-commit hook: format what is being committed
```

### In an editor

Turn off format-on-save with Mago in your editor for WordPress projects: it runs `mago format`
alone, which strips the spaces inside parentheses again. Instead, either:

- format from the command line (or a pre-commit hook) with `vendor/bin/mago-wordpress format`, or
- point the editor's "run on save" or external-formatter setting at
  `vendor/bin/mago-wordpress format ${file}`, run from the project root (VS Code: an extension such
  as Run on Save; PhpStorm: a File Watcher).

`mago lint` in the editor is unaffected.

## Templates

Checked against what phpcbf (`WordPress-Core`) writes for the same input:

| | Mago alone | `mago-wordpress format` | phpcbf |
|:---|:---|:---|:---|
| Alternative-syntax colon | `if ( $x ): ?>` | `if ( $x ) : ?>` | `if ( $x ) : ?>` |
| Final `?>` in a template | removed | kept (`remove-trailing-close-tag = false`) | kept |
| Blank line after `<?php` | added | not added, an existing one kept (`empty-line-after-opening-tag = false`) | not added |
| Long `<?php esc_html_e( '...' ); ?>` in HTML | wrapped at 120 columns | one line (`print-width = 1000`) | one line |

Not fixed: when code follows the colon on the same line (`<?php while ( have_posts() ) : the_post(); ?>`),
phpcbf rewrites the whole block across several lines, moving `?>` onto a line of its own
(`Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace`). That restructures the PHP and HTML
around it and would fight `mago format`'s own layout of the block, so the command leaves it alone.
It doesn't occur in the benchmark below.

## How close it gets

On Akismet, Contact Form 7 and Yoast SEO, phpcs-fixable `WordPress-Core` reports drop from
334,577 (Mago's default formatting) to 7,340 with `mago-wordpress format`. What's left:

| Cause | Share |
|:---|---:|
| Alignment: WPCS stops aligning `=>` and `=` past a column limit, Mago never stops | 72 % |
| Hugged last argument: Mago keeps `foo( $a, array(` on one line | 23 % |
| Associative arrays with several items on one line, which WPCS wants broken | 2 % |
| Fixable but not formatting (loose comparisons and similar) | 2 % |
| Other: `echo` followed by a newline, scope indent, embedded PHP, multi-catch `A\|B` | 2 % |

Most of the alignment share is one generated file in Yoast SEO. On bcap_website, a phpcs-clean site
of 107 files, the command changes 81 files, +579/-733 lines ignoring whitespace. Per-plugin numbers,
sniff codes and how `print-width` trades off: [formatter results](../bench/results/2026-10-01-formatter.md).

## Existing phpcs-clean code

On code already formatted for phpcs, Mago's formatting still differs: mostly `=`/`=>` alignment
phpcs had accepted unaligned, trailing commas added to multi-line calls, and conditions you broke by
hand joined onto one line. Akismet goes from 88 fixable reports as shipped to 114. Adopt the
preset only if you are switching formatting to Mago and accept its style, and reformat once with
`mago-wordpress format`. If you keep running phpcs for formatting while you move, either exclude those
codes from your ruleset or don't format files phpcs still checks.
