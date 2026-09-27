<?php

declare(strict_types=1);

// call_is_flagged
add_action('widgets_init', function () {
    // @mago-expect lint:wordpress/restricted-php-functions
    return create_function('', 'return register_widget("time_more_on_time_widget");');
});

// uppercase_call_is_flagged
// @mago-expect lint:wordpress/restricted-php-functions
CREATE_function('', '');

// fully_qualified_call_is_flagged
// @mago-expect lint:wordpress/restricted-php-functions
\create_function('', 'return;');

// fully_qualified_uppercase_call_is_flagged
// @mago-expect lint:wordpress/restricted-php-functions
\Create_Function('', 'return;');

// namespaced_call_is_allowed
MyNamespace\create_function('', 'return;');

// fully_qualified_namespaced_call_is_allowed
\MyNamespace\create_function('', 'return;');

// relative_namespaced_call_is_allowed
namespace\Sub\create_function('', 'return;');
