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
    /* translators: %s: Count. */
    $text = _n('%d item', '%d items', $count, 'my-plugin');
    /* translators: %s: Count. */
    $pair = _n_noop('%s post', '%s posts', 'my-plugin');
    /* translators: %s: Count. */
    $ctx = _nx('%1$s file', '%1$s files', $count, 'uploads', 'my-plugin');

    // singular_without_placeholder_is_allowed
    /* translators: %s: Count. */
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
    /* translators: %s: Count. */
    $text = _n('%d item', '%d items', $count);

    // non_literal_domain_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    $greeting = __('Hello, World!', $domain);

    // mismatched_placeholders_are_flagged
    // @mago-expect lint:wordpress/wp-i18n
    /* translators: %s: Count. */
    $text = _n('%s item', '%d items', $count, 'my-plugin');

    // placeholder_multiplicity_mismatch_is_flagged
    // @mago-expect lint:wordpress/wp-i18n(2)
    /* translators: %s: Count. */
    $text = _n('%s of %s', '%s items', $count, 'my-plugin');

    // plural_without_placeholder_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    /* translators: %s: Count. */
    $text = _n('%d item', 'many items', $count, 'my-plugin');

    // extra_plural_placeholder_of_other_type_is_flagged
    // @mago-expect lint:wordpress/wp-i18n(2)
    /* translators: %s: Count. */
    $text = _n('%s item', '%d %s items', $count, 'my-plugin');

    // reordered_unnumbered_placeholders_are_flagged
    // @mago-expect lint:wordpress/wp-i18n(3)
    /* translators: %s: Count. */
    $text = _n('%s: %d item', '%d items: %s', $count, 'my-plugin');

    // reordered_numbered_placeholders_are_allowed
    /* translators: %s: Count. */
    $text = _n('%1$s: %2$d item', '%2$d items: %1$s', $count, 'my-plugin');
    /* translators: %s: Count. */
    $core = _n('One thought on %2$s', '%1$s thoughts on %2$s', $count, 'my-plugin');

    // padded_placeholder_mismatch_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    /* translators: %s: Count. */
    $text = _n('%02d item', '%s items', $count, 'my-plugin');

    // flags_width_and_precision_are_parsed
    /* translators: %s: Count. */
    $a = _n('%02d item', '%02d items', $count, 'my-plugin');
    /* translators: %s: Count. */
    $b = _n('%.2f credit', '%.2f credits', $count, 'my-plugin');
    /* translators: %s: Count. */
    $c = _n('20% off %1$-10s', '20% off %1$-10s', $count, 'my-plugin');

    // matching_placeholder_counts_are_allowed (each string still needs numbered placeholders)
    // @mago-expect lint:wordpress/wp-i18n(2)
    /* translators: %s: Count. */
    $text = _n('%s of %s item', '%s of %s items', $count, 'my-plugin');

    // escaped_percent_is_not_a_placeholder
    /* translators: %s: Count. */
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
    class MyPlugin_Translator
    {
        public function _e($text, $domain = 'default')
        {
            _e($text, $domain);
        }
    }

    // wrapper_name_matches_case_insensitively
    class MyPlugin_LoudTranslator
    {
        public function ESC_HTML__($text, $domain = 'default')
        {
            return esc_html__($text, $domain);
        }
    }

    // uppercase_call_is_checked
    // @mago-expect lint:wordpress/wp-i18n
    _E($message, 'my-plugin');

    // closure_inside_wrapper_is_checked
    // @mago-expect lint:wordpress/prefix-all-globals
    function _x($text, $context, $domain = 'default')
    {
        // @mago-expect lint:wordpress/wp-i18n
        return static fn() => __($text, 'my-plugin');
    }

    // skipped_function_is_ignored
    $label = translate_with_gettext_context($text, $context);

    // translate_method_call_is_ignored
    $result = $translator->translate($key);
    $other = Translator::translate($key);

    // spread_arguments_are_ignored
    $greeting = __(...$args);

    // named_arguments_are_checked
    // @mago-expect lint:wordpress/wp-i18n(2)
    $greeting = __(text: $message, domain: 'wrong');

    // named_arguments_bind_by_parameter_name
    $greeting = __(domain: 'my-plugin', text: 'Hello');
    $label = _x('Post', domain: 'my-plugin', context: 'noun');
    /* translators: %s: Count. */
    $text = _n(single: '%d item', plural: '%d items', number: $count, domain: 'my-plugin');
    /* translators: %s: Count. */
    $text = _nx_noop(singular: '%d item', plural: '%d items', context: 'noun', domain: 'my-plugin');

    // named_domain_mismatch_is_flagged
    // @mago-expect lint:wordpress/wp-i18n
    /* translators: %s: Count. */
    $text = _n_noop('%d item', '%d items', domain: 'wrong');

    // parenthesized_literals_are_allowed
    $greeting = __(('Hello'), ('my-plugin'));

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

