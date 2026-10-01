# Shared by run.sh and profile-rules.sh (sourced, not executed).
# Needs $here (repo root).

# write_configs <project> <worker> <domain> <prefix> <work>: writes mago.toml and composer.json
# into <work>. PHP encodes every value; a JSON string with unescaped slashes is also a valid
# TOML basic string.
write_configs() {
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
' "$@"
}

rules_file="$here/tests/corpus/expected-rules.txt"
codes=$(paste -sd, - < "$rules_file")
