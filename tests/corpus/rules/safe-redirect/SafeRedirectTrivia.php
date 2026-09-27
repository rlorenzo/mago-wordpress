<?php

declare(strict_types=1);

// Kept alone in its file: no other call here may satisfy the rule's file gate.
function myplugin_go_commented(string $url): void
{
    // @mago-expect lint:wordpress/safe-redirect
    wp_redirect /* comment */ ($url);
    exit;
}