// Translators comments, ported from WPCS Tests/WP/I18nUnitTest.2.inc.
namespace {
    __('No placeholders here.', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    __('There are %1$d monkeys in the %2$s', 'my-plugin');

    /* translators: %d: number of cats. */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    // translators: %d: number of cats.
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    /* translators:
       - number of monkeys,
       - location. */
    esc_html__('There are %1$d monkeys in the %2$s', 'my-plugin');

    /*
     * translators: %d: number of cats.
     */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    /*
     * translators: %d: number of cats.
     * This is a multiline comment,
     * But it also has * at the start
     of some lines ;-)
    */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /**
     * translators: %d: number of cats.
     */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /* %d: number of cats. */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /* this is for translators: %d: number of cats. */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    /* Translators: %d: number of cats. */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    /* TRANSLATORS: %d: number of cats. */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    # translators: %d: number of cats.
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    /* translators: %d: number of cats. */


    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    /* Some other comment. */
    /* translators: %d: number of cats. */
    _n_noop('I have %d cat.', 'I have %d cats.', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    // translators: 1: number; 2: string.
    // Some other comment.
    esc_attr_e('Text to translate to %1$d languages. Another %2$s placeholder', 'my-plugin');

    printf(
        /* translators: number of monkeys, location. */
        __('There are %1$d monkeys in the %2$s', 'my-plugin'),
        (int) $number,
        esc_html($string),
    );

    /* translators: number of monkeys, location. */
    printf(
        // @mago-expect lint:wordpress/wp-i18n
        __('There are %1$d monkeys in the %2$s', 'my-plugin'),
        (int) $number,
        esc_html($string),
    );

    /* translators: number of monkeys, location. */
    printf(__('There are %1$d monkeys in the %2$s', 'my-plugin'), intval($number), esc_html($string));

    /* translators: number of monkeys, location. */
    $message = sprintf(__('There are %1$d monkeys in the %2$s', 'my-plugin'), intval($number), esc_html($string));

    /* translators: number of monkeys, location. */
    printf(__(
        'There are %1$d monkeys in the %2$s',
        'my-plugin',
    ), intval($number),
        esc_html($string),
    );

    __('foo 100% bar', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    __('text %s' . 'more text', 'my-plugin');

    /* translators: %d: number of cats. */
    _n_noop(domain: 'my-plugin', singular: 'I have %d cat.', plural: 'I have %d cats.');

    // @mago-expect lint:wordpress/wp-i18n
    _n_noop(domain: 'my-plugin', plural: 'I have %d cats.', singular: 'I have %d cat.');
}

// Placeholder ordering, ported from WPCS Tests/WP/I18nUnitTest.1.inc.
namespace {
    // @mago-expect lint:wordpress/wp-i18n
    /* translators: 1: number, 2: place. */
    __('There are %d monkeys in the %s', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n(2)
    /* translators: 1: number, 2: place. */
    _n('There is %d monkey in the %s', 'There are %d monkeys in the %s', $number, 'my-plugin');

    /* translators: 1: number, 2: place. */
    _n('There is %1$d monkey in the %2$s', 'In the %2$s there are %1$d monkeys', $number, 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /* translators: 1: number, 2: place. */
    __("%d for %d 'item'", 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /* translators: 1: number, 2: place. */
    __("%04d for %'.9d item", 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /* translators: 1: number, 2: place. */
    __('%1$d for %d item', 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /* translators: 1: number, 2: place. */
    _x(context: 'context', text: 'translate %s me %s', domain: 'my-plugin');

    // @mago-expect lint:wordpress/wp-i18n
    /* translators: 1: number, 2: place. */
    __('There are %1$h monkeys in the %H', 'my-plugin');

    /* translators: 1: text. */
    __('String with a literal %% and a %s placeholder', 'my-plugin');
}

// Translators comment on the same line as the call.
namespace {
    /* translators: %s: name. */ __('Hello %s', 'my-plugin');
}
