<?php

declare(strict_types=1);

// every_other_runtime_change_is_the_risky_warning

// @mago-expect lint:wordpress/ini-set-warning
ini_set('report_memleaks', true);
// @mago-expect lint:wordpress/ini-set-warning
ini_set('report_memleaks',true);
// @mago-expect lint:wordpress/ini-set-warning
ini_set('short_open_tag', 1230);
// @mago-expect lint:wordpress/ini-set-warning
ini_set($test, 1230);
// @mago-expect lint:wordpress/ini-set-warning
ini_alter('report_memleaks', 1230);
// @mago-expect lint:wordpress/ini-set-warning
ini_set('report_memleaks', ...$args);
