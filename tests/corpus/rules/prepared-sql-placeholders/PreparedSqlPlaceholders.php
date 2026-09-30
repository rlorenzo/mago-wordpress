<?php

declare(strict_types=1);

// correct_placeholders_and_arguments
$wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_title = %s AND ID = %d", $title, $id);

// entirely_dynamic_query_is_ignored
$wpdb->prepare($sql, $id);

// other_methods_are_ignored
$wpdb->query("SELECT * FROM my_table WHERE name = '%s'");
$db->prepare("SELECT * FROM my_table WHERE name = '%s'", $name);

// single_quoted_placeholder
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE name = '%s'", $name);

// double_quoted_placeholder
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('SELECT * FROM my_table WHERE ID = "%d"', $id);

// quoted_complex_placeholders_are_allowed
$wpdb->prepare('SELECT * FROM my_table WHERE name = "%1$s" AND slug = \'%2$s\'', $name, $slug);
$wpdb->prepare("SELECT * FROM my_table WHERE slug = '%5s'", $slug);

// quoted_placeholder_in_concatenated_literals
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE name = '" . "%s'", $name);

// escaped_quotes_around_placeholder
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('SELECT * FROM my_table WHERE name = \'%s\'', $name);

// quoted_placeholder_in_heredoc
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare(<<<SQL
    SELECT * FROM {$wpdb->posts} WHERE post_title = '%s'
    SQL, $title);

// nowdoc_count_mismatch
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare(<<<'SQL'
    SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s
    SQL, $a, $b);

// unsupported_placeholder
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE flags = %x AND name = %s", $flags, $name);

// unsupported_placeholders_are_reported_each_and_count_as_none
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %x AND b = %X AND c = %x", $a);

// unsupported_placeholder_in_interpolated_string
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE flags = %c", $flags);

// too_few_arguments
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s", $a, $b);

// too_many_arguments
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE ID = %d", $id, $extra);

// numbered_placeholders_counted_by_occurrence
$wpdb->prepare('SELECT %1$d, %1$d', 123, 123);
$wpdb->prepare('SELECT * FROM my_table WHERE a = %1$s AND b = %2$d AND c = %1$s', $a, $b, $a);

// repeated_numbered_placeholder_needs_one_replacement_each
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('SELECT %1$d, %1$d', 123);
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('SELECT * FROM my_table WHERE a = %1$s AND b = %2$d AND c = %1$s', $a, $b);

// numbered_placeholder_numbers_are_not_checked
$wpdb->prepare('SELECT * FROM my_table WHERE a = %1$s AND b = %3$d', $a, $b);

// percent_escape_is_not_a_placeholder
$wpdb->prepare("SELECT * FROM my_table WHERE discount = '100%%' AND name = %s", $name);

// like_wildcards_in_the_query_are_reported
// WPCS strips each LIKE operand from the text by its first occurrence, so
// stripping `%` here takes the `%` of `%s` and leaves the wildcard behind.
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE name LIKE %s AND slug LIKE 'admin%' AND path LIKE '%'", $like);

// array_argument_skips_count_check
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s", [$a, $b, $c]);

// single_variable_argument_is_one_replacement
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", $values);

// spread_argument_is_one_replacement
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s", ...$args);

// dynamic_parts_keep_count_check
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM $table WHERE a = %s $extra_where", $a, $b);

// prepare_without_placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE status = 'publish'");

// empty_double_quoted_query_is_useless_prepare
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("");

// empty_single_quoted_query_is_useless_prepare
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('');

// no_placeholders_with_interpolation_is_ignored
$wpdb->prepare("SELECT * FROM {$table} WHERE status = 'publish'");

// no_placeholders_with_arguments_is_mismatch
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE status = 'publish'", $status, $extra);

// wpdb_table_property_keeps_count_check
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_title = %s AND ID = %d", $title, $id, $extra);
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM " . $wpdb->posts . " WHERE ID = %d", $id, $extra);

// array_literal_argument_is_counted
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s", [$a, $b]);

final class PreparedSqlPlaceholdersParams
{
    /** @var list<string> */
    private array $params = [];

    // single_dynamic_argument_is_one_replacement
    public function run(): void
    {
        global $wpdb;
        // @mago-expect lint:wordpress/prepared-sql-placeholders
        // @mago-expect lint:wordpress/prepared-sql-placeholders
        // @mago-expect lint:wordpress/prepared-sql-placeholders
        $wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", $this->params);
        $wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", get_params());
        $wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", [...$this->params]);
    }
}

