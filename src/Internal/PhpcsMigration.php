<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use DOMElement;
use DOMXPath;
use Rlorenzo\MagoWordPress\Settings;
use stdClass;

use function array_filter;
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
use function json_decode;
use function json_encode;
use function ksort;
use function preg_match;
use function preg_replace_callback;
use function rtrim;
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
    private const ENABLED_BY_EXTENDS = ['nonce-verification', 'validated-sanitized-input', 'prepared-sql'];

    /** @var list<string> */
    private array $unmapped = [];

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

        $result = self::migrate((string) file_get_contents($ruleset), basename($ruleset));
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

        return $text;
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
    public static function migrate(string $xml, string $rulesetName): ?array
    {
        $xpath = PhpcsRuleset::load($xml);
        if ($xpath === null) {
            return null;
        }

        $migration = new self($xpath);
        $values = PhpcsRuleset::values($xml);
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
     * @return array<string, array{enabled?: bool, exclude?: list<string>, level?: string}>
     */
    private function coreRules(array $patterns): array
    {
        $rules = [];
        foreach (Report::SNIFF_RULES as $sniff => $codes) {
            $core = array_values(array_filter(
                $codes,
                static fn(string $code): bool => !str_starts_with($code, 'wordpress/'),
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

            if ($settings === []) {
                continue;
            }

            foreach ($core as $rule) {
                // A partial override of a rule the shipped config enables must keep it enabled.
                $rules[$rule] = ($settings['enabled'] ?? true)
                && in_array($rule, self::ENABLED_BY_EXTENDS, strict: true)
                    ? ['enabled' => true, ...$settings]
                    : $settings;
            }
        }

        ksort($rules);

        return $rules;
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
        $reason = match (true) {
            $ref === 'WordPress-Docs' => 'documentation sniffs are not ported',
            str_starts_with($ref, 'PHPCompatibility') => 'ignored (the package targets PHP 8.1+)',
            !str_starts_with($ref, 'WordPress.') && count($parts) === 1 && !str_contains($ref, '/')
                => 'custom or third-party standard; its contents are not followed',
            !str_starts_with($ref, 'WordPress.')
                => 'not a WPCS WordPress sniff, so not mapped (a Mago core rule may cover it)',
            count($parts) >= 3 && (Report::SNIFF_RULES[$sniff] ?? null) === null
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

        $rules = Report::SNIFF_RULES[$sniff] ?? [];
        $extension = array_values(array_filter($rules, static fn(string $code): bool => str_starts_with(
            $code,
            'wordpress/',
        )));
        // A <type> on a whole sniff that only core rules port becomes their level.
        if (PhpcsRuleset::elements($this->xpath, 'type', $rule) !== [] && ($ref !== $sniff || $extension !== [])) {
            $this->unmapped[] =
                "<type> on {$ref}: Mago cannot re-level "
                . ($extension !== [] ? 'extension rules (' . implode(', ', $extension) . ')' : 'part of a rule');
        }

        foreach (PhpcsRuleset::elements($this->xpath, 'exclude-pattern[@type="relative"]', $rule) as $pattern) {
            $this->unmapped[] = "<exclude-pattern type=\"relative\">{$pattern->textContent}</exclude-pattern> on {$ref}: only absolute patterns are supported per rule";
        }

        foreach (PhpcsRuleset::elements($this->xpath, 'properties/property', $rule) as $property) {
            $name = $property->getAttribute('name');
            if (!in_array($name, PhpcsRuleset::OWNED_PROPERTIES[$ref] ?? [], strict: true)) {
                $this->unmapped[] = "property {$name} on {$ref}: no setting";
            }
        }
    }

    /**
     * @param array<string, array{enabled?: bool, exclude?: list<string>, level?: string}> $coreRules
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
                        default => self::string($value),
                    };
                    $pairs[] = $key . ' = ' . $literal;
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
            foreach (Report::SNIFF_RULES as $sniff => $rules) {
                $related =
                    $code === $sniff || str_starts_with($sniff, $code . '.') || str_starts_with($code, $sniff . '.');
                $toExtension = array_filter($rules, static fn(string $rule): bool => str_starts_with(
                    $rule,
                    'wordpress/',
                )) !== [];
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
