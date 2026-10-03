<?php

/**
 * Regenerates src/Internal/WordPress/Lists.php, CoreClasses.php and Levels.php from a WordPress
 * Coding Standards checkout.
 *
 *   php bin/generate-lists.php /path/to/WordPress-Coding-Standards [/path/to/vendor] [--check]
 *
 * The vendor directory (default: the checkout's own `vendor/`, after `composer install` there)
 * holds the phpcs and PHPCSExtra sources whose Generic, Squiz and Universal sniffs Levels.php
 * also covers.
 *
 * The WPCS arrays are read straight from the source: each property default or `getGroups()`
 * return is tokenized and its array literal evaluated, so no phpcs or PHPCSUtils install is
 * needed. `--check` writes nothing and exits 1 when either file is out of date (CI runs it
 * against a clone of the WPCS_VERSION tag).
 */

declare(strict_types=1);

const WPCS_VERSION = '3.4.1';

$args = array_slice($argv, 1);
$check = in_array('--check', $args, true);
$paths = array_values(array_filter($args, static fn(string $a): bool => $a !== '--check'));
$wpcs = $paths[0] ?? null;
$vendor = $paths[1] ?? "{$wpcs}/vendor";
if ($wpcs === null || !is_dir("{$wpcs}/WordPress/Sniffs") || !is_dir("{$vendor}/squizlabs/php_codesniffer")) {
    fwrite(STDERR, "usage: php bin/generate-lists.php /path/to/WordPress-Coding-Standards [/path/to/vendor] [--check]\n");
    exit(2);
}

/** Evaluates the array literal assigned to `$name` (a property) or returned by `getGroups()`. */
function wpcs_array(string $wpcs, string $file, string $name): array
{
    $tokens = token_get_all((string) file_get_contents("{$wpcs}/WordPress/{$file}"));
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if ($name === 'getGroups()') {
            $found = is_array($t) && $t[0] === T_STRING && $t[1] === 'getGroups';
            $opener = T_RETURN;
        } else {
            $found = is_array($t) && $t[0] === T_VARIABLE && $t[1] === '$' . $name;
            $opener = '=';
        }
        if (!$found) {
            continue;
        }
        while (++$i < $count && ($tokens[$i][0] ?? $tokens[$i]) !== $opener);
        $code = '';
        $depth = 0;
        while (++$i < $count) {
            $t = $tokens[$i];
            $text = is_array($t) ? $t[1] : $t;
            if ($depth === 0 && $text === ';') {
                return eval("return {$code};");
            }
            $depth += match ($text) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };
            if (!is_array($t) || !in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= $text;
            }
        }
    }
    throw new RuntimeException("{$file}: \${$name} not found");
}

/** Sorted case-insensitively; `$lower` lowercases first (function and class names). */
function sorted(array $values, bool $lower = true): array
{
    $values = array_values(array_unique($lower ? array_map(strtolower(...), $values) : $values));
    sort($values, SORT_STRING | SORT_FLAG_CASE);
    return $values;
}

function ksorted(array $map, bool $lower = true): array
{
    if ($lower) {
        $map = array_change_key_case($map);
    }
    ksort($map, SORT_STRING | SORT_FLAG_CASE);
    return $map;
}

/** Keys of a WPCS `name => true` lookup table, sorted. */
function keys(string $wpcs, string $file, string $name, bool $lower = true): array
{
    return sorted(array_keys(wpcs_array($wpcs, $file, $name)), $lower);
}

/** Every `functions` entry across the sniff's `getGroups()`, sorted. */
function group_functions(string $wpcs, string $file): array
{
    return sorted(array_merge(...array_column(wpcs_array($wpcs, $file, 'getGroups()'), 'functions')));
}

function literal(mixed $value): string
{
    return match (true) {
        is_string($value) => "'" . preg_replace("/\\\\(?=['\\\\]|$)|'/", '\\\\$0', $value) . "'",
        $value === null => 'null',
        is_bool($value) => $value ? 'true' : 'false',
        default => (string) $value,
    };
}

/**
 * Renders a value as a short-array literal. Arrays nested `$inline` levels down go on one line
 * when that line fits Mago's 120-column print width, as `mago format` would leave them.
 */
