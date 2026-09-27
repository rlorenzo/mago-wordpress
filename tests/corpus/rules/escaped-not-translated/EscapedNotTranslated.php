<?php

declare(strict_types=1);

// single_argument_esc_html_is_ok
esc_html('text');

// single_argument_variable_is_ok
esc_html($var);

// two_argument_esc_html_looks_like_a_missing_translation
// @mago-expect lint:wordpress/escaped-not-translated
esc_html('text', 'domain');

// fully_qualified_and_uppercase_still_matches
// @mago-expect lint:wordpress/escaped-not-translated
\ESC_HTML($foo, $bar);

// esc_attr_case_insensitive_multiline_call
// @mago-expect lint:wordpress/escaped-not-translated
esc_ATTR(
    'text',
    MY_DOMAIN,
);

// fully_qualified_esc_attr
// @mago-expect lint:wordpress/escaped-not-translated
\esc_attr('text', 'domain');

// a_function_of_the_same_name_in_another_namespace_is_not_this_one
MyNamespace\esc_html('text', 'domain');

// a_fully_qualified_function_in_another_namespace_is_not_this_one
\MyNamespace\esc_attr('text', 'domain');
