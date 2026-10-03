<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function str_contains;

/**
 * Lints a fixture through a real worker whose project settings come from composer.json,
 * as a consuming project's would.
 */
final class RuleSettingsTest extends TestCase
{
    use TempProject;

    /**
     * @return iterable<string, array{string, array<string, bool|int|string|list<string>|array<string, list<string>>>, string, int, 4?: string}>
     */
    public static function cases(): iterable
    {
        yield 'custom_auto_escaped_get_the_id_is_honoured' => [
            'wordpress/escape-output',
            ['custom-auto-escaped-functions' => ['get_the_ID']],
            'echo get_the_ID();',
            0,
        ];
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
            'wordpress/valid-hook-name-warning',
            ['additional-word-delimiters' => '/.'],
            "do_action('myplugin/loaded');\n\$value = apply_filters('myplugin.option.value', \$value);",
            0,
        ];
        yield 'other_delimiters_are_still_flagged' => [
            'wordpress/valid-hook-name-warning',
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
        yield 'excluded_group_is_skipped' => [
            'wordpress/discouraged-wp-functions',
            ['exclude-groups' => ['WordPress.PHP.DiscouragedPHPFunctions' => ['serialize']]],
            'serialize($a); base64_encode($b);',
            1,
        ];
        yield 'excluded_only_group_silences_the_sniff' => [
            'wordpress/safe-redirect',
            ['exclude-groups' => ['WordPress.Security.SafeRedirect' => ['wp_redirect']]],
            'wp_redirect($url);',
            0,
        ];
        yield 'excluded_class_group_is_skipped' => [
            'wordpress/class-name-case',
            ['exclude-groups' => ['WordPress.WP.ClassNameCase' => ['wp_classes']]],
            '$query = new wp_query();',
            0,
        ];
        yield 'class_file_prefix_is_checked_by_default' => [
            'wordpress/file-name',
            [],
            'class My_Thing {}',
            1,
        ];
        yield 'non_strict_class_file_names_skip_the_prefix_check' => [
            'wordpress/file-name',
            ['strict-class-file-names' => false],
            'class My_Thing {}',
            0,
        ];
        yield 'custom_test_class_is_skipped' => [
            'wordpress/global-variables-override',
            ['custom-test-classes' => ['\\My_TestClass']],
            'class T extends My_TestClass { function t() { global $post; $post = 1; } }',
            0,
        ];
        yield 'files_as_scoped_skip_file_scope_writes' => [
            'wordpress/global-variables-override',
            ['treat-files-as-scoped' => true],
            '$post = 1;',
            0,
        ];
        yield 'files_as_scoped_still_check_globals_writes' => [
            'wordpress/global-variables-override',
            ['treat-files-as-scoped' => true],
            '$GLOBALS[\'post\'] = 1;',
            1,
        ];
        yield 'files_as_scoped_check_writes_after_a_file_scope_import' => [
            'wordpress/global-variables-override',
            ['treat-files-as-scoped' => true],
            'global $wp_query; $wp_query = 2;',
            1,
        ];
        yield 'unknown_sanitizer_is_reported' => [
            'wordpress/validated-sanitized-input',
            [],
            "if (isset(\$_POST['a'])) { mp_clean(wp_unslash(\$_POST['a'])); }",
            1,
        ];
        yield 'custom_sanitizing_function_sanitizes' => [
            'wordpress/validated-sanitized-input',
            ['custom-sanitizing-functions' => ['mp_clean']],
            "if (isset(\$_POST['a'])) { mp_clean(wp_unslash(\$_POST['a'])); }",
            0,
        ];
        yield 'custom_unslashing_sanitizing_function_needs_no_unslash' => [
            'wordpress/validated-sanitized-input',
            ['custom-unslashing-sanitizing-functions' => ['mp_int']],
            "if (isset(\$_POST['a'])) { mp_int(\$_POST['a']); }",
            0,
        ];
        yield 'custom_nonce_verification_function_verifies' => [
            'wordpress/nonce-verification',
            ['custom-nonce-verification-functions' => ['mp_check_nonce']],
            "mp_check_nonce(); echo sanitize_text_field(wp_unslash(\$_POST['a'] ?? ''));",
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
        [$status, $report] = $this->lint(
            $rule,
            $settings,
            $code,
            linterToml: "[linter]\nminimum-fail-level = \"note\"\n",
        );
        self::assertSame($issues, $status, $report);
        self::assertSame($issues === 1, str_contains($report, $expectedText ?? $rule), $report);
    }

    public function testPrefixProblemsAreReportedOncePerWorker(): void
    {
        // WPCS validates the prefixes once per run, not in every file.
        file_put_contents("{$this->directory}/second.php", data: "<?php\n\nfunction ab_second() {}\n");
        [, $report] = $this->lint(
            'wordpress/prefix-all-globals',
            ['prefixes' => 'ab'],
            'function ab_init() {}',
            flags: '--reporting-format code-count second.php',
            linterToml: "threads = 1\n",
        );
        self::assertStringContainsString('error[wordpress/prefix-all-globals]: 1', $report);
    }
}
