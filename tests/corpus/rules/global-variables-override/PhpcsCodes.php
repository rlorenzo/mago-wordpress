<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

function myplugin_override(): void
{
    global $post;

    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    $post = get_post(1);

    // @mago-expect lint:wordpress/global-variables-override
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Found
    $post = get_post(2);
}
