#!/usr/bin/env bash
# Runs bench/run.sh on every bake-off codebase and prints the results table for docs/benchmarks.md.
#
#   BAKEOFF_DATA=~/Projects/bakeoff-data PHPCS=~/.config/composer/vendor/bin/phpcs bench/bakeoff.sh [<out-dir>]
#   bench/bakeoff.sh --report <out-dir> > bench/results/<yyyy-mm>-bakeoff.md
#
# $BAKEOFF_DATA holds plugins/<slug>/ (release zips, unpacked) and wordpress-develop/. Each
# codebase's full bench/run.sh output lands in <out-dir>/<name>.out (default
# bench/results/<yyyy-mm-dd>/). The markdown report (results table plus per-rule issue counts
# per codebase) prints at the end, or on its own with --report. Re-run this for every PR that
# adds or changes a rule, and diff the report against the last one in bench/results/.
set -euo pipefail

here=$(cd "$(dirname "$0")/.." && pwd -P)
report_only=false
if [[ ${1:-} == --report ]]; then report_only=true; shift; fi
out=${1:-$here/bench/results/$(date +%Y-%m-%d)}

# name | path | text domain | prefix | display name
codebases=(
    "woocommerce|plugins/woocommerce|woocommerce|wc|WooCommerce"
    "wordpress-core|wordpress-develop/src|default|wp|WordPress core"
    "elementor|plugins/elementor|elementor|elementor|Elementor"
    "google-site-kit|plugins/google-site-kit|google-site-kit|googlesitekit|Google Site Kit"
    "wordpress-seo|plugins/wordpress-seo|wordpress-seo|wpseo|Yoast SEO"
    "wpforms-lite|plugins/wpforms-lite|wpforms-lite|wpforms|WPForms Lite"
    "litespeed-cache|plugins/litespeed-cache|litespeed-cache|litespeed|LiteSpeed Cache"
    "wp-mail-smtp|plugins/wp-mail-smtp|wp-mail-smtp|wp_mail_smtp|WP Mail SMTP"
    "all-in-one-wp-migration|plugins/all-in-one-wp-migration|all-in-one-wp-migration|ai1wm|All-in-One WP Migration"
    "contact-form-7|plugins/contact-form-7|contact-form-7|wpcf7|Contact Form 7"
    "akismet|plugins/akismet|akismet|akismet|Akismet"
)

if ! $report_only; then
data=${BAKEOFF_DATA:?set BAKEOFF_DATA to the directory holding plugins/ and wordpress-develop/}
mkdir -p "$out"
# Recorded now so a later --report labels these measurements with the versions that produced them.
echo "PHP $(php -r 'echo PHP_VERSION;'), this package at $(git -C "$here" describe --tags --always)" > "$out/versions.txt"
for entry in "${codebases[@]}"; do
    IFS='|' read -r name path domain prefix _ <<< "$entry"
    echo "== $name" >&2
    "$here/bench/run.sh" "$data/$path" "$domain" "$prefix" > "$out/$name.out" 2>&1 || {
        echo "error: bench/run.sh failed for $name; see $out/$name.out" >&2
        exit 1
    }
done
fi

# Report from the .out files: file count from the heading, times from the two timed rows,
# per-rule counts from the code-count block. Totals accumulate as the rows print.
first=$(ls "$out"/*.out | head -1)
echo "# Bake-off $(basename "$out"): top 10 WordPress.org plugins + WordPress core"
echo
echo "\`bench/bakeoff.sh\` (phpcs WordPress-Extra vs mago + mago-wordpress), mean of 3 runs after a warm-up."
[[ -f "$out/versions.txt" ]] || { echo "error: $out/versions.txt is missing; run the benchmarks first" >&2; exit 1; }
echo "Versions: $(grep -m1 "^mago " "$first"), $(cat "$out/versions.txt")."
echo
echo "## Results"
echo
echo "| Codebase | PHP files | phpcs \`WordPress-Extra\` | \`mago lint\` + extension | Speed-up |"
echo "|:---|---:|---:|---:|---:|"
tf=0; tp=0; tm=0
for entry in "${codebases[@]}"; do
    IFS='|' read -r name _ _ _ display <<< "$entry"
    files=$(sed -n 's/^## .* (\([0-9]*\) PHP files)$/\1/p' "$out/$name.out")
    phpcs=$(sed -n 's/^| phpcs .* | \([0-9.]*\) s |$/\1/p' "$out/$name.out")
    mago=$(sed -n 's/^| mago .* | \([0-9.]*\) s |$/\1/p' "$out/$name.out")
    [[ -n $files && -n $phpcs && -n $mago ]] || { echo "error: $out/$name.out has no complete timing rows" >&2; exit 1; }
    perl -e 'printf "| %s | %s | %.2f s | %.2f s | %.1f× |\n", @ARGV[0..3], $ARGV[2] / $ARGV[3]' "$display" "$files" "$phpcs" "$mago"
    tf=$((tf + files)); tp=$(perl -e "print $tp + $phpcs"); tm=$(perl -e "print $tm + $mago")
done
perl -e 'printf "| **Total** | **%s** | **%.2f s** | **%.2f s** | **%.1f×** |\n", $ARGV[0], $ARGV[1], $ARGV[2], $ARGV[1] / $ARGV[2]' "$tf" "$tp" "$tm"
echo
echo "## mago issue counts by rule, per codebase"
for entry in "${codebases[@]}"; do
    IFS='|' read -r name _ _ _ display <<< "$entry"
    echo; echo "### $display"; echo; echo '```'
    grep -E '^(error|warning|help|note)\[' "$out/$name.out" || echo "No issues found."
    echo '```'
done
