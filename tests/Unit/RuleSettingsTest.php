<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function is_file;
use function json_encode;
use function mkdir;
use function rmdir;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Lints a fixture through a real worker whose project settings come from composer.json,
 * as a consuming project's would. Ports the Rust option tests of the same names.
 */
final class RuleSettingsTest extends TestCase
{
    private const FILES = ['composer.json', 'mago.toml', 'fixture.php'];

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('mago-wordpress-', more_entropy: true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (self::FILES as $name) {
            if (!is_file("{$this->directory}/{$name}")) {
                continue;
            }

            unlink("{$this->directory}/{$name}");
        }

        rmdir($this->directory);
    }

    /**
     * @return iterable<string, array{string, array<string, bool|int|string|list<string>|array<string, list<string>>>, string, int, 4?: string}>
     */
    public static function cases(): iterable
    {
        yield 'custom_limit_is_respected' => [
            'wordpress/posts-per-page',
            ['max-posts-per-page' => 10],
            "\$query = new WP_Query(['posts_per_page' => 25]);",
            1,
        ];
        yield 'negative_max_falls_back_to_default' => [
            'wordpress/posts-per-page',
            ['max-posts-per-page' => -5],
            "\$query = new WP_Query(['posts_per_page' => 10, 'numberposts' => '10']);",
            0,
        ];
        yield 'negative_max_still_flags_over_default' => [
            'wordpress/posts-per-page',
            ['max-posts-per-page' => -5],
            "\$query = new WP_Query(['posts_per_page' => '500']);",
            1,
        ];
        yield 'raised_limit_is_respected' => [
            'wordpress/posts-per-page',
            ['max-posts-per-page' => 1000],
            "\$query = new WP_Query(['posts_per_page' => 500]);",
            0,
        ];
        yield 'configured_minimum_is_respected' => [
            'wordpress/cron-interval',
            ['min-cron-interval' => 60],
            "add_filter('cron_schedules', function (\$s) { \$s['m'] = ['interval' => 60, 'display' => 'M']; return \$s; });",
            0,
        ];
        yield 'raised_minimum_is_respected' => [
            'wordpress/cron-interval',
            ['min-cron-interval' => 3600],
            "add_filter('cron_schedules', function (\$s) { \$s['h'] = ['interval' => 1800, 'display' => 'H']; return \$s; });",
            1,
        ];
        yield 'identifier_placeholder_is_supported_from_wp_6_2' => [
            'wordpress/prepared-sql-placeholders',
            ['minimum-wp-version' => '6.2'],
            "\$wpdb->prepare('SELECT * FROM %i WHERE %2\$i = %3\$d', \$table, \$column, \$id);",
            0,
        ];
        yield 'quoted_identifier_placeholder_is_flagged_from_wp_6_2' => [
            'wordpress/prepared-sql-placeholders',
            ['minimum-wp-version' => '6.2.1'],
            "\$wpdb->prepare('SELECT * FROM `%i` WHERE ID = %d', \$table, \$id);",
            1,
        ];
        yield 'identifier_placeholder_is_unsupported_before_wp_6_2' => [
            'wordpress/prepared-sql-placeholders',
            ['minimum-wp-version' => '6.1'],
            "\$wpdb->prepare('SELECT * FROM %i WHERE ID = %d', \$table, \$id);",
            1,
        ];
        yield 'identifier_placeholder_is_supported_under_unparsable_minimum' => [
            'wordpress/prepared-sql-placeholders',
            ['minimum-wp-version' => 'latest'],
            "\$wpdb->prepare('SELECT * FROM %i WHERE ID = %d', \$table, \$id);",
            0,
        ];
        yield 'additional_word_delimiters_are_allowed' => [
            'wordpress/valid-hook-name',
            ['additional-word-delimiters' => '/.'],
            "do_action('myplugin/loaded');\n\$value = apply_filters('myplugin.option.value', \$value);",
            0,
        ];
        yield 'other_delimiters_are_still_flagged' => [
            'wordpress/valid-hook-name',
            ['additional-word-delimiters' => '/'],
            "do_action('myplugin-loaded');",
            1,
        ];
        yield 'forbidden_prefix_is_reported' => [
            'wordpress/prefix-all-globals',
            ['prefixes' => ['wp', 'myplugin']],
            'function myplugin_init() {}',
            1,
        ];
        yield 'short_prefix_is_reported' => [
            'wordpress/prefix-all-globals',
            ['prefixes' => 'ab'],
            'function ab_init() {}',
            1,
        ];
        yield 'valid_prefixes_are_not_reported' => [
            'wordpress/prefix-all-globals',
            ['prefixes' => ['abc', 'myplugin']],
            "function abc_init() {}\ndo_action('myplugin_init');",
            0,
        ];
        yield 'phpcs_comments_are_honoured_by_default' => [
            'wordpress/safe-redirect',
            [],
            "wp_redirect(\$url); // phpcs:ignore WordPress.Security.SafeRedirect",
            0,
        ];
        yield 'phpcs_comments_are_ignored_when_disabled' => [
            'wordpress/safe-redirect',
            ['honor-phpcs-comments' => false],
            "wp_redirect(\$url); // phpcs:ignore WordPress.Security.SafeRedirect",
            1,
        ];
        // Ports WPCS's `$text_domain_is_default`/`$text_domain_contains_default` cases from I18nUnitTest.1.inc.
        yield 'default_only_domain_allows_missing_argument' => [
            'wordpress/wp-i18n',
            ['text-domains' => ['default']],
            "__('Greeting');",
            0,
        ];
        yield 'default_only_domain_flags_explicit_default_as_superfluous' => [
            'wordpress/wp-i18n',
            ['text-domains' => ['default']],
            "__('Greeting', 'default');",
            1,
            // WPCS's SuperfluousDefaultTextDomain.
            'Superfluous `default` text domain',
        ];
        yield 'default_only_domain_still_flags_other_domains' => [
            'wordpress/wp-i18n',
            ['text-domains' => ['default']],
            "__('Greeting', 'foo');",
            1,
            // WPCS's TextDomainMismatch.
            'Unexpected text domain',
        ];
        yield 'mixed_default_domain_warns_on_missing_argument' => [
            'wordpress/wp-i18n',
            ['text-domains' => ['default', 'my-plugin']],
            "__('Greeting');",
            1,
            // WPCS's MissingArgDomainDefault.
            'pass `default` as the text domain explicitly',
        ];
        yield 'exclude_pattern_silences_its_code_in_matching_files' => [
            'wordpress/safe-redirect',
            ['exclude-patterns' => ['WordPress.Security.SafeRedirect' => ['/fixture\\.php']]],
            'wp_redirect($url);',
            0,
        ];
        yield 'exclude_pattern_for_other_files_keeps_reports' => [
            'wordpress/safe-redirect',
            ['exclude-patterns' => ['WordPress.Security.SafeRedirect' => ['/tests/*']]],
            'wp_redirect($url);',
            1,
        ];
        yield 'excluded_category_covers_its_sniffs' => [
            'wordpress/safe-redirect',
            ['exclude-patterns' => ['WordPress.Security' => ['*']]],
            'wp_redirect($url);',
            0,
        ];
        yield 'excluded_sibling_message_code_keeps_reports' => [
            'wordpress/safe-redirect',
            ['exclude-patterns' => ['WordPress.Security.SafeRedirect.SomethingElse' => ['*']]],
            'wp_redirect($url);',
            1,
        ];
        yield 'mixed_case_property_is_reported_by_default' => [
            'wordpress/valid-variable-name',
            [],
            '$count = $node->childNodes;',
            1,
        ];
        yield 'allowed_custom_properties_are_not_reported' => [
            'wordpress/valid-variable-name',
            ['allowed-custom-properties' => ['childNodes']],
            '$count = $node->childNodes;',
            0,
        ];
        yield 'mixed_default_domain_allows_explicit_default' => [
            'wordpress/wp-i18n',
            ['text-domains' => ['default', 'my-plugin']],
            "__('Greeting', 'default');",
            0,
        ];
    }