function render(mixed $value, int $indent = 1, ?int $inline = null): string
{
    if (!is_array($value)) {
        return literal($value);
    }
    if ($value === []) {
        return '[]';
    }
    $isList = array_is_list($value);
    $key = static fn($k): string => $isList ? '' : literal($k) . ' => ';
    if ($inline === 0) {
        return '[' . implode(', ', array_map(static fn($k, $v): string => $key($k) . render($v, 0, 0), array_keys($value), $value)) . ']';
    }
    $pad = str_repeat('    ', $indent + 1);
    $out = "[\n";
    foreach ($value as $k => $v) {
        $line = $pad . $key($k) . render($v, $indent + 1, $inline === null ? null : $inline - 1);
        if ($inline === 1 && strlen($line) >= 120) {
            $line = $pad . $key($k) . render($v, $indent + 1);
        }
        $out .= $line . ",\n";
    }
    return $out . str_repeat('    ', $indent) . ']';
}

// Discouraged PHP function groups, as WPCS names them.
$discouragedGroups = [];
foreach (wpcs_array($wpcs, 'Sniffs/PHP/DiscouragedPHPFunctionsSniff.php', 'getGroups()') as $group => $def) {
    $discouragedGroups[$group] = sorted($def['functions']);
}

$alternatives = [];
foreach (wpcs_array($wpcs, 'Sniffs/WP/AlternativeFunctionsSniff.php', 'getGroups()') as $group => $def) {
    $alternatives[$group] = array_filter(
        ['functions' => sorted($def['functions']), 'message' => $def['message'], 'since' => $def['since'] ?? null],
        static fn($v): bool => $v !== null,
    );
}

$hooks = ksorted(wpcs_array($wpcs, 'Helpers/WPHookHelper.php', 'hookInvokeFunctions'));

$deprecatedFunctions = [];
foreach (ksorted(wpcs_array($wpcs, 'Sniffs/WP/DeprecatedFunctionsSniff.php', 'deprecated_functions')) as $fn => $def) {
    $deprecatedFunctions[$fn] = ['alt' => $def['alt'], 'version' => $def['version']];
}

$deprecatedParameters = [];
foreach (ksorted(wpcs_array($wpcs, 'Sniffs/WP/DeprecatedParametersSniff.php', 'target_functions')) as $fn => $params) {
    ksort($params);
    $deprecatedParameters[$fn] = array_map(
        static fn(array $p): array => ['name' => $p['name'], 'value' => $p['value'], 'version' => $p['version']],
        $params,
    );
}

$deprecatedValues = [];
foreach (ksorted(wpcs_array($wpcs, 'Sniffs/WP/DeprecatedParameterValuesSniff.php', 'target_functions')) as $fn => $params) {
    ksort($params);
    $deprecatedValues[$fn] = array_map(
        static fn(array $p): array => ['name' => $p['name'], 'values' => $p['values']],
        $params,
    );
}

$classNameCase = 'Sniffs/WP/ClassNameCaseSniff.php';
$bundled = [];
foreach (wpcs_array($wpcs, $classNameCase, 'class_groups') as $group) {
    if ($group !== 'wp_classes') {
        $bundled[$group] = wpcs_array($wpcs, $classNameCase, $group);
    }
}

$superglobals = keys($wpcs, 'Sniffs/Security/ValidatedSanitizedInputSniff.php', 'slashed_superglobals', lower: false);

