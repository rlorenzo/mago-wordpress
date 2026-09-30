<?php

declare(strict_types=1);

// closure_with_low_interval_is_flagged
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display' => 'Every Minute',
    ];

    return $schedules;
});

// arrow_function_with_low_interval_is_flagged
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', fn($schedules) => array_merge($schedules, [
    'every_five_minutes' => [
        'interval' => 5 * 60,
        'display' => 'Every 5 Minutes',
    ],
]));

// minute_in_seconds_constant_is_recognized
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_minute'] = [
        'interval' => MINUTE_IN_SECONDS,
        'display' => 'Every Minute',
    ];

    return $schedules;
});

// arithmetic_with_constant_is_evaluated
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_two_minutes'] = [
        'interval' => 2 * MINUTE_IN_SECONDS,
        'display' => 'Every 2 Minutes',
    ];

    return $schedules;
});

// legacy_array_syntax_is_checked
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_30_seconds'] = array(
        'interval' => 30,
        'display' => 'Every 30 Seconds',
    );

    return $schedules;
});

// addition_expression_is_evaluated
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['odd_schedule'] = [
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

// variable_interval_is_undetermined
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) use ($interval) {
    $schedules['custom'] = [
        'interval' => $interval,
        'display' => 'Custom',
    ];

    return $schedules;
});

// function_call_interval_is_undetermined
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['custom'] = [
        'interval' => (int) get_option('my_plugin_interval'),
        'display' => 'Custom',
    ];

    return $schedules;
});

// unknown_constant_is_undetermined
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['custom'] = [
        'interval' => MY_PLUGIN_CRON_INTERVAL,
        'display' => 'Custom',
    ];

    return $schedules;
});

// named_arguments_are_supported
// @mago-expect lint:wordpress/cron-interval
add_filter(hook_name: 'cron_schedules', callback: function ($schedules) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display' => 'Every Minute',
    ];

    return $schedules;
});

// numeric_separator_is_evaluated
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['every_ten_minutes'] = [
        'interval' => 6_00,
        'display' => 'Every Ten Minutes',
    ];

    return $schedules;
});

// octal_literal_is_evaluated
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['custom'] = [
        'interval' => 0600,
        'display' => 'Custom',
    ];

    return $schedules;
});

// integer_overflow_is_above_minimum
add_filter('cron_schedules', function ($schedules) {
    $schedules['custom'] = [
        'interval' => 9223372036854775807 * 2,
        'display' => 'Custom',
    ];

    return $schedules;
});

// undeclared_string_callback_is_undetermined
// @mago-expect lint:wordpress/cron-interval
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

// named_function_callback_is_resolved
function my_plugin_every_minute(array $schedules): array
{
    $schedules['every_minute'] = ['interval' => 60, 'display' => 'Every Minute'];

    return $schedules;
}

// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', 'my_plugin_every_minute');

// first_class_callable_is_resolved
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', my_plugin_every_minute(...));

final class My_Plugin_Cron
{
    public function register(): void
    {
        // array_callable_method_is_resolved
        // @mago-expect lint:wordpress/cron-interval
        add_filter('cron_schedules', [$this, 'every_five_minutes']);

        // array_callable_method_above_minimum_is_allowed
        add_filter('cron_schedules', array($this, 'hourly'));

        // method_first_class_callable_is_resolved
        // @mago-expect lint:wordpress/cron-interval
        add_filter('cron_schedules', $this->every_five_minutes(...));
    }

    public function every_five_minutes(array $schedules): array
    {
        $schedules['every_five_minutes'] = ['interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Every 5 Minutes'];

        return $schedules;
    }

    public static function hourly(array $schedules): array
    {
        $schedules['hourly_custom'] = ['interval' => HOUR_IN_SECONDS, 'display' => 'Hourly'];

        return $schedules;
    }
}

// static_array_callable_is_resolved
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', ['My_Plugin_Cron', 'Every_Five_Minutes']);

// array_callable_with_undeclared_method_is_undetermined
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', [$other, 'some_method']);

// array_callable_with_variable_method_is_undetermined
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', [$other, $method]);

// variable_callback_is_undetermined
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', $callback);

// interval_without_value_is_undetermined
// @mago-expect lint:wordpress/cron-interval
add_filter('cron_schedules', function ($schedules) {
    $schedules['custom'] = ['interval'];

    return $schedules;
});

// callback_without_interval_is_ignored
add_filter('cron_schedules', function ($schedules) {
    unset($schedules['hourly']);

    return $schedules;
});

// configured_minimum_is_respected: skipped. The Rust rule's `min-interval`
// option has no matching `Settings` field, so the minimum cannot be lowered
// for a corpus fixture (the corpus shares one composer.json for every rule).
