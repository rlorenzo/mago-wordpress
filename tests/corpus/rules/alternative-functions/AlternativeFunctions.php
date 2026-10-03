<?php

declare(strict_types=1);

// @mago-expect lint:wordpress/alternative-functions
use function curl_init as myplugin_curl;

function myplugin_alternatives(string $url, string $html, string $local_file): void
{
    // @mago-expect lint:wordpress/alternative-functions
    $ch = curl_init($url);
    // @mago-expect lint:wordpress/alternative-functions
    CURL_close($ch);
    curl_version();
    // @mago-expect lint:wordpress/alternative-functions
    $parts = parse_url($url);
    // @mago-expect lint:wordpress/alternative-functions
    $json = \json_encode($parts);
    // @mago-expect lint:wordpress/alternative-functions
    $body = file_get_contents($url);
    $body = file_get_contents($local_file, true);
    $body = file_get_contents(ABSPATH . 'wp-admin/css/some-file.css');
    $body = file_get_contents(/* the request body */ 'php://input');
    // @mago-expect lint:wordpress/alternative-functions
    $body = file_get_contents(MYPLUGIN_ABSPATH . 'data.json');
    $stream = fopen('php://output', 'w');
    $stream = fopen(mode: 'w', filename: STDERR);
    // @mago-expect lint:wordpress/alternative-functions
    $stream = fopen($local_file, 'r');
    // @mago-expect lint:wordpress/alternative-functions
    $text = strip_tags($html);
    $text = strip_tags($html, '<p>');
    // @mago-expect lint:wordpress/alternative-functions
    $number = mt_rand(1, 10);
    // @mago-expect lint:wordpress/alternative-functions
    unlink($local_file);
    $number = wp_rand(1, 10);
    // An aliased import resolves to the function, as in every call rule here.
    // @mago-expect lint:wordpress/alternative-functions
    $ch = myplugin_curl();
    $ch = Myplugin\curl_init();
}
