<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use DOMElement;
use DOMXPath;
use Rlorenzo\MagoWordPress\Settings;
use stdClass;

use function array_filter;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_replace;
use function array_slice;
use function array_unique;
use function array_values;
use function basename;
use function count;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_dir;
use function is_int;
use function json_decode;
use function json_encode;
use function ksort;
use function preg_match;
use function preg_replace_callback;
use function rtrim;
use function serialize;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

use const ARRAY_FILTER_USE_BOTH;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const STDERR;

/**
 * Turns a `phpcs.xml` ruleset into the `mago.toml` and composer.json `extra.mago-wordpress`
 * that reproduce it, and lists everything that has no equivalent. `bin/mago-wordpress
 * migrate` prints or writes the result.
 *
 * Extension rules cannot be configured from `mago.toml` (Mago 1.50 rejects extension rule
 * codes under `[linter.rules]`), so everything a ruleset turns off for them goes into the
 * `exclude-patterns` setting, which `Report` applies per WPCS message code with phpcs's own
 * pattern semantics. Mago's core rules get `[linter.rules]` entries instead.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect One pass over every ruleset element kind.
 */
final class PhpcsMigration
{
    public const EXTENDS = 'vendor/rlorenzo/mago-wordpress/wordpress.mago.toml';

    /** Mago core rules that ship disabled and that wordpress.mago.toml turns on. */
    private const ENABLED_BY_EXTENDS = [];

    /**
     * Sniffs that become settings of a Mago core rule (`metricRules()`, `docsRule()`), and the
     * properties of each that are read; any other property is listed as not migrated.
     */
    public const CONFIGURED_SNIFFS = [
        'Generic.Metrics.CyclomaticComplexity' => ['complexity'],
        'Generic.Metrics.NestingLevel' => ['nestingLevel'],
        'Squiz.Commenting.FunctionComment.Missing' => [],
        'Squiz.Commenting.ClassComment.Missing' => [],
        'Squiz.Commenting.VariableComment.Missing' => [],
    ];

    /** What changes for scripts and hooks that ran phpcs. */
    private const HOOK_NOTES = <<<'TXT'

        If a hook or CI script ran phpcs:
        - `mago lint` exits non-zero only on errors; pass `--minimum-fail-level warning` to fail on warnings as phpcs did.
        - `mago lint --reporting-format short` prints `file:line:col: level[code]: message`, like `phpcs --report=emacs`.
        - `// phpcs:ignore` and `phpcs:disable` comments are honoured by this package's rules only; Mago's own rules need `@mago-expect lint:<code>`.

        TXT;

    /** @var list<string> */
    private array $unmapped = [];

    /** @var array<string, list<string>> comment lines to print above a `[linter.rules]` entry */
    private array $comments = [];

    private function __construct(
        private readonly DOMXPath $xpath,
    ) {}

