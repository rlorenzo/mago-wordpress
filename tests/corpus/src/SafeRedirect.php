<?php

declare(strict_types=1);

function myplugin_go(string $url): void
{
    // @mago-expect lint:wordpress/safe-redirect
    wp_redirect($url);
    // @mago-expect lint:wordpress/safe-redirect
    \wp_redirect($url, 302);
    wp_safe_redirect($url);
    exit;
}
