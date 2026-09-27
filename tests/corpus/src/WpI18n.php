<?php

declare(strict_types=1);

namespace {
    // literal_text_and_domain
    $greeting = __('Hello, World!', 'my-plugin');
    esc_html_e('Welcome', 'my-plugin');

    // context_functions_with_literals
    $label = _x('Post', 'noun', 'my-plugin');
    _ex('Book', 'verb', 'my-plugin');
    $attr = esc_attr_x('Draft', 'post status', 'my-plugin');

    // plural_with_matching_placeholders
    $text = _n('%d item', '%d items', $count, 'my-plugin');
    $pair = _n_noop('%s post', '%s posts', 'my-plugin');
    $ctx = _nx('%1$s file', '%1$s files', $count, 'uploads', 'my-plugin');

    // singular_without_placeholder_is_allowed
    $text = _n('One item', '%d items', $count, 'my-plugin');

    // fully_qualified_call_with_literals
    $greeting = \__('Hello', 'my-plugin');

    // unrelated_function_is_ignored
    $value = my_helper($variable);
    $other = sprintf('%s items', $count);

    // variable_text_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $greeting = __($message, 'my-plugin');

    // concatenated_text_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $greeting = __('Hello, ' . $name, 'my-plugin');

    // interpolated_text_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    _e("Hello, $name!", 'my-plugin');

    // non_literal_context_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $label = _x('Post', $context, 'my-plugin');

    // missing_text_domain_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $greeting = __('Hello, World!');

    // missing_plural_domain_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $text = _n('%d item', '%d items', $count);

    // non_literal_domain_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $greeting = __('Hello, World!', $domain);

    // mismatched_placeholders_are_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $text = _n('%s item', '%d items', $count, 'my-plugin');

    // placeholder_multiplicity_mismatch_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $text = _n('%s of %s', '%s items', $count, 'my-plugin');

    // plural_without_placeholder_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $text = _n('%d item', 'many items', $count, 'my-plugin');

    // extra_plural_placeholder_of_other_type_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $text = _n('%s item', '%d %s items', $count, 'my-plugin');

    // reordered_unnumbered_placeholders_are_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $text = _n('%s: %d item', '%d items: %s', $count, 'my-plugin');

    // reordered_numbered_placeholders_are_allowed
    $text = _n('%1$s: %2$d item', '%2$d items: %1$s', $count, 'my-plugin');
    $core = _n('One thought on %2$s', '%1$s thoughts on %2$s', $count, 'my-plugin');

    // padded_placeholder_mismatch_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $text = _n('%02d item', '%s items', $count, 'my-plugin');

    // flags_width_and_precision_are_parsed
    $a = _n('%02d item', '%02d items', $count, 'my-plugin');
    $b = _n('%.2f credit', '%.2f credits', $count, 'my-plugin');
    $c = _n('20% off %1$-10s', '20% off %1$-10s', $count, 'my-plugin');

    // matching_placeholder_counts_are_allowed
    $text = _n('%s of %s item', '%s of %s items', $count, 'my-plugin');

    // escaped_percent_is_not_a_placeholder
    $text = _n('100%% of %d item', '100%% of %d items', $count, 'my-plugin');

    // allowed_text_domain (text-domains = ["my-plugin"] in tests/corpus/composer.json)
    $greeting = __('Hello, World!', 'my-plugin');

    // unexpected_text_domain_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $greeting = __('Hello, World!', 'other-plugin');

    // wrapper_function_definition_is_exempt
    function __($text, $domain = 'default')
    {
        return translate($text, $domain);
    }

    // wrapper_method_definition_is_exempt
    class Translator
    {
        public function _e($text, $domain = 'default')
        {
            _e($text, $domain);
        }
    }

    // translate_method_call_is_ignored
    $result = $translator->translate($key);
    $other = Translator::translate($key);

    // spread_arguments_are_ignored
    $greeting = __(...$args);

    // named_arguments_are_ignored
    $greeting = __(text: $message);

    // namespace_qualified_call_is_ignored
    $greeting = Foo\__($message);

    // multiple_problems_are_all_reported (count = 2)
    // @mago-expect lint:wordpress/wp-i18n
    // @mago-expect lint:wordpress/wp-i18n
    $greeting = __($message);
}

// function_imported_from_namespace_is_ignored
namespace App {
    use function Other\translate;

    $result = translate($key);
}
