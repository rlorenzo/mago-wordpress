<?php

declare(strict_types=1);

// query_posts_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
query_posts(['post_type' => 'post']);

// wp_reset_query_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
wp_reset_query();

// functions_wpcs_does_not_discourage_are_not_flagged (get_page_by_title is wp-deprecated-functions)
$page = get_page_by_title('About Us');
$post_id = url_to_postid('https://example.com/about/');
$attachment_id = attachment_url_to_postid($url);
if (wp_is_mobile()) {
    echo 'mobile';
}

// fully_qualified_call_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
\query_posts(['post_type' => 'post']);

// uppercase_call_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
WP_Reset_Query();

// wp_query_is_allowed
$query = new WP_Query(['post_type' => 'post']);
wp_reset_postdata();

// method_call_is_allowed
$helper->query_posts(['post_type' => 'post']);

// unrelated_function_is_allowed
get_posts(['post_type' => 'post']);

// obfuscation_group_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
base64_decode($data);

// runtime_configuration_group_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
putenv('TZ=UTC');

// serialize_group_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
serialize($data);

// system_calls_group_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
exec($command);

// urlencode_group_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
urlencode($value);

// development_group_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
var_dump($value);

// use_function_import_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
use function wp_reset_query;

// aliased_use_function_import_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
use function wp_reset_query as myFunction;

// alias_named_like_a_discouraged_function_is_not_flagged
use function someOtherFunction as wp_reset_query;

// first_class_callable_is_flagged
// @mago-expect lint:wordpress/discouraged-wp-functions
call_user_func(query_posts(...), $param);
