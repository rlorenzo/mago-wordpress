<?php

declare(strict_types=1);

// Calls without an expectation must be silenced by a phpcs comment.
function myplugin_suppressed(string $url): void
{
    // phpcs:ignore WordPress.Security.SafeRedirect -- next-line ignore.
    wp_redirect($url);
    wp_redirect($url); // phpcs:ignore -- trailing ignore.
    // @mago-expect lint:wordpress/safe-redirect
    wp_redirect($url);

    // @mago-expect lint:wordpress/safe-redirect
    // phpcs:ignore WordPress.WP.I18n -- another sniff.
    wp_redirect($url);
    // @mago-expect lint:wordpress/safe-redirect
    wp_redirect($url); // phpcs:ignore WordPress.Security.EscapeOutput

    /* phpcs:ignore WordPress.Security */
    wp_redirect($url);
    # phpcs:ignore Generic.PHP, WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
    wp_redirect($url);

    // phpcs:disable WordPress.Security
    wp_redirect($url);
    wp_redirect($url);
    // phpcs:enable
    // @mago-expect lint:wordpress/safe-redirect
    wp_redirect($url);

    // phpcs:disable WordPress
    wp_redirect($url);
    // phpcs:enable WordPress.Security.SafeRedirect
    // @mago-expect lint:wordpress/safe-redirect
    wp_redirect($url);
    // phpcs:enable

    // @codingStandardsIgnoreLine
    wp_redirect($url);
    // @codingStandardsIgnoreStart
    wp_redirect($url);
    // @codingStandardsIgnoreEnd
    // @mago-expect lint:wordpress/safe-redirect
    wp_redirect($url);
    exit;
}
