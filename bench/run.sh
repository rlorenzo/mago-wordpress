#!/usr/bin/env bash
# Benchmarks phpcs (WordPress-Extra) against mago + this extension on a WordPress codebase.
#
#   bench/run.sh <project-dir> [<text-domain>] [<prefix>]
#
# Needs: vendor/bin/mago (composer install), perl, and a phpcs with WPCS on PATH or in
# $PHPCS (defaults to vendor/bin/phpcs of this package if installed). Results print as a
# markdown table; paste them into README.md.
set -euo pipefail
project=${1:?project directory}; domain=${2:-}; prefix=${3:-}
here=$(cd "$(dirname "$0")/.." && pwd)
mago=${MAGO:-$here/vendor/bin/mago}
phpcs=${PHPCS:-$here/vendor/bin/phpcs}
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

cat > "$work/mago.toml" <<TOML
php-version = "8.2"
[source]
paths = ["$project"]
excludes = ["**/vendor/**", "**/vendor_prefixed/**", "**/node_modules/**", "**/tests/**"]
[linter]
integrations = ["wordpress"]
[extension-hosts.wordpress]
command = ["php", "$here/resources/worker.php"]
TOML
printf '{"extra":{"mago-wordpress":{"text-domains":["%s"],"prefixes":["%s"]}}}\n' "$domain" "$prefix" > "$work/composer.json"

cat > "$work/phpcs.xml" <<XML
<?xml version="1.0"?>
<ruleset name="bench">
  <rule ref="WordPress-Extra"/>
  <rule ref="WordPress.WP.I18n"><properties><property name="text_domain" type="array"><element value="$domain"/></property></properties></rule>
  <rule ref="WordPress.NamingConventions.PrefixAllGlobals"><properties><property name="prefixes" type="array"><element value="$prefix"/></property></properties></rule>
</ruleset>
XML

codes=$(paste -sd, - < "$here/tests/corpus/expected-rules.txt")
mago_cmd="cd $work && $mago --config $work/mago.toml lint --only $codes --reporting-format code-count || true"
phpcs_cmd="$phpcs --standard=$work/phpcs.xml --extensions=php --ignore='*/vendor/*,*/vendor_prefixed/*,*/node_modules/*,*/tests/*' --parallel=8 -d memory_limit=2G --report=summary $project >/dev/null || true"

files=$(find "$project" -name '*.php' -not -path '*/vendor/*' -not -path '*/vendor_prefixed/*' -not -path '*/node_modules/*' -not -path '*/tests/*' | wc -l | tr -d ' ')
echo "## $(basename "$project") ($files PHP files)"
echo
echo "mago issue counts by rule:"; sh -c "$mago_cmd" 2>/dev/null || true
echo

# Mean of three timed runs after one warm-up (hyperfine hangs on mago's worker protocol).
timed() { # name, command
    local name=$1 cmd=$2 total=0 t0 t1
    sh -c "$cmd" >/dev/null 2>&1
    for _ in 1 2 3; do
        t0=$(perl -MTime::HiRes=time -e 'printf "%.3f", time')
        sh -c "$cmd" >/dev/null 2>&1
        t1=$(perl -MTime::HiRes=time -e 'printf "%.3f", time')
        total=$(perl -e "print $total + ($t1 - $t0)")
    done
    perl -e "printf \"| %s | %.2f s |\n\", '$name', $total / 3"
}
echo "| Tool | Mean of 3 runs |"
echo "|:---|---:|"
timed "phpcs WordPress-Extra (WPCS 3.4.1, --parallel=8)" "$phpcs_cmd"
timed "mago + mago-wordpress" "$mago_cmd"