// [name, @var line, Source line, value, extra docblock lines]
$constants = [
    ['ESCAPING_FUNCTIONS', 'list<string>', 'Helpers/EscapingFunctionsTrait.php ($escapingFunctions)',
        keys($wpcs, 'Helpers/EscapingFunctionsTrait.php', 'escapingFunctions')],
    ['AUTO_ESCAPED_FUNCTIONS', 'list<string>', 'Helpers/EscapingFunctionsTrait.php ($autoEscapedFunctions)',
        keys($wpcs, 'Helpers/EscapingFunctionsTrait.php', 'autoEscapedFunctions')],
    ['PRINTING_FUNCTIONS', 'list<string>', 'Helpers/PrintingFunctionsTrait.php ($printingFunctions)',
        keys($wpcs, 'Helpers/PrintingFunctionsTrait.php', 'printingFunctions')],
    ['SANITIZING_FUNCTIONS', 'list<string>', 'Helpers/SanitizationHelperTrait.php ($sanitizingFunctions)',
        keys($wpcs, 'Helpers/SanitizationHelperTrait.php', 'sanitizingFunctions')],
    ['UNSLASHING_SANITIZING_FUNCTIONS', 'list<string>', 'Helpers/SanitizationHelperTrait.php ($unslashingSanitizingFunctions)',
        keys($wpcs, 'Helpers/SanitizationHelperTrait.php', 'unslashingSanitizingFunctions')],
    ['UNSLASHING_FUNCTIONS', 'list<string>', 'Helpers/UnslashingFunctionsHelper.php ($unslashingFunctions)',
        keys($wpcs, 'Helpers/UnslashingFunctionsHelper.php', 'unslashingFunctions')],
    ['ARRAY_WALKING_FUNCTIONS', 'list<string>', 'Helpers/ArrayWalkingFunctionsHelper.php ($arrayWalkingFunctions)',
        keys($wpcs, 'Helpers/ArrayWalkingFunctionsHelper.php', 'arrayWalkingFunctions')],
    ['FORMATTING_FUNCTIONS', 'list<string>', 'Helpers/FormattingFunctionsHelper.php ($formattingFunctions)',
        keys($wpcs, 'Helpers/FormattingFunctionsHelper.php', 'formattingFunctions')],
    ['HOOK_INVOKE_FUNCTIONS', 'list<string>', 'Helpers/WPHookHelper.php ($hookInvokeFunctions)',
        array_keys($hooks)],
    ['HOOK_NAME_ARGUMENT_POSITION', 'array<string, int> function name => 1-based argument position of the hook name, as stored by WPCS',
        "Helpers/WPHookHelper.php (\$hookInvokeFunctions[*]['position'])",
        array_map(static fn(array $h): int => $h['position'], $hooks)],
    ['WP_GLOBAL_VARIABLES', 'list<string>', 'Helpers/WPGlobalVariablesHelper.php ($wp_globals)',
        keys($wpcs, 'Helpers/WPGlobalVariablesHelper.php', 'wp_globals', lower: false)],
    ['SUPERGLOBALS', 'list<string>', 'Sniffs/Security/ValidatedSanitizedInputSniff.php ($slashed_superglobals)', $superglobals],
    ['INPUT_SUPERGLOBALS', 'list<string>', 'Sniffs/Security/ValidatedSanitizedInputSniff.php ($slashed_superglobals)', $superglobals],
    ['DB_RESTRICTED_FUNCTIONS', 'list<string>', "Sniffs/DB/RestrictedFunctionsSniff.php getGroups() ('mysql' group)",
        sorted(wpcs_array($wpcs, 'Sniffs/DB/RestrictedFunctionsSniff.php', 'getGroups()')['mysql']['functions'])],
    ['DB_RESTRICTED_CLASSES', 'list<string>', "Sniffs/DB/RestrictedClassesSniff.php getGroups() ('mysql' group)",
        (static function (array $classes): array {
            sort($classes, SORT_STRING); // Byte order ('PDO' before 'mysqli'), unlike the other lists.
            return $classes;
        })(wpcs_array($wpcs, 'Sniffs/DB/RestrictedClassesSniff.php', 'getGroups()')['mysql']['classes'])],
    ['DISCOURAGED_WP_FUNCTIONS', 'list<string>', 'Sniffs/WP/DiscouragedFunctionsSniff.php getGroups()',
        group_functions($wpcs, 'Sniffs/WP/DiscouragedFunctionsSniff.php')],
    ['DISCOURAGED_PHP_FUNCTIONS', 'list<string>', 'Sniffs/PHP/DiscouragedPHPFunctionsSniff.php getGroups()',
        sorted(array_merge(...array_values($discouragedGroups)))],
    ['DISCOURAGED_PHP_FUNCTION_GROUPS', 'array<string, list<string>>', 'Sniffs/PHP/DiscouragedPHPFunctionsSniff.php getGroups()',
        ksorted($discouragedGroups)],
    ['DEVELOPMENT_FUNCTIONS', 'list<string>', 'Sniffs/PHP/DevelopmentFunctionsSniff.php getGroups()',
        group_functions($wpcs, 'Sniffs/PHP/DevelopmentFunctionsSniff.php')],
    ['RESTRICTED_PHP_FUNCTIONS', 'list<string>', 'Sniffs/PHP/RestrictedPHPFunctionsSniff.php getGroups()',
        group_functions($wpcs, 'Sniffs/PHP/RestrictedPHPFunctionsSniff.php')],
    ['ALTERNATIVE_FUNCTIONS', 'array<string, array{functions: list<string>, message: string, since?: string}>',
        'Sniffs/WP/AlternativeFunctionsSniff.php getGroups()', ksorted($alternatives)],
    ['WP_DATETIME_RESTRICTED', 'list<string>', 'Sniffs/DateTime/RestrictedFunctionsSniff.php getGroups()',
        group_functions($wpcs, 'Sniffs/DateTime/RestrictedFunctionsSniff.php')],
    ['DISCOURAGED_CONSTANTS', 'array<string, string> constant name => suggested replacement',
        'Sniffs/WP/DiscouragedConstantsSniff.php ($discouraged_constants)',
        ksorted(wpcs_array($wpcs, 'Sniffs/WP/DiscouragedConstantsSniff.php', 'discouraged_constants'), lower: false)],
    ['CORE_ROLES', 'list<string>', 'Sniffs/WP/CapabilitiesSniff.php ($core_roles)',
        keys($wpcs, 'Sniffs/WP/CapabilitiesSniff.php', 'core_roles')],
    ['CORE_CAPABILITIES', 'list<string>', 'Sniffs/WP/CapabilitiesSniff.php ($core_capabilities)',
        keys($wpcs, 'Sniffs/WP/CapabilitiesSniff.php', 'core_capabilities')],
    ['DEPRECATED_CAPABILITIES', 'array<string, string> capability => version deprecated since',
        'Sniffs/WP/CapabilitiesSniff.php ($deprecated_capabilities)',
        ksorted(wpcs_array($wpcs, 'Sniffs/WP/CapabilitiesSniff.php', 'deprecated_capabilities'))],
    ['DEPRECATED_CLASSES', 'array<string, string> class name => version deprecated since',
        'Sniffs/WP/DeprecatedClassesSniff.php ($deprecated_classes)',
        ksorted(array_map(
            static fn(array $c): string => $c['version'],
            wpcs_array($wpcs, 'Sniffs/WP/DeprecatedClassesSniff.php', 'deprecated_classes'),
        ))],
    ['DEPRECATED_FUNCTIONS', 'array<string, array{alt: string, version: string}>',
        'Sniffs/WP/DeprecatedFunctionsSniff.php ($deprecated_functions)', $deprecatedFunctions, 1],
    ['DEPRECATED_PARAMETERS', 'array<string, array<int, array{name: string|list<string>, value: mixed, version: string}>> function name => 1-based parameter position => deprecation info',
        'Sniffs/WP/DeprecatedParametersSniff.php ($target_functions)', $deprecatedParameters],
    ['DEPRECATED_PARAMETER_VALUES', 'array<string, array<int, array{name: string|list<string>, values: array<string, array{alt: string, version: string}>}>> function name => 1-based parameter position => deprecated value info',
        'Sniffs/WP/DeprecatedParameterValuesSniff.php ($target_functions)', $deprecatedValues],
    ['SLOW_DB_QUERY_KEYS', 'list<string>', "Sniffs/DB/SlowDBQuerySniff.php getGroups() ('slow_db_query' group keys)",
        sorted(wpcs_array($wpcs, 'Sniffs/DB/SlowDBQuerySniff.php', 'getGroups()')['slow_db_query']['keys'])],
    ['POSTS_PER_PAGE_KEYS', 'list<string>', "Sniffs/WP/PostsPerPageSniff.php getGroups() ('posts_per_page' group keys)",
        sorted(wpcs_array($wpcs, 'Sniffs/WP/PostsPerPageSniff.php', 'getGroups()')['posts_per_page']['keys'])],
    ['PLUGIN_MENU_SLUG_FUNCTIONS', 'list<string>', 'Sniffs/Security/PluginMenuSlugSniff.php ($target_functions)',
        keys($wpcs, 'Sniffs/Security/PluginMenuSlugSniff.php', 'target_functions')],
    // WPCS hardcodes these positions in process_parameters(); kept here so the file stays whole.
    ['ENQUEUE_FUNCTIONS', 'array<string, array{ver: int, in_footer?: int}> function name => 0-based parameter positions',
        'Sniffs/WP/EnqueuedResourceParametersSniff.php (process_parameters(), positions are hardcoded there, not stored in $target_functions)',
        [
            'wp_register_script' => ['ver' => 3, 'in_footer' => 4],
            'wp_enqueue_script' => ['ver' => 3, 'in_footer' => 4],
            'wp_register_style' => ['ver' => 3],
            'wp_enqueue_style' => ['ver' => 3],
        ]],
    ['I18N_FUNCTIONS', 'array<string, string> function name => i18n type', 'Sniffs/WP/I18nSniff.php ($i18n_functions)',
        ksorted(wpcs_array($wpcs, 'Sniffs/WP/I18nSniff.php', 'i18n_functions'))],
];

