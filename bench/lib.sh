# Shared by run.sh and profile-rules.sh (sourced, not executed).
# Needs $here (repo root).

# write_configs <project> <worker> <domain> <prefix> <work>: writes mago.toml and composer.json
# into <work>. The mago.toml extends the package's wordpress.mago.toml (the package being the
# directory above <worker>'s resources/), so the bench runs the config a consumer gets with the
# full WordPress standard. PHP encodes every value; a JSON string with unescaped slashes is also
# a valid TOML basic string.
write_configs() {
    # shellcheck disable=SC2016 # the single-quoted $ are PHP variables, not shell ones
    php -r '
[, $project, $worker, $domain, $prefix, $work] = $argv;
$json = fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$preset = dirname($worker, 2) . "/wordpress.mago.toml";
file_put_contents("$work/mago.toml", <<<TOML
extends = {$json($preset)}
php-version = "8.2"
[source]
paths = [{$json($project)}]
excludes = ["**/vendor/**", "**/vendor_prefixed/**", "**/node_modules/**", "**/tests/**"]
[extension-hosts.wordpress]
command = ["php", {$json($worker)}]

TOML);
file_put_contents("$work/composer.json", json_encode(
    ["extra" => ["mago-wordpress" => ["text-domains" => [$domain], "prefixes" => [$prefix]]]],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
) . "\n");
' "$@"
}

rules_file="$here/tests/corpus/expected-rules.txt"
# The rules a consumer gets by default: off-by-default ones (parentheses-spacing) would skew counts
# and timings. Needs $mago.
# shellcheck disable=SC2016 # $r is a PHP variable
codes=$("$mago" --workspace "$here/tests/corpus" extension list --json 2>/dev/null | php -r '
foreach (json_decode(stream_get_contents(STDIN), true)["extensions"][0]["linter-rules"] as $r) {
    if ($r["default-enabled"]) { echo $r["code"], ","; }
}' | sed 's/,$//')
[[ -n $codes ]] || { echo "could not list the extension rules" >&2; exit 1; }