    /**
     * `migrate [<ruleset or directory>] [--write] [--force]`. Prints the result, or with
     * --write saves mago.toml (never over an existing one without --force) and merges
     * `extra.mago-wordpress` into composer.json, beside the ruleset.
     *
     * @param list<string> $args the arguments after `migrate`
     * @return string|int the text to print, or an exit code after an error
     */
    public static function run(array $args, string $cwd): string|int
    {
        $write = in_array('--write', $args, strict: true);
        $force = in_array('--force', $args, strict: true);
        $target =
            array_values(array_filter($args, static fn(string $arg): bool => !str_starts_with($arg, '--')))[0] ?? $cwd;

        $ruleset = $target;
        if (is_dir($target)) {
            $ruleset = null;
            foreach (SettingsDiscovery::RULESETS as $name) {
                if (!file_exists("{$target}/{$name}")) {
                    continue;
                }

                $ruleset = "{$target}/{$name}";
                break;
            }
        }

        if ($ruleset === null || !file_exists($ruleset)) {
            fwrite(
                STDERR,
                "No phpcs ruleset found in {$target} (looked for "
                . implode(', ', SettingsDiscovery::RULESETS)
                . ").\n",
            );

            return 1;
        }

        $result = self::migrate((string) file_get_contents($ruleset), basename($ruleset), dirname($ruleset));
        if ($result === null) {
            fwrite(STDERR, "{$ruleset} is not well-formed XML.\n");

            return 1;
        }

        $unmapped = self::unmappedText($result['unmapped']);
        if (!$write) {
            return (
                "# mago.toml\n{$result['toml']}\n# composer.json \"extra\": {\"mago-wordpress\": ...}\n"
                . self::json((object) $result['extra'], '    ')
                . "\n\nDry run: pass --write to save these.\n"
                . $unmapped
            );
        }

        $dir = dirname($ruleset);
        $toml = "{$dir}/mago.toml";
        if (file_exists($toml) && !$force) {
            fwrite(STDERR, "{$toml} exists; pass --force to overwrite it.\n");

            return 1;
        }

        $composerPath = "{$dir}/composer.json";
        $composer = self::mergedComposer($composerPath, $result['extra']);
        if ($composer === null) {
            fwrite(STDERR, "{$composerPath} is not a JSON object with an object `extra`; nothing written.\n");

            return 1;
        }

        if (file_put_contents($toml, $result['toml']) === false) {
            fwrite(STDERR, "Could not write {$toml}; nothing written.\n");

            return 1;
        }

        if (file_put_contents($composerPath, $composer) === false) {
            fwrite(STDERR, "Wrote {$toml} but could not write {$composerPath}.\n");

            return 1;
        }

        return "Wrote {$toml} and extra.mago-wordpress in {$dir}/composer.json.\n" . $unmapped;
    }

    /**
     * @param list<string> $unmapped
     */
    private static function unmappedText(array $unmapped): string
    {
        $count = count($unmapped);
        $text = $count === 0 ? "\nEverything was migrated.\n" : "\nNot migrated ({$count}):\n";
        foreach ($unmapped as $line) {
            $text .= "- {$line}\n";
        }

        return $text . self::HOOK_NOTES;
    }

    /**
     * The composer.json contents with `extra.mago-wordpress` keys set, keeping the file's other
     * content, key order and indentation. Decoded as objects so empty `{}` blocks stay objects.
     * Null when the file is not a JSON object.
     *
     * @param array<string, mixed> $extra
     */
    private static function mergedComposer(string $path, array $extra): ?string
    {
        $contents = file_exists($path) ? (string) file_get_contents($path) : '{}';
        $match = [];
        $indent = preg_match('/^([ \t]+)"/m', $contents, $match) === 1 ? $match[1] : '    ';
        $composer = self::object(json_decode($contents));
        $composerExtra = $composer === null ? null : self::object($composer->extra ?? new stdClass());
        $block = $composerExtra === null ? null : self::object($composerExtra->{'mago-wordpress'} ?? new stdClass());
        if ($composer === null || $composerExtra === null || $block === null) {
            return null;
        }

        $composerExtra->{'mago-wordpress'} = (object) array_replace((array) $block, $extra);
        $composer->extra = $composerExtra;

        return self::json($composer, $indent) . "\n";
    }

    private static function object(mixed $value): ?stdClass
    {
        return $value instanceof stdClass ? $value : null;
    }

