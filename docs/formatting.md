# Formatting

The `extends` in [Install](../README.md#install) also sets Mago's formatter to the closest it gets to
`WordPress-Core`: tabs, braces on the same line, `! $x`, spaces inside grouping parentheses
`( $a + $b )`, aligned `=` and `=>`, argument and parameter lists kept broken where you broke them,
a broken concatenation kept broken (one operand per line; joined, `'a' . 'b'` on one line is what
`Generic.Strings.UnnecessaryStringConcat` reports), no line limit (`print-width = 1000`), `exit;` rather than `exit();`, a final `?>` kept, and no blank
line added after `<?php`. Trailing commas in multi-line lists stay on: WordPress-Core requires one
after the last item of a multi-line array. Override any of
these under `[formatter]` in your own `mago.toml`.

## Lint and format

The layout sniffs of WordPress-Core (indentation, spacing, array and call layout, braces, embedded PHP,
...) are lint rules: `mago lint` reports them as phpcs does, at the same lines, and `mago lint --fix`
applies the fixes phpcbf would. Each is listed in [Rules](rules.md). Mago's formatter is the other
way to get the same layout: it rewrites a file in one pass, but on its own it produces code those
rules then report (it removes the spaces inside call, declaration and control-structure parentheses, for one). `mago-wordpress format` is
the formatter followed by the fixes that put WordPress's layout back, and puts a file back as it was if
formatting would raise the number of findings of any rule. Use `mago lint` to check, `mago-wordpress format` to rewrite.

## Format and check

```sh
vendor/bin/mago-wordpress format            # format the files your mago.toml lists
vendor/bin/mago-wordpress format src/ x.php # or just these paths
vendor/bin/mago-wordpress format --check    # change nothing; fail with a diff if a file would change
vendor/bin/mago-wordpress format --staged   # the staged PHP files mago.toml lists, then re-stage them
```

This is the only supported way to format and to check. `mago format` alone can't produce WordPress
formatting, and `mago format --check` always fails on code that is.

The command runs thirteen steps in order:

```sh
mago lint --fix --only generic/disallow-short-array-syntax  # [] to array()
mago format
mago lint --fix --only wordpress/parentheses-spacing # the spaces mago format removes
mago lint --fix --only wordpress/language-construct-spacing # `echo\n$x` -> `echo $x`
mago lint --fix --only wordpress/operator-spacing     # `catch ( A|B $e )` -> `catch ( A | B $e )`
mago lint --fix --only wordpress/array-declaration-spacing # `array( 'a' => 1, 'b' => 2 )` -> one item per line
mago lint --fix --only wordpress/array-brace-spacing # a line break after `array(` and before `)` of a multi-line array
mago lint --fix --only wordpress/array-key-spacing-restrictions # `$a[ $i ]` but `$a['key']`
mago lint --fix --only wordpress/comma-after-last    # a comma after the last item of a multi-line array
mago lint --fix --only wordpress/function-call-signature # `foo( 'a', array(` -> one argument per line
mago lint --fix --only wordpress/array-alignment     # the arrows mago format aligns past column 60
mago lint --fix --only wordpress/statement-alignment # the `=` mago format aligns past 40 spaces of padding
mago lint --fix --only generic/disallow-inline-tabs  # a tab used for alignment in the middle of a line -> spaces
```

`mago format` can't add the spaces WordPress puts inside parentheses and brackets, and upstream has
declined an option for it ([#446](https://github.com/carthage-software/mago/issues/446),
[#490](https://github.com/carthage-software/mago/issues/490)). The third step adds them, plus the
space before an alternative-syntax colon (`if ( $x ) :`, `else :`). Since `mago format` removes them
again, the command is idempotent only as a whole: running `mago format` on its own undoes step 3.
`mago format` is also not always stable on its own (a comment between `<?php` and `endif;` moves a
little on each run), so the command repeats it until `mago format --check` passes.

`language-construct-spacing` puts back the one space after `echo`, `return`, `new`, `use` and the other language constructs that `mago format` replaces by a line break when the argument is long. `operator-spacing` puts the spaces back around the `|` of a multi-catch (`mago format` writes `A|B`). `array-declaration-spacing` puts the items of a keyed array on separate lines (`mago format` keeps a short one on a line), `array-brace-spacing` and `array-key-spacing-restrictions` fix the spacing inside the brackets, and `comma-after-last` adds the comma after the last item that WordPress-Core requires of a multi-line array (and removes one from a single-line array). `function-call-signature`, `array-alignment` and `statement-alignment` undo what `mago format` does and phpcs does not allow. It keeps a closure or array last argument on the line of the call's `(`, aligns every `=>` however long the key, and WordPress-Core wants `add_action( 'init', function () {` as one argument per line with `)` on its own line, and the `=>` aligned only up to column 60. `function-call-signature` moves a hugged argument to a line of its own together with the lines it spans, one level deeper (the lines of a multi-line string or heredoc stay as they are), and is repeated until no call is left; a call with a comment among its arguments is left alone. `disallow-inline-tabs` turns a tab used for alignment in the middle of a line into spaces, which `mago format` leaves.

Formatting must not create findings. After the steps, the command lints each file they changed,
formatted and as it was, and puts back the original of any file whose findings of some rule went up in number, with
`<file>: left unformatted: formatting adds <rule> findings`. Two cases are common: PHP tags in
templates (`generic/embedded-php`, and a `generic/multi-line-function-declaration` where the
declaration spans a tag), and a `/* translators: */` comment inside a multi-line call that
`mago format` moves so it is no longer on the line before its `__()`
(`WordPress.WP.I18n.MissingTranslatorsComment`). Such a file keeps its layout until you format it by
hand; `mago lint` still reports whatever phpcs reports in it, and `--check` does not count it as
unformatted.

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

Measured on 2026-10-19 with Mago 1.57.0 and phpcs 3.13.6 / WPCS 3.4.1: a fresh copy of each plugin, then
`mago-wordpress format`, then `phpcs --standard=WordPress-Core` counting the reports it marks fixable.

| Plugin | PHP files | Fixable as shipped | After `mago-wordpress format` | Files left unformatted |
|:---|---:|---:|---:|---:|
| Akismet 5.7.2 | 29 | 88 | 88 | 5 |
| Contact Form 7 6.1.7 | 111 | 3,682 | 218 | see below |
| Classic Editor | 1 | 704 | 704 | 1 |

Akismet's 88 are all `Universal.Operators.StrictComparisons`: they change behaviour, so no step touches
them (`mago lint --fix --unsafe --only generic/strict-comparisons` rewrites them once you have reviewed
the risk). Its five unformatted files are templates and one file with a translators comment (see
above). Classic Editor is one template-style file, so the check above leaves it as it is, and
its 704 reports stay (632 `ScopeIndent`). In Contact Form 7 the 218 left are mostly
`FunctionCallSignature` (100), `MultipleStatementAlignment` (58), `ScopeIndent` (30) and
`EmbeddedPhp` (20), in files the check left unformatted. Where `mago format` is stopped by the check,
`mago lint` still tells you what to fix by hand; `mago lint --fix` applies the safe fixes one rule at
a time.

Earlier measurements (2026-10-01, Mago 1.51.0, Akismet, Contact Form 7 and Yoast SEO, 145 fixable
reports left after the steps) predate the layout rules and the check on them, and are in the
[formatter results](../bench/results/2026-10-01-formatter.md).

## Existing phpcs-clean code

On code already formatted for phpcs, Mago's formatting still differs: mostly `=`/`=>` alignment
phpcs had accepted unaligned, trailing commas added to multi-line calls, and conditions you broke by
hand joined onto one line. Akismet goes from 88 fixable reports as shipped to 114. Adopt the
preset only if you are switching formatting to Mago and accept its style, and reformat once with
`mago-wordpress format`. If you keep running phpcs for formatting while you move, either exclude those
codes from your ruleset or don't format files phpcs still checks.
