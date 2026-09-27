<?php

declare(strict_types=1);

// not_the_sniff_target
my\ns\get_post_meta($a, $b);
$this->get_post_meta($a, $b);
MyClass::get_post_meta($a, $b);
echo GET_POST_META;
add_action('my_action', get_post_meta(...));

// omitted_key_or_explicit_single_is_ok
$ok = get_post_meta($post_id);
$ok = get_post_meta($post_id, $meta_key, false);
$ok = get_post_meta($post_id, single: true);
$ok = get_post_meta($post_id, single: true, key: $meta_key);
$ok = get_metadata('post', $post_id);
$ok = get_metadata('post', $post_id, $meta_key, true);
$ok = get_metadata('post', $post_id, single: true);
$ok = get_metadata('post', $post_id, single: true, meta_key: $meta_key);

// spread_arguments_are_ignored
$ok = get_post_meta($post_id, $meta_key, ...$rest);
$ok = get_post_meta(...$args);

// incorrect_calls_are_ignored
$incorrect_but_ok = get_post_meta();
$incorrect_but_ok = get_post_meta(single: true);
$incorrect_but_ok = get_metadata('post');

// fully_qualified_call_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = \get_post_meta($post_id, $meta_key);

// case_insensitive_name_is_flagged
// @mago-expect lint:wordpress/get-meta-single
implode(', ', \GET_POST_META($post_id, $meta_key));

// named_key_argument_is_flagged
if (
    // @mago-expect lint:wordpress/get-meta-single
    get_post_meta($post_id, key: $meta_key)
) {
}

// misspelled_single_argument_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = get_post_meta($post_id, key: $meta_key, sinngle: true);

// get_comment_meta_is_flagged
// @mago-expect lint:wordpress/get-meta-single
echo get_comment_meta($comment_id, $meta_key);

// get_site_meta_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = get_site_meta($site_id, $meta_key);

// get_term_meta_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = get_term_meta($term_id, $meta_key);

// get_user_meta_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = get_user_meta($user_id, $meta_key);

// uppercase_get_metadata_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = GET_METADATA('post', $post_id, $meta_key);

// named_meta_key_across_lines_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = get_metadata('post', $post_id, meta_key: $meta_key);

// get_metadata_raw_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = get_metadata_raw('post', $post_id, $meta_key);

// get_metadata_default_is_flagged
// @mago-expect lint:wordpress/get-meta-single
$warning = get_metadata_default('post', $post_id, $meta_key);

// namespaced_calls_are_not_flagged
\MyNamespace\get_user_meta($user_id, $meta_key);
namespace\Sub\get_comment_meta($comment_id, $meta_key);

// relative_namespace_operator_resolves_to_the_global_function_and_is_flagged
//
// Unlike the sniff, which cannot resolve `namespace\` at the token level,
// this rule sees that an unnamespaced file's `namespace\get_metadata` is
// the global get_metadata().
// @mago-expect lint:wordpress/get-meta-single
namespace\get_metadata('post', $post_id, $meta_key);
