#!/usr/bin/env bash
# Adoption benchmark: what a phpcs-clean project sees after `mago-wordpress migrate --write` alone.
#
#   PHPCS=~/.config/composer/vendor/bin/phpcs bench/adoption.sh '<path>[|<ref>[|<label>]]'...
#
# For each project, exports <ref> (default origin/main, the phpcs-era state) with `git archive`
# into a temp dir (without wp-content/uploads), links this checkout in as
# vendor/rlorenzo/mago-wordpress plus Mago (and the project's own vendor/php-stubs if present),
# runs `mago-wordpress migrate --write`, then `mago lint --reporting-format code-count`. With
# $PHPCS set, also runs phpcs with the project's own ruleset (warnings forced on, so a ruleset's
# `-n` does not hide them). The source repo is only read. Writes
# bench/results/<yyyy-mm-dd>-adoption.md; <label> names the project there (default: the
# directory name), so a private project's path never reaches the report.
set -euo pipefail

[[ $# -ge 1 ]] || { echo "usage: bench/adoption.sh '<path>[|<ref>[|<label>]]'..." >&2; exit 1; }

here=$(cd "$(dirname "$0")/.." && pwd -P)
mago=$here/vendor/bin/mago
[[ -x "$mago" ]] || { echo "mago not found: run composer install in $here" >&2; exit 1; }
out=$here/bench/results/$(date +%Y-%m-%d)-adoption.md

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

{
echo "# Adoption $(date +%Y-%m-%d): phpcs-era projects after \`mago-wordpress migrate --write\`"
echo
echo "\`bench/adoption.sh\`. Each project's phpcs-era tree, with only \`mago-wordpress migrate --write\`"
echo "applied, linted with \`mago lint --reporting-format code-count\`. The phpcs row uses the project's"
echo "own ruleset with warnings forced on (\`--warning-severity=5\`); \`phpcs -n\` would report the errors only."
echo
echo "Versions: $("$mago" --version | tail -1), this package at $(git -C "$here" describe --tags --always), PHP $(php -r 'echo PHP_VERSION;')."
} > "$work/report.md"

for entry in "$@"; do
    IFS='|' read -r path ref label <<< "$entry"
    path=$(cd "$path" && pwd -P)
    ref=${ref:-origin/main}
    label=${label:-$(basename "$path")}
    tree=$work/tree
    rm -rf "$tree" && mkdir -p "$tree/vendor/bin" "$tree/vendor/rlorenzo" "$tree/vendor/carthage-software"
    echo "== $label ($ref)" >&2

    git -C "$path" archive "$ref" | tar -x -C "$tree" --exclude 'wp-content/uploads'
    # The worker finds this checkout's autoloader when the project has none of its own.
    ln -s "$here" "$tree/vendor/rlorenzo/mago-wordpress"
    ln -s "$here/vendor/carthage-software/mago" "$tree/vendor/carthage-software/mago"
    ln -s "$mago" "$tree/vendor/bin/mago"
    [[ -d "$path/vendor/php-stubs" ]] && ln -s "$path/vendor/php-stubs" "$tree/vendor/php-stubs"

    (cd "$tree" && php "$here/bin/mago-wordpress" migrate --write) > "$work/migrate.out" 2>&1 || {
        echo "error: migrate failed for $label:" >&2; cat "$work/migrate.out" >&2; exit 1
    }
    # The worker reads composer.json from the directory mago runs in. Exit 1 means issues found.
    status=0
    (cd "$tree" && "$mago" lint --reporting-format code-count) > "$work/lint.out" 2>&1 || status=$?
    if (( status > 1 )) || grep -qiE 'extension host|worker (crashed|panicked|failed|exited)' "$work/lint.out"; then
        echo "error: mago lint exited with status $status for $label:" >&2; cat "$work/lint.out" >&2; exit 1
    fi

    {
        echo
        echo "## $label"
        echo
        echo "| Rule | Level | Count |"
        echo "|:---|:---|---:|"
        # shellcheck disable=SC2016 # the backticks are markdown
        sed -nE 's/^(error|warning|help|note)\[([^]]+)\]: ([0-9]+)$/| `\2` | \1 | \3 |/p' "$work/lint.out" | sort -t'|' -k4 -rn
        total=$(sed -nE 's/^(error|warning|help|note)\[[^]]+\]: ([0-9]+)$/\2/p' "$work/lint.out" | paste -sd+ - | bc)
        echo "| **Total** | | **${total:-0}** |"
    } >> "$work/report.md"

    if [[ -n ${PHPCS:-} ]]; then
        # phpcs exits 1/2 when it found issues; the JSON totals are the result.
        (cd "$tree" && "$PHPCS" --standard=./phpcs.xml --report=json --warning-severity=5 -q) > "$work/phpcs.json" 2> "$work/phpcs.err" || true
        # shellcheck disable=SC2016 # $t is a PHP variable
        totals=$(php -r '$t = json_decode(file_get_contents($argv[1]), true)["totals"] ?? null;
            if ($t === null) { exit(1); } echo $t["errors"], " ", $t["warnings"];' "$work/phpcs.json") || {
            echo "error: phpcs produced no JSON report for $label:" >&2; cat "$work/phpcs.json" "$work/phpcs.err" >&2; exit 1
        }
        read -r errors warnings <<< "$totals"
        {
            echo
            echo "phpcs with the project's ruleset: $errors errors, $warnings warnings ($errors with \`-n\`)."
        } >> "$work/report.md"
    fi
done

cp "$work/report.md" "$out"
echo "wrote $out" >&2
