#!/usr/bin/env bash
# Times this extension's rules on a WordPress codebase, all together and one at a time.
#
#   bench/profile-rules.sh <project-dir> [<text-domain>] [<prefix>]           # timings
#   bench/profile-rules.sh --counts <project-dir> [<text-domain>] [<prefix>]  # per-rule issue counts
#
# Timings are the median of $RUNS runs (default 5) and print slowest first. The counts mode
# prints `--reporting-format code-count` (or $FORMAT, e.g. short) for all rules, sorted; diff
# it before and after a change to prove the change kept behaviour. Needs vendor/bin/mago
# (composer install) and php.
set -euo pipefail

counts=false
if [[ ${1:-} == --counts ]]; then counts=true; shift; fi
[[ $# -ge 1 ]] || { echo "usage: bench/profile-rules.sh [--counts] <project-dir> [<text-domain>] [<prefix>]" >&2; exit 1; }

project=$(cd "$1" && pwd -P)
here=$(cd "$(dirname "$0")/.." && pwd -P)
mago=${MAGO:-$here/vendor/bin/mago}
runs=${RUNS:-5}
source "$here/bench/lib.sh"
[[ $runs =~ ^[1-9][0-9]*$ ]] || { echo "RUNS must be an integer >= 1" >&2; exit 1; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

write_configs "$project" "$here/resources/worker.php" "${2:-}" "${3:-}" "$work"

# The worker discovers composer.json via getcwd(), so mago must run from $work.
lint() { (cd "$work" && "$mago" --config "$work/mago.toml" lint --only "$1" --reporting-format "$2") || true; }

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
for code in $(cat "$rules_file"); do
    printf '%6s ms  %s\n' "$(median "$code")" "$code"
done | sort -rn
