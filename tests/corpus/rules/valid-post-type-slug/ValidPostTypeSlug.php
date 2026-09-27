<?php

declare(strict_types=1);

// hyphenated_slug_is_ok
register_post_type('my-own-post-type', []);

// underscored_slug_is_ok
register_post_type('my_own_post_type', []);

// zero_arguments_presumed_live_coding
register_post_type();

// plain_heredoc_with_no_interpolation_is_ok
register_post_type(<<<EOD
my_own_post_type
EOD
);

// nowdoc_is_ok
register_post_type(<<<'EOD'
my_own_post_type
EOD
);

// too_long_slug
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type('my-own-post-type-too-long', []);

// reserved_name
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type('author', []);

// invalid_characters_uppercase
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type('My-Own-Post-Type', []);

// invalid_characters_slash_case_insensitive_function_name
// @mago-expect lint:wordpress/valid-post-type-slug
register_POST_TYPE('my/own/post/type', []);

// reserved_prefix
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type('wp_post_type', []);

// reserved_keyword_not_reported_as_reserved_prefix
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type('wp_block', []);

// empty_slug
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type('', []);

// fully_qualified_call_still_matches
// @mago-expect lint:wordpress/valid-post-type-slug
\register_post_type('my-own-post-type-too-long', []);

// interpolated_double_quoted_string_warns_but_stripped_text_is_valid
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type("my_post_type_{$suffix}");

// unknown_escape_keeps_its_backslash
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type("my_\qtype", []);

// non_string_literal_function_call
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type(sprintf('my_post_type_%d', $suffix));

// non_string_literal_constant
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type(POST_TYPE_CONST);

// non_string_literal_variable
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type($post_type_name);

// non_string_literal_null
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type(null, []);

// non_string_literal_int
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type(1000, []);

// safeguard_php_8_named_arguments_ok
register_post_type(args: [], post_type: 'my_own_post_type');

// named_arguments_missing_post_type
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type(args: []);

// named_arguments_too_long
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type(args: [], post_type: 'my-own-post-type-too-long');

// namespaced_calls_are_not_matched
MyNamespace\register_post_type('my-own-post-type-too-long', []);
namespace\Sub\register_post_type('my-own-post-type-too-long', []);

// purely_interpolated_slug_warns_as_dynamic_not_empty
// @mago-expect lint:wordpress/valid-post-type-slug
register_post_type("{$slug}");