    private static function json(mixed $value, string $indent): string
    {
        $json = (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // json_encode indents by four spaces; re-indent each level with the file's own unit.
        return (string) preg_replace_callback(
            '/^((?:    )+)/m',
            static fn(array $m): string => str_replace(search: '    ', replace: $indent, subject: $m[1]),
            $json,
        );
    }

    /**
     * @return null|array{toml: string, extra: array<string, mixed>, unmapped: list<string>}
     */
    public static function migrate(string $xml, string $rulesetName, ?string $directory = null): ?array
    {
        $xpath = PhpcsRuleset::load($xml, $directory, $rulesetName);
        if ($xpath === null) {
            return null;
        }

        $migration = new self($xpath);
        $values = PhpcsRuleset::values($xml, $directory, $rulesetName);
        $patterns = Settings::fromArray($values)->excludePatterns;

        $toml = $migration->toml($rulesetName, $migration->coreRules($patterns));
        $migration->reportElements();

        return ['toml' => $toml, 'extra' => self::extra($values, $patterns), 'unmapped' => $migration->unmapped];
    }

    /**
     * A phpcs `<exclude-pattern>` (a regex after `*` becomes `.*`, matched unanchored and
     * case-insensitively against the absolute path) as a Mago glob, which matches `[source]
     * excludes` against the absolute path. NULL when the regex uses anything a glob
     * cannot express.
     */
    public static function glob(string $pattern, bool $relative): ?string
    {
        $pattern = trim($pattern);
        $anchored = str_starts_with($pattern, '^');
        if ($anchored) {
            if (!$relative) {
                return null;
            }

            $pattern = substr($pattern, offset: 1);
        }

        $open = !str_ends_with($pattern, '$') || str_ends_with($pattern, '\\$');
        if (!$open) {
            $pattern = substr($pattern, offset: 0, length: -1);
        }

        $glob = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '\\') {
                $next = $pattern[++$i] ?? '';
                // An escaped letter or digit is a regex class (\d, \w, ...); anything else is literal.
                if ($next === '' || preg_match('/[A-Za-z0-9]/', $next) === 1) {
                    return null;
                }

                $glob .= str_contains('*?[]{}', $next) ? '[' . $next . ']' : $next;
                continue;
            }

            if (str_contains('()[]{}|+?^$', $char)) {
                return null;
            }

            $glob .= match ($char) {
                '*' => '*',
                '.' => '?',
                default => $char,
            };
        }

        if (!$anchored && !str_starts_with($glob, '*')) {
            $glob = '*' . $glob;
        }

