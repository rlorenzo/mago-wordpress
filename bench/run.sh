#!/usr/bin/env bash
# Benchmarks phpcs (WordPress-Extra) against mago + this extension on a WordPress codebase.
#
#   bench/run.sh <project-dir> [<text-domain>] [<prefix>]
#
# Needs: vendor/bin/mago (composer install), php, perl, and a phpcs with WPCS on PATH or
# in $PHPCS (defaults to vendor/bin/phpcs of this package if installed). Results print as
# a markdown table; paste them into README.md.
#
# Not an apples-to-apples rule comparison: phpcs runs the full WordPress-Extra ruleset
# (WPCS 3.4.1), while mago only runs this extension's rules.
set -euo pipefail

[[ $# -ge 1 ]] || { echo "usage: bench/run.sh <project-dir> [<text-domain>] [<prefix>]" >&2; exit 1; }

project=$(cd "$1" && pwd -P)
domain=${2:-}
prefix=${3:-}
here=$(cd "$(dirname "$0")/.." && pwd -P)
mago=${MAGO:-$here/vendor/bin/mago}
phpcs=${PHPCS:-$here/vendor/bin/phpcs}
source "$here/bench/lib.sh"

[[ -x "$mago" ]] || { echo "mago not found or not executable: $mago" >&2; exit 1; }
[[ -x "$phpcs" ]] || { echo "phpcs not found or not executable: $phpcs" >&2; exit 1; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# PHP writes the configs so every value is escaped by a real encoder.
write_configs "$project" "$here/resources/worker.php" "$domain" "$prefix" "$work"
# shellcheck disable=SC2016 # the single-quoted $ are PHP variables, not shell ones
php -r '
[, $domain, $prefix, $work] = $argv;
$xml = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1);

file_put_contents("$work/phpcs.xml", <<<XML
<?xml version="1.0"?>
<ruleset name="bench">
  <rule ref="WordPress-Extra"/>
  <rule ref="WordPress.WP.I18n"><properties><property name="text_domain" type="array"><element value="{$xml($domain)}"/></property></properties></rule>
  <rule ref="WordPress.NamingConventions.PrefixAllGlobals"><properties><property name="prefixes" type="array"><element value="{$xml($prefix)}"/></property></properties></rule>
</ruleset>

XML);
' "$domain" "$prefix" "$work"

# The worker discovers composer.json via getcwd(), so mago must run from $work.
run_mago() { (cd "$work" && "$mago" --config "$work/mago.toml" lint --only "$codes" --reporting-format code-count); }
run_phpcs() {
    "$phpcs" --standard="$work/phpcs.xml" --extensions=php \
        --ignore='*/vendor/*,*/vendor_prefixed/*,*/node_modules/*,*/tests/*' \
        --parallel=8 -d memory_limit=2G --report=summary "$project"
}

# Exit status alone doesn't prove a clean run: phpcs exits 2 both when it found fixable
# violations and on some runtime errors, and mago exits 1 for findings even if its worker died.
# So each tool gets its highest acceptable status plus a pattern its findings report always prints;
# a run that exceeds the status, lacks the report, or shows a crash message fails the script.
# The patterns are case-insensitive heuristics (neither tool's wording is a stable contract; mago
# prints "Error[" but "warning["), not a parse.
# Output goes to a file rather than a captured variable, so timed runs pay no subshell.
run_checked() { # tool (mago|phpcs): runs run_<tool>
    local tool=$1 status=0 max report crash
    case $tool in
        mago) max=1 report='^(error|warning|help|note)\[|No issues found'
              crash='extension host|worker (crashed|panicked|failed|exited unexpectedly)' ;;
        phpcs) max=2 report='PHP CODE SNIFFER REPORT SUMMARY'
               crash='PHP Fatal error|Uncaught (Error|Exception)|^ERROR: ' ;;
    esac
    "run_$tool" > "$work/out" 2>&1 || status=$?
    if (( status > max )) || { (( status > 0 )) && ! grep -qiE "$report" "$work/out"; } || grep -qiE "$crash" "$work/out"; then
        echo "error: $tool exited with status $status:" >&2
        cat "$work/out" >&2
        exit 1
    fi
}

files=$(find "$project" -name '*.php' -not -path '*/vendor/*' -not -path '*/vendor_prefixed/*' -not -path '*/node_modules/*' -not -path '*/tests/*' | wc -l | tr -d ' ')
version_of() { # binary; prints the last line of its --version output, or fails the script
    local out
    out=$("$1" --version 2>&1) || { echo "error: $1 --version failed: $out" >&2; exit 1; }
    echo "${out##*$'\n'}"
}
mago_version=$(version_of "$mago")
phpcs_version=$(version_of "$phpcs")

echo "## $(basename "$project") ($files PHP files)"
echo
echo "$mago_version / $phpcs_version"
echo
echo "mago issue counts by rule:"
run_checked mago
cat "$work/out"
echo

# Mean of 3 timed runs after 1 warm-up (hyperfine hangs on mago's worker protocol). Each
# run is re-checked so a crash mid-benchmark fails the script instead of skewing the mean.
timed() { # label, tool
    local name=$1 tool=$2 total=0 t0 t1
    run_checked "$tool"
    for _ in 1 2 3; do
        t0=$(perl -MTime::HiRes=time -e 'printf "%.3f", time')
        run_checked "$tool"
        t1=$(perl -MTime::HiRes=time -e 'printf "%.3f", time')
        total=$(perl -e "print $total + ($t1 - $t0)")
    done
    perl -e "printf \"| %s | %.2f s |\n\", '$name', $total / 3"
}
echo "| Tool | Mean of 3 runs |"
echo "|:---|---:|"
timed "phpcs WordPress-Extra (WPCS 3.4.1, --parallel=8)" phpcs
timed "mago + mago-wordpress" mago