    /**
     * @param array<string, bool|int|string|list<string>|array<string, list<string>>> $settings
     */
    #[DataProvider('cases')]
    public function testRuleHonoursSettings(
        string $rule,
        array $settings,
        string $code,
        int $issues,
        ?string $expectedText = null,
    ): void {
        $worker = dirname(__DIR__, levels: 2) . '/resources/worker.php';
        file_put_contents("{$this->directory}/composer.json", json_encode(['extra' => [
            'mago-wordpress' => $settings,
        ]], flags: JSON_THROW_ON_ERROR));
        file_put_contents(
            "{$this->directory}/mago.toml",
            "version = \"1\"\nphp-version = \"8.1\"\n[linter]\nminimum-fail-level = \"note\"\n"
            . "[extension-hosts.wordpress]\ncommand = [\"php\", "
            . json_encode($worker, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
            . "]\n",
        );
        file_put_contents("{$this->directory}/fixture.php", "<?php\n\n{$code}\n");

        $mago = dirname(__DIR__, levels: 2) . '/vendor/bin/mago';
        $output = [];
        $status = 0;
        exec(
            escapeshellarg($mago)
            . ' --workspace '
            . escapeshellarg($this->directory)
            . ' lint --only '
            . escapeshellarg($rule)
            . ' fixture.php 2>&1',
            $output,
            $status,
        );

        $report = implode("\n", $output);
        self::assertSame($issues, $status, $report);
        self::assertSame($issues === 1, str_contains($report, $expectedText ?? $rule), $report);
    }
}