// quoted_and_mismatch_reported_together
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE name = '%s' AND ID = %d", $name, $id, $extra);

// identifier_placeholder_supported_at_default_minimum
// `%i` arrived in WordPress 6.2; the default minimum-wp-version (6.7) has it.
// The pre-6.2 report is covered by RuleSettingsTest with a lower minimum.
$wpdb->prepare("SELECT * FROM %i WHERE ID = %d", $table, $id);

// quoted_identifier_placeholder_is_still_reported
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('SELECT * FROM `%1$i` WHERE ID = %2$d', $table, $id);

// named_query_argument_is_checked
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare(query: "SELECT * FROM my_table WHERE name = '%s'", args: $name);

// no_placeholders_with_single_variable_argument_is_mismatch
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE status = 'publish'", $status);

// modified_placeholders_are_counted
$wpdb->prepare("SELECT * FROM my_table WHERE a = %05d AND b = %-10s AND c = %.2f AND d = %F", $a, $b, $c, $d);

// modified_placeholder_count_mismatch
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %05d AND b = %.2f", $a, $b, $c);

// modified_unsupported_placeholder
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE flags = %05x", $flags);

// null_safe_prepare_is_checked
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb?->prepare("SELECT * FROM my_table WHERE name = '%s'", $name);

// parenthesized_array_argument_is_one_replacement
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", (['a']));
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", (['a', 2]));

// custom_padding_placeholders_are_counted
$wpdb->prepare("SELECT '%'.5s'", 'abc');
$wpdb->prepare("SELECT * FROM my_table WHERE code = '%1\$'x10s' AND ID = %2\$d", $code, $id);

// custom_padding_placeholder_count_mismatch
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT '%'.5s'", 'abc', 'def');

// like_wildcards_are_reported_not_unsupported_placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a LIKE '%bar%' AND b LIKE \"_x%\" AND c = %d", $c);
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE %s LIKE concat('%%', name, '%x')", $agent);

// like_wildcards_matching_placeholders_are_counted
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a LIKE '%foo%' AND b = %d", 5);

// quoted_placeholder_after_like_is_still_reported
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a LIKE '%s'", $a);

// dollar_precision_padding_is_not_an_argnum
$wpdb->prepare("SELECT '%5.'\$3s'", 'abc');

// like_without_wildcards
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_content LIKE 'a string'");

// like_wildcards_in_the_query
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_content LIKE 'a_string' AND post_status = %s", $status);
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid LIKE '%%%s%%' LIMIT 1;", $url);
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('UPDATE ' . $wpdb->posts . ' SET a = 1 WHERE post_name LIKE "feed-%" AND post_type = %s', $type);

// unescaped_literal_percent
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('WHERE % i IS NULL', $field);

// space_padded_identifier_parses_as_unsupported_argnum
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('WHERE %1$ 10.3i IS NULL', $field);

// missing_replacements
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare(query: 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = %s');

// static_calls_on_the_global_wpdb_class
// @mago-expect lint:wordpress/prepared-sql-placeholders
\wpdb::prepare("SELECT * FROM my_table WHERE id = %d");
// @mago-expect lint:wordpress/prepared-sql-placeholders
WPDB::prepare("SELECT * FROM my_table WHERE id = %d");
MyNamespace\wpdb::prepare("SELECT * FROM my_table WHERE id = %d");

// first_class_callable_is_ignored
$callback = $wpdb->prepare(...);

// dynamic_in_placeholders_from_implode_array_fill
$wpdb->prepare(sprintf("{$wpdb->posts}.post_type IN (%s)", implode(',', array_fill(0, count($types), '%s'))), $types);
$wpdb->prepare(
    "{$wpdb->posts}.post_type IN (" . implode(', ', \array_fill(0, count($types), '%s')) . ") AND ID = %d",
    array_merge($types, [$id]),
);

// dynamic_in_placeholders_counted_against_a_variable_list
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("{$wpdb->posts}.post_type IN (" . implode(',', $esses) . ')', $types);
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare(sprintf("{$wpdb->posts}.post_type IN (%s)", implode(',', array_fill(0, count($types), '%s'))), $types, $more);

// quoted_dynamic_placeholder_generation
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("{$wpdb->posts}.post_type IN ('" . implode(',', array_fill(0, count($types), '%s')) . "')", $types);

// identifier_within_in
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare(sprintf('xxx IN (%s)', implode(',', array_fill(0, count($fields), '%i'))), $fields);
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('xxx IN ( ' . implode(',', array_fill(0, count($fields), "%i")) . ' )', $fields);
