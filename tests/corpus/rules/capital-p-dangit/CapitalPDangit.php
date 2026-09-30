<?php

declare(strict_types=1);

// misspelling_in_single_quoted_string
// @mago-expect lint:wordpress/capital-p-dangit
$message = 'Welcome to Wordpress!';

// misspelling_in_double_quoted_string
// @mago-expect lint:wordpress/capital-p-dangit
$message = 'I love wordPress';

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

// misspelling_in_link_tag_text_is_not_flagged
/**
 * @link https://example.com/docs
 * Integrates with Wordpress core.
 */
$x = 2;

// misspelling_on_later_docblock_line_is_flagged
// @mago-expect lint:wordpress/capital-p-dangit
/**
 * Summary.
 *
 * @param string $wordpress The variable name is fine.
 * @package Wordpress
 */
$x = 3;

// sentence_ending_misspelling_is_flagged
// @mago-expect lint:wordpress/capital-p-dangit
$message = 'Thank you for choosing Wordpress.';

// correct_spelling_is_not_flagged
// WordPress is spelled correctly.
$message = 'Welcome to WordPress!';

// all_lowercase_is_flagged
$slug = 'my-wordpress-site';
// @mago-expect lint:wordpress/capital-p-dangit
$note = 'installing wordpress here';

// dashed_and_hyphenated_variants_are_flagged
// @mago-expect lint:wordpress/capital-p-dangit
$note = 'word-press and word - presss';

// url_with_scheme_is_not_flagged
$url = 'See https://Wordpress.org for details';

// occurrence_after_url_is_flagged
// @mago-expect lint:wordpress/capital-p-dangit
$urls = 'See https://wordpress.org then Wordpress';

// domain_like_token_is_not_flagged
$domain = 'Wordpress.org';

// path_adjacent_occurrence_is_not_flagged
$path = 'visit /Wordpress/ now';
$query = 'https://x.org/?platform=Wordpress';
$email = 'wapuu@wordpress.example or wordpress@example.com';
$file = 'wordpress-importer/wordpress.php';

// class_like_token_is_not_flagged
$class = 'Wordpress_Plugin';

// embedded_in_identifier_is_not_flagged
$name = 'MyWordpressClass';
$other = 'WordPressy things';

// unrelated_text_is_not_flagged
// A perfectly normal comment about presses.
$message = 'the printing press changed the world';

// arrays_and_array_keys_are_not_flagged
$list = array('label' => 'Wordpress', ['Wordpress']);
$counts['wordpress'] = 1;

// constant_declarations_are_not_flagged
define('WORDPRESS_SOMETHING', 'wordpress');
\DEFINE('WORDPRESS_SOMETHING', 'wordpress');
const WORDPRESS_THING = 'wordpress';

// namespaced_define_is_flagged
// @mago-expect lint:wordpress/capital-p-dangit
MyNamespace\define('WORDPRESS_SOMETHING', 'wordpress');

// misspelling_in_heredoc_line
// @mago-expect lint:wordpress/capital-p-dangit
$text = <<<EOD
    This is an {$explanation} about wordpress.
    EOD;

// misspelling_in_class_like_names
// @mago-expect lint:wordpress/capital-p-dangit
class Wordpress_Something {}

// @mago-expect lint:wordpress/capital-p-dangit
enum My_Wordpress_Enum {}

class Something_WordPress {}

// misspelling_in_inline_html
// @mago-expect lint:wordpress/capital-p-dangit
?>
<p class="wordpress-class">Text with Wordpress, <a href="http://wordpress.org/">link</a></p>
<p class="fa-wordpress" value="wordpress">Spelled WordPress.</p>
wordpress.pot
<?php
