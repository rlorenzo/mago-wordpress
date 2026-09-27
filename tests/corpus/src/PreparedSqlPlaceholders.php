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

// quoted_numbered_placeholder
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('SELECT * FROM my_table WHERE name = "%1$s"', $name);

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

// multiple_unsupported_placeholders_reported_once_without_count_check
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %x AND b = %X AND c = %x", $a);

// unsupported_placeholder_in_interpolated_string
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE flags = %c", $flags);

// too_few_arguments
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s", $a, $b);

// too_many_arguments
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE ID = %d", $id, $extra);

// numbered_placeholders_counted_by_highest_argnum
$wpdb->prepare('SELECT * FROM my_table WHERE a = %1$s AND b = %2$d AND c = %1$s', $a, $b);

// numbered_placeholders_with_too_few_arguments
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare('SELECT * FROM my_table WHERE a = %1$s AND b = %3$d', $a, $b);

// percent_escape_is_not_a_placeholder
$wpdb->prepare("SELECT * FROM my_table WHERE discount = '100%%' AND name = %s", $name);

// like_wildcard_is_not_a_placeholder
$wpdb->prepare("SELECT * FROM my_table WHERE name LIKE %s AND slug LIKE 'admin%' AND path LIKE '%'", $like);

// array_argument_skips_count_check
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s", [$a, $b, $c]);

// single_variable_argument_skips_count_check
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", $values);

// spread_argument_skips_count_check
$wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d AND c = %s", ...$args);

// dynamic_parts_skip_count_check
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

    // single_dynamic_argument_skips_count_check
    public function run(): void
    {
        global $wpdb;
        $wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", $this->params);
        $wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", get_params());
        $wpdb->prepare("SELECT * FROM my_table WHERE a = %s AND b = %d", [...$this->params]);
    }
}

// quoted_and_mismatch_reported_together
// @mago-expect lint:wordpress/prepared-sql-placeholders
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb->prepare("SELECT * FROM my_table WHERE name = '%s' AND ID = %d", $name, $id, $extra);

// identifier_placeholder_is_supported
$wpdb->prepare("SELECT * FROM %i WHERE ID = %d", $table, $id);

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
$wpdb->prepare("SELECT * FROM my_table WHERE flags = %05x", $flags);

// null_safe_prepare_is_checked
// @mago-expect lint:wordpress/prepared-sql-placeholders
$wpdb?->prepare("SELECT * FROM my_table WHERE name = '%s'", $name);
