<?php

declare(strict_types=1);

// the_options_wordpress_sets_another_way_are_errors

// @mago-expect lint:wordpress/ini-set
ini_set('bcmath.scale', 0);
// @mago-expect lint:wordpress/ini-set
\ini_SET( 'bcmath.scale' ,0 );
// @mago-expect lint:wordpress/ini-set
INI_set('display_errors', 0);
// @mago-expect lint:wordpress/ini-set
ini_set('error_reporting', 0);
// @mago-expect lint:wordpress/ini-set
ini_set('filter.default', 'full_special_chars');
// @mago-expect lint:wordpress/ini-set
ini_set('filter.default_flags', 0);
// @mago-expect lint:wordpress/ini-set
ini_set('iconv.input_encoding', '');
// @mago-expect lint:wordpress/ini-set
ini_set('iconv.internal_encoding', '');
// @mago-expect lint:wordpress/ini-set
ini_set('iconv.output_encoding', '');
// @mago-expect lint:wordpress/ini-set
ini_set('ignore_user_abort', true);
// @mago-expect lint:wordpress/ini-set
ini_set('log_errors', true);
// @mago-expect lint:wordpress/ini-set
ini_set('max_execution_time', 60);
// @mago-expect lint:wordpress/ini-set
ini_set('memory_limit', -1);
// @mago-expect lint:wordpress/ini-set
ini_set('short_open_tag', false);
// @mago-expect lint:wordpress/ini-set
ini_set('short_open_tag', FALSE);
// @mago-expect lint:wordpress/ini-set
ini_set('short_open_tag', FaLsE);
// @mago-expect lint:wordpress/ini-set
ini_set('short_open_tag', 0);
// @mago-expect lint:wordpress/ini-set
ini_set('short_open_tag', 'Off');
// @mago-expect lint:wordpress/ini-set
ini_alter('display_errors', false);
// @mago-expect lint:wordpress/ini-set
ini_set(value: 0, option: 'short_open_tag');
// @mago-expect lint:wordpress/ini-set
\ini_set( 'bcmath.scale', 0 );

// a_spread_value_is_still_a_value
// @mago-expect lint:wordpress/ini-set
ini_set('memory_limit', ...[$limit]);
// @mago-expect lint:wordpress/ini-set
ini_set('display_errors', ...$args);

// a_comment_between_the_arguments_is_ignored
// @mago-expect lint:wordpress/ini-set
ini_set(
    // This affects all function calls to the BCMath extension.
    'bcmath.scale',
    // Set the number of decimals.
    0
);

// safe_options_and_values_are_not_reported
ini_set('auto_detect_line_endings', true);
ini_set( 'auto_detect_line_endings',true) ;
ini_set('highlight.bg', '#FFFFFF');
ini_set('highlight.comment', '#FFFFFF');
ini_set('highlight.default', '#FFFFFF');
ini_set('highlight.html', '#FFFFFF');
ini_set('highlight.keyword', '#FFFFFF');
ini_set('highlight.string', '#FFFFFF');
ini_set('short_open_tag', true);
ini_set('short_open_tag', 1);
ini_set('short_open_tag', 'On');
ini_set('short_open_tag', 'on');
ini_set(value: 1, option: 'short_open_tag');
ini_set('short_open_tag', /* allowed*/ 'on');
ini_alter('auto_detect_line_endings', true);

// a_missing_argument_is_not_this_rules_concern
ini_set('short_open_tag');

// namespaced_calls_are_other_functions
MyNamespace\ini_alter('bcmath.scale', 0);
\MyNamespace\ini_set('bcmath.scale', 0);
namespace\Sub\ini_set('bcmath.scale', 0);
