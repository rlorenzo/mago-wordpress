<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
add_filter('cron_schedules', static function (array $schedules): array { $schedules['minute'] = ['interval' => 60, 'display' => 'Every Minute']; return $schedules; });

// @mago-expect lint:wordpress/cron-interval
// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
add_filter('cron_schedules', static function (array $schedules): array { $schedules['minute'] = ['interval' => 60, 'display' => 'Every Minute']; return $schedules; });
