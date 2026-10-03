<?php

declare(strict_types=1);

function myplugin_render(string $title, array $items, int $count, WP_Post $post): void
{
    // @mago-expect lint:wordpress/escape-output
    echo $title;
    echo esc_html($title);
    echo 'Plain text ' . esc_attr($title) . (int) $count;
    // Auto-escaped WordPress functions.
    echo wp_get_attachment_image($post->ID, 'large');
    echo do_shortcode('[gallery]');
    echo get_the_date('', $post);
    // @mago-expect lint:wordpress/escape-output
    echo esc_html($title) . $title;
    // @mago-expect lint:wordpress/escape-output(2)
    echo $count ? $title : $items[0];
    echo $count ? esc_html($title) : '';
    // @mago-expect lint:wordpress/escape-output
    echo implode(', ', $items);
    echo implode(', ', array_map('esc_html', $items));
    // @mago-expect lint:wordpress/escape-output
    print $title;
    // @mago-expect lint:wordpress/escape-output
    _e('Unescaped translation', 'my-plugin');
    esc_html_e('Escaped translation', 'my-plugin');
    // @mago-expect lint:wordpress/escape-output
    printf('Hello %s', $title);
    printf('Hello %s', esc_html($title));
    // Custom lists from composer.json.
    echo myplugin_esc($title);
    echo myplugin_safe_html();
    // @mago-expect lint:wordpress/escape-output
    myplugin_print($title);
    // @mago-expect lint:wordpress/escape-output
    echo get_search_query(false);
    echo <<<HTML
        <p>No interpolation.</p>
        HTML;
    // @mago-expect lint:wordpress/escape-output
    echo <<<HTML
        <p>{$title}</p>
        HTML;
}

function myplugin_fail(string $message): void
{
    try {
        throw new Exception($message);
    } catch (Exception $e) {
        // @mago-expect lint:wordpress/escape-output
        throw new RuntimeException($message);
    }
}

// @mago-expect lint:wordpress/escape-output
exit($argv[0]);

function myplugin_match_arrow(int $kind, string $title): void
{
    $handler = match ($kind) {
        1 => fn() => esc_html($title),
        default => 2,
    };
    // @mago-expect lint:wordpress/escape-output
    echo $title;
}
