<?php

declare(strict_types=1);

// misspelling_in_single_quoted_string
// @mago-expect lint:wordpress/capital-p-dangit
$message = 'Welcome to Wordpress!';

// misspelling_in_double_quoted_string
// @mago-expect lint:wordpress/capital-p-dangit
$message = "I love wordPress";

// spaced_misspelling_is_flagged
// @mago-expect lint:wordpress/capital-p-dangit
$message = 'Powered by Word Press';

// lowercase_spaced_misspelling_is_flagged
// @mago-expect lint:wordpress/capital-p-dangit
$message = 'Powered by word press';

// misspelling_in_comment
// @mago-expect lint:wordpress/capital-p-dangit
// This plugin integrates with Wordpress core.
$x = 1;

// misspelling_in_interpolated_string_part
// @mago-expect lint:wordpress/capital-p-dangit
$message = "Hello $name, welcome to Wordpress";

// misspelling_at_string_edges
// @mago-expect lint:wordpress/capital-p-dangit
$message = 'Wordpress';

// correct_spelling_is_not_flagged
// WordPress is spelled correctly.
$message = 'Welcome to WordPress!';

// all_lowercase_is_not_flagged
$slug = 'my-wordpress-site';
$note = 'installing wordpress here';

// url_with_scheme_is_not_flagged
$url = 'See https://Wordpress.org for details';

// path_adjacent_occurrence_is_not_flagged
$path = 'visit /Wordpress/ now';
$query = 'platform=Wordpress';

// class_like_token_is_not_flagged
$class = 'Wordpress_Plugin';

// embedded_in_identifier_is_not_flagged
$name = 'MyWordpressClass';
$other = 'WordPressy things';

// unrelated_text_is_not_flagged
// A perfectly normal comment about presses.
$message = 'the printing press changed the world';
