<?php

declare(strict_types=1);

// wp_enqueue_functions_are_fine
wp_enqueue_script('my-script', 'https://example.com/js/app.js', [], '1.0.0', true);
wp_enqueue_style('my-style', 'https://example.com/css/app.css', [], '1.0.0');

// inline_script_without_src_is_fine
echo '<script>console.log("hello");</script>';
echo '<script type="text/template">{{ name }}</script>';

// non_stylesheet_link_is_fine
echo '<link rel="canonical" href="https://example.com/page/" />';
echo '<link rel="preconnect" href="https://fonts.example.com" />';

// data_src_attribute_is_fine
echo '<script data-src="lazy.js"></script>';

// attribute_lookalike_inside_quoted_value_is_fine
echo '<script data-config="src=foo.js"></script>';
echo '<link href="app.css" data-meta="rel=stylesheet">';

// longer_tag_names_are_fine
echo '<scripting src="not-html.js">';
echo '<linkage rel="stylesheet">';

// plain_text_mentioning_src_is_fine
$doc = 'Set the src= attribute on your script tag, or use rel="stylesheet".';

// script_tag_with_src
// @mago-expect lint:wordpress/enqueued-resources
echo '<script src="https://example.com/js/app.js"></script>';

// script_tag_with_attributes_before_src
// @mago-expect lint:wordpress/enqueued-resources
echo '<script type="module" src="https://example.com/js/app.mjs"></script>';

// stylesheet_link_tag
// @mago-expect lint:wordpress/enqueued-resources
echo '<link rel="stylesheet" href="https://example.com/css/app.css" />';

// single_quoted_rel_and_uppercase
// @mago-expect lint:wordpress/enqueued-resources
echo "<LINK REL='STYLESHEET' HREF='style.css'>";
// @mago-expect lint:wordpress/enqueued-resources
echo "<SCRIPT SRC='app.js'></SCRIPT>";

// multi_token_stylesheet_rel
// @mago-expect lint:wordpress/enqueued-resources
echo '<link rel="alternate stylesheet" href="dark.css">';
// @mago-expect lint:wordpress/enqueued-resources
echo '<link rel=" stylesheet " href="app.css">';
// @mago-expect lint:wordpress/enqueued-resources
echo '<link rel=stylesheet/>';

// interpolated_string_with_script_src
// @mago-expect lint:wordpress/enqueued-resources
echo "<script src='{$url}'></script>";

// heredoc_with_stylesheet_link
// @mago-expect lint:wordpress/enqueued-resources
$html = <<<HTML
<link rel="stylesheet" href="{$url}" />
HTML;

// returned_string_with_script_src
function enqueued_resources_footer_scripts(): string
{
    // @mago-expect lint:wordpress/enqueued-resources
    return '<script src="/js/footer.js"></script>';
}

// inline_html_with_script_src
$enqueued_resources_title = 'x';
// @mago-expect lint:wordpress/enqueued-resources
?>
<script src="/js/app.js"></script>
<?php

// inline_html_with_stylesheet_link
$enqueued_resources_title = 'y';
// @mago-expect lint:wordpress/enqueued-resources
?>
<link rel=stylesheet href="/css/app.css">
<?php

// escaped_double_quoted_rel
// @mago-expect lint:wordpress/enqueued-resources
echo "<link rel=\"stylesheet\" href=\"style.css\">";

// escaped_single_quoted_rel
// @mago-expect lint:wordpress/enqueued-resources
echo '<link rel=\'stylesheet\' href=\'style.css\'>';

// quoted_greater_than_before_src
// @mago-expect lint:wordpress/enqueued-resources
echo '<script data-query="a > b" src="app.js"></script>';
