<?php

declare(strict_types=1);

// closure_with_low_interval_is_flagged
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_minute'] = [
        // @mago-expect lint:wordpress/cron-interval
        'interval' => 60,
        'display' => 'Every Minute',
    ];

    return $schedules;
});

// arrow_function_with_low_interval_is_flagged
add_filter('cron_schedules', fn($schedules) => array_merge($schedules, [
    'every_five_minutes' => [
        // @mago-expect lint:wordpress/cron-interval
        'interval' => 5 * 60,
        'display' => 'Every 5 Minutes',
    ],
]));

// minute_in_seconds_constant_is_recognized
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_minute'] = [
        // @mago-expect lint:wordpress/cron-interval
        'interval' => MINUTE_IN_SECONDS,
        'display' => 'Every Minute',
    ];

    return $schedules;
});

// arithmetic_with_constant_is_evaluated
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_two_minutes'] = [
        // @mago-expect lint:wordpress/cron-interval
        'interval' => 2 * MINUTE_IN_SECONDS,
        'display' => 'Every 2 Minutes',
    ];

    return $schedules;
});

// legacy_array_syntax_is_checked
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_30_seconds'] = array(
        // @mago-expect lint:wordpress/cron-interval
        'interval' => 30,
        'display' => 'Every 30 Seconds',
    );

    return $schedules;
});

// addition_expression_is_evaluated
add_filter('cron_schedules', function ($schedules) {
    $schedules['odd_schedule'] = [
        // @mago-expect lint:wordpress/cron-interval
        'interval' => 60 + 60,
        'display' => 'Every 2 Minutes',
    ];

    return $schedules;
});

// interval_at_minimum_is_allowed
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_15_minutes'] = [
        'interval' => 900,
        'display' => 'Every 15 Minutes',
    ];

    return $schedules;
});

// hour_in_seconds_constant_is_allowed
add_filter('cron_schedules', function ($schedules) {
    $schedules['hourly_custom'] = [
        'interval' => HOUR_IN_SECONDS,
        'display' => 'Every Hour',
    ];

    return $schedules;
});

// constant_arithmetic_above_minimum_is_allowed
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_30_minutes'] = [
        'interval' => 30 * MINUTE_IN_SECONDS,
        'display' => 'Every 30 Minutes',
    ];

    return $schedules;
});

// variable_interval_is_skipped
add_filter('cron_schedules', function ($schedules) use ($interval) {
    $schedules['custom'] = [
        'interval' => $interval,
        'display' => 'Custom',
    ];

    return $schedules;
});

// function_call_interval_is_skipped
add_filter('cron_schedules', function ($schedules) {
    $schedules['custom'] = [
        'interval' => (int) get_option('my_plugin_interval'),
        'display' => 'Custom',
    ];

    return $schedules;
});

// unknown_constant_is_skipped
add_filter('cron_schedules', function ($schedules) {
    $schedules['custom'] = [
        'interval' => MY_PLUGIN_CRON_INTERVAL,
        'display' => 'Custom',
    ];

    return $schedules;
});

// string_callback_is_not_resolved
add_filter('cron_schedules', 'my_plugin_cron_schedules');

// other_filters_are_ignored
add_filter('the_content', function ($content) {
    return ['interval' => 1, 'content' => $content];
});

// non_literal_hook_name_is_skipped
add_filter($hook, function ($schedules) {
    $schedules['custom'] = ['interval' => 1];

    return $schedules;
});

// configured_minimum_is_respected: skipped. The Rust rule's `min-interval`
// option has no matching `Settings` field, so the minimum cannot be lowered
// for a corpus fixture (the corpus shares one composer.json for every rule).