        return $open && !str_ends_with($glob, '*') ? $glob . '*' : $glob;
    }

    /**
     * `[linter.rules]` entries for Mago's core rules, from the codes the ruleset turns off
     * and the `<type>` it sets on the sniffs they port.
     *
     * @param array<string, list<string>> $patterns
     * @return array<string, array<string, bool|int|string|list<string>>>
     */
    private function coreRules(array $patterns): array
    {
        $rules = [];
        /** @var array<string, array<string, array<string, bool|int|string|list<string>>>> $bySniff */
        $bySniff = [];
        foreach (SniffMap::RULES as $sniff => $codes) {
            $core = array_values(array_filter(
                $codes,
                static fn(string $code): bool => !SniffMap::isExtensionRule($code),
            ));
            if ($core === []) {
                continue;
            }

            $sniffPatterns = self::patternsFor($patterns, $sniff);
            foreach (array_keys($patterns) as $code) {
                if (!str_starts_with($code, $sniff . '.')) {
                    continue;
                }

                $this->unmapped[] =
                    "exclusion of {$code}: Mago's " . implode(', ', $core) . ' has no message codes to scope it to';
            }

            $excludes = in_array('*', $sniffPatterns, strict: true) ? [] : $this->ruleExcludes($sniff, $sniffPatterns);
            $settings = match (true) {
                in_array('*', $sniffPatterns, strict: true) => ['enabled' => false],
                $excludes !== [] => ['exclude' => $excludes],
                default => [],
            };

            $level = $this->level($sniff);
            if ($level !== null && ($settings['enabled'] ?? true)) {
                $settings['level'] = $level;
            }

            foreach ($core as $rule) {
                $bySniff[$rule][$sniff] = $settings;
            }
        }

        foreach ($bySniff as $rule => $sniffSettings) {
            // One Mago rule can cover several sniffs (lowercase-keyword); one sniff's setting
            // must not turn off or re-level the others', so a disagreement leaves it as shipped.
            if (count(array_unique(array_map(serialize(...), $sniffSettings))) > 1) {
                $this->unmapped[] =
                    "Mago's {$rule} covers "
                    . implode(' and ', array_keys($sniffSettings))
                    . ', which the ruleset configures differently; the rule is left as shipped';
                continue;
            }

            $settings = array_values($sniffSettings)[0];
            if ($settings === []) {
                continue;
            }

            // A partial override of a rule the shipped config enables must keep it enabled.
            $rules[$rule] = ($settings['enabled'] ?? true) && in_array($rule, self::ENABLED_BY_EXTENDS, strict: true)
                ? ['enabled' => true, ...$settings]
                : $settings;
        }

        $rules = [...$rules, ...$this->metricRules($patterns)];
        $docs = $this->docsRule($patterns);
        if ($docs !== null) {
            $rules['missing-docs'] = $docs;
        } elseif ($this->leavesOutDocs()) {
            $rules['missing-docs'] = ['enabled' => false];
        }

        ksort($rules);

        return $rules;
    }

    /**
     * The last value a ruleset sets for a property of a sniff, or NULL.
     */
    private function property(string $ref, string $name): ?string
    {
        $query = '//rule[@ref="' . $ref . '"]/properties/property[@name="' . $name . '"]';
        $found = PhpcsRuleset::elements($this->xpath, $query);

        return $found === [] ? null : $found[count($found) - 1]->getAttribute('value');
    }

    private function refers(string $ref): bool
    {
        return PhpcsRuleset::elements($this->xpath, '/ruleset/rule[@ref="' . $ref . '"]') !== [];
    }

    /**
     * What a ruleset's `<exclude-pattern>`s and `<type>` on one sniff add to its Mago rule.
     *
     * @param array<string, list<string>> $patterns
     * @param array<string, bool|int|string|list<string>> $settings
     * @return array<string, bool|int|string|list<string>>
     */
    private function configured(string $ref, array $patterns, array $settings, string $level): array
    {
        $found = self::patternsFor($patterns, $ref);
        if (in_array('*', $found, strict: true)) {
            return ['enabled' => false];
        }

        $settings['level'] = $this->level($ref) ?? $level;
        $excludes = $this->ruleExcludes($ref, $found);

        return $excludes === [] ? $settings : [...$settings, 'exclude' => $excludes];
    }

    /**
     * `cyclomatic-complexity` and `excessive-nesting`, from the phpcs metrics sniffs.
     *
     * @param array<string, list<string>> $patterns
     * @return array<string, array<string, bool|int|string|list<string>>>
     */
    private function metricRules(array $patterns): array
    {
        $rules = [];
        $ref = 'Generic.Metrics.CyclomaticComplexity';
        if ($this->refers($ref)) {
            $threshold = (int) ($this->property($ref, 'complexity') ?? 10);
            $rules['cyclomatic-complexity'] = $this->configured(
                $ref,
                $patterns,
                ['threshold' => $threshold, 'method-threshold' => $threshold],
                'warning',
            );
            $this->comments['cyclomatic-complexity'] = [
                "{$ref} complexity {$threshold}. Without method-threshold Mago does not check methods and scores",
                'a class as the sum of its methods. Mago counts && and || and phpcs does not, so the same',
                'number is stricter there; plain branches score one lower in Mago (phpcs counts the function',
                'itself), so it is laxer there. absoluteComplexity has no equivalent.',
            ];
        }

        $ref = 'Generic.Metrics.NestingLevel';
        if ($this->refers($ref)) {
            $level = (int) ($this->property($ref, 'nestingLevel') ?? 5);
            $rules['excessive-nesting'] = $this->configured($ref, $patterns, ['threshold' => $level + 1], 'warning');
            $this->comments['excessive-nesting'] = [
                "{$ref} nestingLevel {$level}. Mago counts the function body as level 1, so the same depth is",
                'threshold = nestingLevel + 1 (measured: identical on nested if blocks). Mago counts fewer',
                'constructs than phpcs (switch/case, else chains), so it can report less. absoluteNestingLevel',
                'has no equivalent.',
            ];
        }

        return $rules;
    }

    /**
     * `missing-docs` for the `Squiz.Commenting.*Comment.Missing` codes a ruleset names, unless it
     * builds on WordPress or WordPress-Docs (the shipped config already covers those).
     *
     * @param array<string, list<string>> $patterns
     * @return null|array<string, bool|int|string|list<string>>
     */
    private function docsRule(array $patterns): ?array
    {
        $standards = PhpcsRuleset::standards($this->xpath);
        if (in_array('WordPress', $standards, strict: true) || $this->refers('WordPress-Docs')) {
            return null;
        }

        $refs = [
            'functions' => 'Squiz.Commenting.FunctionComment.Missing',
            'classes' => 'Squiz.Commenting.ClassComment.Missing',
            'properties' => 'Squiz.Commenting.VariableComment.Missing',
        ];
        $on = array_filter($refs, $this->refers(...));
        if ($on === []) {
            return null;
        }

        $settings = [
            'enabled' => true,
            'functions' => array_key_exists('functions', $on),
            'methods' => array_key_exists('functions', $on),
            'classes' => array_key_exists('classes', $on),
            'properties' => array_key_exists('properties', $on),
            'constants' => false,
            'enum-cases' => false,
            'statics' => false,
        ];
        $per = array_values(array_map(fn(string $ref): array => $this->configured($ref, $patterns, [], 'error'), $on));
        if (count(array_unique(array_map(serialize(...), $per))) > 1) {
            $this->unmapped[] = 'Squiz.Commenting *Comment.Missing refs have different <exclude-pattern>/<type> settings; Mago has one missing-docs rule, so the first is used';
        }

        $this->comments['missing-docs'] = [
            'Squiz.Commenting.*Comment.Missing: an error, as in phpcs (the shipped config reports at help).',
        ];

        return [...$settings, ...$per[0]];
    }

    /**
     * The shipped config enables `missing-docs` for WordPress-Docs; a ruleset built on
     * WordPress-Core or -Extra alone does not run those sniffs. A custom standard is not followed.
     */
    private function leavesOutDocs(): bool
    {
        $standards = PhpcsRuleset::standards($this->xpath);
        if ($standards === [] || in_array('WordPress', $standards, strict: true)) {
            return false;
        }

        foreach (PhpcsRuleset::elements($this->xpath, '/ruleset/rule[@ref]') as $rule) {
            $ref = $rule->getAttribute('ref');
            if ($ref === 'WordPress-Docs' || str_starts_with($ref, 'Squiz.Commenting')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Patterns set on the sniff itself or on its category or standard.
     *
     * @param array<string, list<string>> $patterns
     * @return list<string>
     */
    private static function patternsFor(array $patterns, string $sniff): array
    {
        $found = [];
        foreach ($patterns as $code => $list) {
            if ($code === $sniff || str_starts_with($sniff, $code . '.')) {
                $found = [...$found, ...$list];
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Per-rule `exclude` globs match workspace-relative paths, so a pattern that phpcs
     * would match after any directory becomes two globs: at the root and below it.
     *
     * @param list<string> $patterns
     * @return list<string>
     */
    private function ruleExcludes(string $sniff, array $patterns): array
    {
        $globs = [];
        foreach ($patterns as $pattern) {
            $glob = self::glob($pattern, relative: false);
            if ($glob === null) {
                $this->unmapped[] = "<exclude-pattern>{$pattern}</exclude-pattern> on {$sniff}: not expressible as a glob";
                continue;
            }

            if (str_starts_with($glob, '*/')) {
                $globs[] = substr($glob, offset: 2);
            }

            $globs[] = $glob;
        }

        return $globs;
    }

    /**
     * The `<type>` set on the sniff itself, as a Mago level.
     */
    private function level(string $sniff): ?string
    {
        $level = null;
        foreach (PhpcsRuleset::elements($this->xpath, '/ruleset/rule[@ref="' . $sniff . '"]/type') as $type) {
            $level = trim($type->textContent) === 'warning' ? 'warning' : 'error';
        }

        return $level;
    }

    /**
     * Lists the ruleset elements that nothing above migrates.
     */
    private function reportElements(): void
    {
        if (PhpcsRuleset::standards($this->xpath) === []) {
            $this->unmapped[] = 'no <rule ref="WordPress|WordPress-Extra|WordPress-Core">: rules a custom standard leaves out are not followed, so every extension rule stays on';
        }

        foreach (PhpcsRuleset::elements($this->xpath, '/ruleset/rule[@ref]') as $rule) {
            $this->reportRule($rule);
        }

        foreach (PhpcsRuleset::elements($this->xpath, '/ruleset/config[@name]') as $config) {
            $name = $config->getAttribute('name');
            if ($name === 'minimum_wp_version' || $name === 'minimum_supported_wp_version') {
                continue;
            }

            $this->unmapped[] = $name === 'testVersion'
                ? '<config name="testVersion">: ignored (the package targets PHP 8.1+); set php-version in mago.toml'
                : "<config name=\"{$name}\">: no equivalent";
        }

        $options = [];
        foreach (PhpcsRuleset::elements(
            $this->xpath,
            '/ruleset/*[self::arg or self::ini or self::autoload]',
        ) as $element) {
            $options[] = $element->getAttribute('name') !== ''
                ? $element->getAttribute('name')
                : $element->getAttribute('value');
        }

        if ($options !== []) {
            $this->unmapped[] =
                '<arg>/<ini> (' . implode(', ', $options) . '): phpcs command-line options, no equivalent';
        }
    }

    private function reportRule(DOMElement $rule): void
    {
        $ref = $rule->getAttribute('ref');
        if (in_array($ref, ['WordPress', 'WordPress-Extra', 'WordPress-Core'], strict: true)) {
            return;
        }

        $parts = explode('.', $ref);
        $sniff = implode('.', array_slice($parts, offset: 0, length: 3));
        $configured = array_key_exists($ref, self::CONFIGURED_SNIFFS);
        $reason = match (true) {
            $configured => null,
            $ref === 'WordPress-Docs' => 'only missing docblocks are checked (missing-docs); see docs/wpcs-coverage.md',
            str_starts_with($ref, 'PHPCompatibility') => 'ignored (the package targets PHP 8.1+)',
            !str_starts_with($ref, 'WordPress.') && count($parts) === 1 && !str_contains($ref, '/')
                => 'custom or third-party standard; its contents are not followed',
            !str_starts_with($ref, 'WordPress.') && (SniffMap::RULES[$sniff] ?? null) === null
                => 'non-WordPress sniff with no Mago rule mapped to it',
            count($parts) >= 3 && (SniffMap::RULES[$sniff] ?? null) === null
                => 'WPCS sniff with no Mago port (formatting sniffs are `mago fmt`\'s job)',
            default => null,
        };
        if ($reason !== null) {
            $this->unmapped[] = "<rule ref=\"{$ref}\">: {$reason}";

            return;
        }

        foreach (PhpcsRuleset::elements($this->xpath, 'include-pattern', $rule) as $pattern) {
            $this->unmapped[] = "<include-pattern>{$pattern->textContent}</include-pattern> on {$ref}: no per-rule include; the rule runs on every file";
        }

        $rules = SniffMap::RULES[$sniff] ?? [];
        $extension = array_values(array_filter($rules, SniffMap::isExtensionRule(...)));
        // A <type> on a whole sniff that only core rules port becomes their level.
        if (
            !$configured
            && PhpcsRuleset::elements($this->xpath, 'type', $rule) !== []
            && ($ref !== $sniff || $extension !== [])
        ) {
            $this->unmapped[] =
                "<type> on {$ref}: Mago cannot re-level "
                . ($extension !== [] ? 'extension rules (' . implode(', ', $extension) . ')' : 'part of a rule');
        }

        foreach (PhpcsRuleset::elements($this->xpath, 'exclude-pattern[@type="relative"]', $rule) as $pattern) {
            $this->unmapped[] = "<exclude-pattern type=\"relative\">{$pattern->textContent}</exclude-pattern> on {$ref}: only absolute patterns are supported per rule";
        }

        foreach (PhpcsRuleset::elements($this->xpath, 'properties/property', $rule) as $property) {
            $name = $property->getAttribute('name');
            if (!in_array(
                $name,
                self::CONFIGURED_SNIFFS[$ref] ?? PhpcsRuleset::OWNED_PROPERTIES[$ref] ?? [],
                strict: true,
            )) {
                $this->unmapped[] = "property {$name} on {$ref}: " . ($configured ? 'no equivalent' : 'no setting');
                continue;
            }

            if (
                $property->getAttribute('type') === 'array'
                && !array_is_list(PhpcsRuleset::arrayValues($this->xpath, $property))
            ) {
                $this->unmapped[] = "property {$name} on {$ref}: key=>value entries are read as plain values, the keys are ignored";
            }
        }
    }

    /**
     * @param array<string, array<string, bool|int|string|list<string>>> $coreRules
     */
    private function toml(string $rulesetName, array $coreRules): string
    {
        $toml =
            "# Generated by `vendor/bin/mago-wordpress migrate` from {$rulesetName}.\n"
            . 'extends = '
            . self::string(self::EXTENDS)
            . "\n";

        $paths = [];
        foreach (PhpcsRuleset::elements($this->xpath, '/ruleset/file') as $file) {
            $path = rtrim(trim($file->textContent), characters: '/');
            $path = str_starts_with($path, './') ? substr($path, offset: 2) : $path;
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        $excludes = [];
        foreach (PhpcsRuleset::elements($this->xpath, '/ruleset/exclude-pattern') as $pattern) {
            $text = trim($pattern->textContent);
            $glob = self::glob($text, relative: $pattern->getAttribute('type') === 'relative');
            if ($glob === null) {
                $this->unmapped[] = "<exclude-pattern>{$text}</exclude-pattern>: not expressible as a glob; add an equivalent to [source] excludes by hand";
                continue;
            }

            $excludes[] = $glob;
        }

        // phpcs would lint vendor/ too when handed the whole project; a Mago project never wants that.
        if (
            in_array('.', $paths, strict: true)
            && array_filter($excludes, static fn(string $glob): bool => str_contains($glob, 'vendor')) === []
        ) {
            $excludes[] = 'vendor/*';
        }

        if ($paths !== [] || $excludes !== []) {
            $toml .= "\n[source]\n";
            $toml .= $paths !== [] ? 'paths = ' . self::list($paths) . "\n" : '';
            $toml .= $excludes !== [] ? 'excludes = ' . self::list($excludes) . "\n" : '';
        }

        if ($excludes !== []) {
            $toml .= "\n[source.glob]\n# phpcs matches exclude patterns case-insensitively.\ncase-insensitive = true\n";
        }

        if ($coreRules !== []) {
            $toml .= "\n[linter.rules]\n";
            foreach ($coreRules as $rule => $settings) {
                $pairs = [];
                foreach ($settings as $key => $value) {
                    $literal = match (true) {
                        is_array($value) => self::list($value, inline: true),
                        is_bool($value) => $value ? 'true' : 'false',
                        is_int($value) => (string) $value,
                        default => self::string($value),
                    };
                    $pairs[] = $key . ' = ' . $literal;
                }

                foreach ($this->comments[$rule] ?? [] as $comment) {
                    $toml .= "# {$comment}\n";
                }

                $toml .= $rule . ' = { ' . implode(', ', $pairs) . " }\n";
            }
        }

        return $toml;
    }

    /**
     * The `extra.mago-wordpress` block: settings that differ from empty, and the
     * exclusions that reach an extension rule.
     *
     * @param array<string, mixed> $values
     * @param array<string, list<string>> $patterns
     * @return array<string, mixed>
     */
    private static function extra(array $values, array $patterns): array
    {
        $extra = array_filter(
            $values,
            static fn(mixed $value, string $key): bool => (
                $key !== 'exclude-patterns'
                && $value !== null
                && $value !== []
            ),
            ARRAY_FILTER_USE_BOTH,
        );

        $extensionPatterns = [];
        foreach ($patterns as $code => $list) {
            foreach (SniffMap::RULES as $sniff => $rules) {
                $related =
                    $code === $sniff || str_starts_with($sniff, $code . '.') || str_starts_with($code, $sniff . '.');
                $toExtension = array_filter($rules, SniffMap::isExtensionRule(...)) !== [];
                if ($related && $toExtension) {
                    $extensionPatterns[$code] = $list;
                    break;
                }
            }
        }

        if ($extensionPatterns !== []) {
            ksort($extensionPatterns);
            $extra['exclude-patterns'] = $extensionPatterns;
        }

        return $extra;
    }

    /**
     * @param list<string> $values
     */
    private static function list(array $values, bool $inline = false): string
    {
        $items = array_map(self::string(...), $values);
        if ($inline || count($items) === 1) {
            return '[' . implode(', ', $items) . ']';
        }

        return "[\n    " . implode(",\n    ", $items) . ",\n]";
    }

    /**
     * A TOML basic string: JSON's string escapes are all valid TOML.
     */
    private static function string(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
