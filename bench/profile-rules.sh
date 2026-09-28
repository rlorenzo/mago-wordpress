#!/usr/bin/env bash
# Times this extension's rules on a WordPress codebase, all together and one at a time.
#
#   bench/profile-rules.sh <project-dir> [<text-domain>] [<prefix>]           # timings
#   bench/profile-rules.sh --counts <project-dir> [<text-domain>] [<prefix>]  # per-rule issue counts
#
# Timings are the median of $RUNS runs (default 5) and print slowest first. The counts mode
# prints `--reporting-format code-count` (or $FORMAT, e.g. short) for all rules, sorted; diff it before and after a change to
# prove the change kept behaviour. Needs vendor/bin/mago (composer install) and php.
set -euo pipefail

counts=false
if [[ ${1:-} == --counts ]]; then counts=true; shift; fi
[[ $# -ge 1 ]] || { echo "usage: bench/profile-rules.sh [--counts] <project-dir> [<text-domain>] [<prefix>]" >&2; exit 1; }

project=$(cd "$1" && pwd -P)
here=$(cd "$(dirname "$0")/.." && pwd -P)
mago=${MAGO:-$here/vendor/bin/mago}
runs=${RUNS:-5}

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# shellcheck disable=SC2016 # the single-quoted $ are PHP variables, not shell ones
php -r '
[, $project, $worker, $domain, $prefix, $work] = $argv;
$json = fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents("$work/mago.toml", <<<TOML
php-version = "8.2"
[source]
paths = [{$json($project)}]
excludes = ["**/vendor/**", "**/vendor_prefixed/**", "**/node_modules/**", "**/tests/**"]
[linter]
integrations = ["wordpress"]
[extension-hosts.wordpress]
command = ["php", {$json($worker)}]

TOML);
file_put_contents("$work/composer.json", json_encode(
    ["extra" => ["mago-wordpress" => ["text-domains" => [$domain], "prefixes" => [$prefix]]]],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
) . "\n");
' "$project" "$here/resources/worker.php" "${2:-}" "${3:-}" "$work"

# The worker discovers composer.json via getcwd(), so mago must run from $work.
lint() { (cd "$work" && "$mago" --config "$work/mago.toml" lint --only "$1" --reporting-format "$2") || true; }

codes=$(paste -sd, - < "$here/tests/corpus/expected-rules.txt")
if $counts; then lint "$codes" "${FORMAT:-code-count}" | sort; exit 0; fi

median() {
    local times=()
    for ((i = 0; i < runs; i++)); do
        local start end
        start=$(php -r 'echo hrtime(true);')
        lint "$1" count > /dev/null 2>&1
        end=$(php -r 'echo hrtime(true);')
        times+=("$(( (end - start) / 1000000 ))")
    done
    printf '%s\n' "${times[@]}" | sort -n | sed -n "$(( (runs + 1) / 2 ))p"
}

lint "$codes" count > /dev/null 2>&1 # warm the file cache
printf '%6s ms  %s\n' "$(median "$codes")" "all rules"
for code in $(cat "$here/tests/corpus/expected-rules.txt"); do
    printf '%6s ms  %s\n' "$(median "$code")" "$code"
done | sort -rn
