<?php

declare(strict_types=1);

// phpcs:ignoreFile -- nothing in this file is reported.
function myplugin_ignored_file(string $url): void
{
    wp_redirect($url);
    exit;
}