/** Tokens without whitespace and comments. */
function code_tokens(string $file): array
{
    return array_values(array_filter(
        token_get_all((string) file_get_contents($file)),
        static fn($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
    ));
}

function text($t): string
{
    return is_array($t) ? $t[1] : $t;
}

/** The arguments of the call whose `(` is at $open, each a token slice. */
function call_args(array $tokens, int $open): array
{
    $args = [[]];
    $depth = 0;
    for ($i = $open + 1; $i < count($tokens); $i++) {
        $text = text($tokens[$i]);
        if ($depth === 0 && ($text === ',' || $text === ')')) {
            if ($text === ')') {
                return $args;
            }
            $args[] = [];
            continue;
        }
        $depth += match ($text) {
            '(', '[' => 1,
            ')', ']' => -1,
            default => 0,
        };
        $args[array_key_last($args)][] = $tokens[$i];
    }
    return $args;
}

/** The right-hand sides assigned (or `.=` appended) to $variable between the enclosing function's start and $at. */
function assignments(array $tokens, int $at, string $variable): array
{
    $values = [];
    for ($i = $at; $i > 0 && !(is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION); $i--) {
        $append = is_array($tokens[$i + 1] ?? null) && $tokens[$i + 1][0] === T_CONCAT_EQUAL;
        if (!is_array($tokens[$i]) || $tokens[$i][1] !== $variable || (($tokens[$i + 1] ?? null) !== '=' && !$append)) {
            continue;
        }
        // `.=` appends to whatever the variable held: `*` followed by the appended text.
        $value = $append ? [[T_STRING, '*'], '.'] : [];
        $depth = 0;
        for ($j = $i + 2; $j < count($tokens); $j++) {
            $text = text($tokens[$j]);
            if ($depth === 0 && in_array($text, [';', ',', ')'], true)) {
                break;
            }
            $depth += match ($text) {
                '(', '[' => 1,
                ')', ']' => -1,
                default => 0,
            };
            $value[] = $tokens[$j];
        }
        $values[] = $value;
    }
    return $values;
}

/**
 * The message codes an expression can produce, `*` standing for a dynamic part: literals,
 * concatenations, `MessageHelper::stringToErrorcode()` and variables assigned in the same function.
 */
function code_patterns(array $tokens, array $expr, int $at): array
{
    if (count($expr) === 1 && is_array($expr[0]) && $expr[0][0] === T_VARIABLE) {
        $patterns = [];
        foreach (assignments($tokens, $at, $expr[0][1]) as $value) {
            $patterns = [...$patterns, ...code_patterns($tokens, $value, $at)];
        }
        return $patterns === [] ? ['*'] : array_values(array_unique($patterns));
    }
    if (text($expr[0] ?? '') === 'MessageHelper' && text($expr[2] ?? '') === 'stringToErrorcode') {
        return code_patterns($tokens, array_slice($expr, 4, -1), $at);
    }
    $pattern = '';
    $depth = 0;
    $part = [];
    foreach ([...$expr, '.'] as $t) {
        $text = text($t);
        if ($depth === 0 && $text === '.') {
            $resolved = count($part) === 1 && is_array($part[0]) && $part[0][0] === T_VARIABLE
                ? code_patterns($tokens, $part, $at) : ['*'];
            $pattern .= match (true) {
                count($part) === 1 && is_array($part[0]) && $part[0][0] === T_CONSTANT_ENCAPSED_STRING
                    => substr($part[0][1], 1, -1),
                count($resolved) === 1 => $resolved[0],
                default => '*',
            };
            $part = [];
            continue;
        }
        $depth += match ($text) {
            '(', '[' => 1,
            ')', ']' => -1,
            default => 0,
        };
        $part[] = $t;
    }
    return [preg_replace('/\*+/', '*', $pattern)];
}

/** `error`, `warning`, or `error|warning` when the sniff decides at runtime. */
function level_of(array $tokens, array $expr, int $at): string
{
    $text = strtolower(implode('', array_map(text(...), $expr)));
    if ($text === 'true' || $text === 'false') {
        return $text === 'true' ? 'error' : 'warning';
    }
    if (count($expr) === 1 && is_array($expr[0]) && $expr[0][0] === T_VARIABLE) {
        $levels = array_values(array_unique(array_map(
            static fn(array $value): string => level_of($tokens, $value, $at),
            assignments($tokens, $at, $expr[0][1]),
        )));
        return count($levels) === 1 ? $levels[0] : 'error|warning';
    }
    return 'error|warning';
}

/**
 * Message code (`*` matches any text) => level, from each `add[Fixable](Error|Warning|Message)`
 * call in the sniff and each `getGroups()` group's `type`.
 */
function sniff_levels(string $wpcs, string $file): array
{
    $tokens = code_tokens($file);
    $levels = [];
    foreach ($tokens as $i => $t) {
        if (!is_array($t) || $t[0] !== T_STRING || ($tokens[$i + 1] ?? null) !== '('
            || !preg_match('/^add(?:Fixable)?(Error|Warning|Message)$/', $t[1], $m)) {
            continue;
        }
        $args = call_args($tokens, $i + 1);
        // File::addError($message, $stackPtr, $code, ...);
        // MessageHelper::addMessage($phpcsFile, $message, $stackPtr, $isError, $code, ...).
        [$level, $code] = $m[1] === 'Message'
            ? [level_of($tokens, $args[3], $i), $args[4]]
            : [strtolower($m[1]), $args[2]];
        foreach (code_patterns($tokens, $code, $i) as $pattern) {
            $levels[$pattern] = isset($levels[$pattern]) && $levels[$pattern] !== $level ? 'error|warning' : $level;
        }
    }
    // The restriction sniffs' groups report `<group>_<matched name>` at the group's `type`.
    if (preg_match("/extends Abstract\\w+RestrictionsSniff.*'type'\\s*=>/s", (string) file_get_contents($file))) {
        foreach (wpcs_array($wpcs, substr($file, strlen("{$wpcs}/WordPress/")), 'getGroups()') as $group => $def) {
            if (isset($def['type'])) {
                $levels["{$group}_*"] = $def['type'];
            }
        }
    }
    ksort($levels, SORT_STRING | SORT_FLAG_CASE);
    return $levels;
}

require_once __DIR__ . '/../src/Internal/SniffMap.php';
$sniffLevels = [];
// Every mapped sniff: the extension rules take their levels from here, and bin/generate-presets.php
// sets the level of the Mago core rules the presets keep on.
foreach (array_keys(Rlorenzo\MagoWordPress\Internal\SniffMap::RULES) as $sniff) {
    [$standard, $category, $name] = explode('.', $sniff);
    $sniffLevels[$sniff] = sniff_levels($wpcs, match ($standard) {
        'WordPress' => "{$wpcs}/WordPress/Sniffs/{$category}/{$name}Sniff.php",
        'Universal', 'Modernize', 'NormalizedArrays' => "{$vendor}/phpcsstandards/phpcsextra/{$standard}/Sniffs/{$category}/{$name}Sniff.php",
        default => "{$vendor}/squizlabs/php_codesniffer/src/Standards/{$standard}/Sniffs/{$category}/{$name}Sniff.php",
    });
}
// The level comes from `$superglobals` at runtime: `$_POST`/`$_FILES` (Missing) are errors,
// `$_GET`/`$_REQUEST` (Recommended) warnings.
$sniffLevels['WordPress.Security.NonceVerification'] = ['Missing' => 'error', 'Recommended' => 'warning'];
// The `<type>` overrides in the WordPress standard's rulesets (WordPress-Extra makes two I18n codes errors).
foreach (glob("{$wpcs}/WordPress-*/ruleset.xml") as $ruleset) {
    foreach ((new SimpleXMLElement((string) file_get_contents($ruleset)))->rule as $rule) {
        $parts = explode('.', (string) $rule['ref']);
        $sniff = implode('.', array_slice($parts, 0, 3));
        if (isset($rule->type, $parts[3], $sniffLevels[$sniff])) {
            $sniffLevels[$sniff][$parts[3]] = (string) $rule->type;
        }
    }
}
ksort($sniffLevels);

$version = WPCS_VERSION;
$lists = <<<PHP
    <?php

    declare(strict_types=1);

    namespace Rlorenzo\MagoWordPress\Internal\WordPress;

    /**
     * Function, class, constant and capability lists ported from WordPress Coding Standards {$version}
     * (https://github.com/WordPress/WordPress-Coding-Standards, MIT). Keep entries lowercase and sorted.
     *
     * Generated from the WPCS source arrays (tokenized and evaluated, not hand-typed) — do not
     * hand-edit; regenerate from WPCS instead if the data needs to be refreshed.
     *
     * @internal
     */
    final class Lists
    {

    PHP;
foreach ($constants as $constant) {
    [$name, $var, $source, $value, $inline] = $constant + [4 => null];
    $lists .= "    /**\n     * @var {$var}\n     * Source: WPCS {$source}\n     */\n"
        . "    public const {$name} = " . render($value, 1, $inline) . ";\n\n";
}
$lists .= <<<PHP
        /**
         * Bundled-library and default-theme class names in their properly cased form, by the
         * sniff's group name (its `exclude` property drops groups); the WP core group
         * (`wp_classes`) lives in `CoreClasses`.
         *
         * @var array<string, list<string>>
         * Source: WPCS Sniffs/WP/ClassNameCaseSniff.php (every class group except \$wp_classes)
         */
        public const CLASS_NAME_CASE_BUNDLED_CLASSES = 
    PHP . render($bundled) . <<<PHP
    ;

        private function __construct() {}
    }

    PHP;

$core = file_get_contents(__DIR__ . '/../src/Internal/WordPress/CoreClasses.php');
$names = '';
foreach (wpcs_array($wpcs, $classNameCase, 'wp_classes') as $class) {
    $names .= '        ' . literal($class) . ",\n";
}
$core = preg_replace('/(WPCS )[\d.]+(\'s)/', '${1}' . $version . '$2', $core);
$core = preg_replace('/(public const NAMES = \[\n).*?(    \];)/s', '$1' . str_replace('\\', '\\\\', $names) . '$2', $core);

$stale = 0;
$levels = <<<PHP
    <?php

    declare(strict_types=1);

    namespace Rlorenzo\MagoWordPress\Internal\WordPress;

    /**
     * Whether phpcs reports each message code of the sniffs this package ports as an error or a
     * warning, from WordPress Coding Standards {$version} and the phpcs/PHPCSExtra sniffs it pulls in.
     *
     * Generated by bin/generate-lists.php from each sniff's `addError()` / `addWarning()` /
     * `addMessage(\$isError)` calls, its `getGroups()` types and the WordPress rulesets' `<type>`
     * overrides — do not hand-edit.
     *
     * @internal
     */
    final class Levels
    {
        /**
         * Sniff => message code (`*` matches any text) => `error`, `warning`, or `error|warning`
         * when the sniff decides at runtime (deprecations against `minimum_wp_version`, dynamic names).
         *
         * @var array<string, array<string, 'error'|'warning'|'error|warning'>>
         */
        public const SNIFFS
    PHP . ' = ' . render($sniffLevels) . <<<PHP
    ;

        private function __construct() {}
    }

    PHP;

foreach (['Lists.php' => $lists, 'CoreClasses.php' => $core, 'Levels.php' => $levels] as $file => $contents) {
    $path = __DIR__ . "/../src/Internal/WordPress/{$file}";
    if (is_file($path) && file_get_contents($path) === $contents) {
        continue;
    }
    if ($check) {
        fwrite(STDERR, "{$file} is out of date with WPCS {$version}; run php bin/generate-lists.php <wpcs-dir>\n");
        $stale = 1;
    } else {
        file_put_contents($path, $contents);
        echo "wrote {$file}\n";
    }
}
exit($stale);
