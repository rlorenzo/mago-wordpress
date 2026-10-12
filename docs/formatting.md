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
vendor/bin/mago-wordpress format --staged   # the staged PHP files mago.toml lists, then re-stage them
```

This is the only supported way to format and to check. `mago format` alone can't produce WordPress
formatting, and `mago format --check` always fails on code that is.

The command runs six steps in order:

```sh
mago lint --fix --only array-style                  # [] to array()
mago format
mago lint --fix --only wordpress/parentheses-spacing # the spaces mago format removes
mago lint --fix --only wordpress/language-construct-spacing # `echo\n$x` -> `echo $x`
mago lint --fix --only wordpress/function-call-signature # `foo( 'a', array(` -> one argument per line
mago lint --fix --only wordpress/array-alignment     # the arrows mago format aligns past column 60
```

`mago format` can't add the spaces WordPress puts inside parentheses and brackets, and upstream has
declined an option for it ([#446](https://github.com/carthage-software/mago/issues/446),
[#490](https://github.com/carthage-software/mago/issues/490)). The third step adds them, plus the
space before an alternative-syntax colon (`if ( $x ) :`, `else :`). Since `mago format` removes them
again, the command is idempotent only as a whole: running `mago format` on its own undoes step 3.
`mago format` is also not always stable on its own (a comment between `<?php` and `endif;` moves a
little on each run), so the command repeats it until `mago format --check` passes.

Step 4 puts back the one space after `echo`, `return`, `new`, `use` and the other language constructs that `mago format` replaces by a line break when the argument is long. Steps 5 and 6 undo what `mago format` does and phpcs does not allow. It keeps a closure or array last
argument on the line of the call's `(`, aligns every `=>` however long the key, and WordPress-Core
wants `add_action( 'init', function () {` as one argument per line with `)` on its own line, and the
`=>` aligned only up to column 60. Step 5 moves a hugged argument to a line of its own together with
the lines it spans, one level deeper (the lines of a multi-line string or heredoc stay as they are),
and is repeated until no call is left; a call with a comment among its arguments is left alone.

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

On Akismet, Contact Form 7 and Yoast SEO (1,834 PHP files), phpcs-fixable `WordPress-Core` reports
drop from 334,577 (Mago's default formatting) to 692 with the five steps of `mago-wordpress format` that existed then (before the language-construct step)
(7,340 before `wordpress/array-alignment` and 2,366 before `wordpress/function-call-signature`).
What's left:

| Cause | Reports | Share |
|:---|---:|---:|
| Assignment `=` alignment: WPCS stops aligning past 40 spaces of padding, Mago never stops (`Generic.Formatting.MultipleStatementAlignment`) | 263 | 38 % |
| Associative arrays with several items on one line, which WPCS wants broken | 147 | 21 % |
| Fixable but not formatting (loose comparisons) | 113 | 16 % |
| `Generic.WhiteSpace.LanguageConstructSpacing` | 61 | 9 % |
| Other: scope indent, operator spacing, embedded PHP, inline tabs | 108 | 16 % |

On the same plugins phpcs reports 4,991 `WordPress.Arrays.MultipleStatementAlignment` warnings in
51 files after the first three steps (`wordpress/array-alignment` takes them to 0), and 1,669
`PEAR.Functions.FunctionCallSignature` layout reports (`wordpress/function-call-signature` takes
them to 0 without adding a report of any other sniff).

How these were measured: a fresh copy of each plugin (Akismet 5.7.2, Contact Form 7 6.1.7, Yoast SEO
28.5; 1,834 PHP files) with a `mago.toml` that extends the preset, the steps run one by one with
`mago lint --fix --only <rule>` and `mago format` (repeated until `mago format --check` passes) on
Mago 1.51.0, then `phpcs --standard=WordPress-Core --extensions=php --report=json` (phpcs 3.13.6,
WPCS 3.4.1), counting the reports phpcs marks fixable by sniff code. This is a manual run of the
steps, not the output of `mago-wordpress format`, whose final check can put a file back unformatted
(see above); the figures are what the steps do, not a measure of that check.

On bcap_website (measured before steps 5 and 6), a phpcs-clean site
of 107 files, the command changes 81 files, +579/-733 lines ignoring whitespace. Per-plugin numbers,
sniff codes and how `print-width` trades off: [formatter results](../bench/results/2026-10-01-formatter.md).

## Existing phpcs-clean code

On code already formatted for phpcs, Mago's formatting still differs: mostly `=`/`=>` alignment
phpcs had accepted unaligned, trailing commas added to multi-line calls, and conditions you broke by
hand joined onto one line. Akismet goes from 88 fixable reports as shipped to 114. Adopt the
preset only if you are switching formatting to Mago and accept its style, and reformat once with
`mago-wordpress format`. If you keep running phpcs for formatting while you move, either exclude those
codes from your ruleset or don't format files phpcs still checks.
