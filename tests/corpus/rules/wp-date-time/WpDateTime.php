<?php

declare(strict_types=1);

namespace {
    // date_call_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    $formatted = date('Y-m-d H:i:s');

    // date_call_with_leading_backslash_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    $formatted = \DATE('Y-m-d');

    // date_default_timezone_set_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    date_default_timezone_set('UTC');

    // current_time_timestamp_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    $timestamp = current_time('timestamp');

    // current_time_u_format_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    $timestamp = current_time('U');

    // current_time_timestamp_mode_is_case_sensitive_so_uppercase_is_a_date_format
    $timestamp = current_time('TIMESTAMP');

    // current_time_timestamp_with_explicit_false_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    $timestamp = current_time('timestamp', false);

    // current_time_timestamp_with_zero_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    $timestamp = current_time('timestamp', 0);

    // current_time_named_type_argument_is_flagged
    // @mago-expect lint:wordpress/wp-date-time
    $timestamp = current_time(type: 'timestamp');

    // gmdate_and_wp_date_are_not_flagged
    $utc = gmdate('Y-m-d H:i:s');
    $local = wp_date('Y-m-d H:i:s');
    $i18n = date_i18n('Y-m-d');

    // date_method_calls_are_not_flagged
    $formatted2 = $datetime->date('Y-m-d');
    $other = Carbon::date('Y-m-d');

    // current_time_mysql_is_not_flagged
    $mysql = current_time('mysql');
    $lower_u = current_time('u');

    // current_time_with_dynamic_format_is_not_flagged
    $timestamp2 = current_time($format);

    // current_time_timestamp_with_gmt_true_is_not_flagged
    $timestamp3 = current_time('timestamp', true);
    $timestamp4 = current_time('timestamp', $gmt);
    $timestamp5 = current_time('timestamp', gmt: true);
}

namespace App {
    use function My\Plugin\date;

    // namespaced_date_function_is_not_flagged
    $formatted = date('Y-m-d');
}
