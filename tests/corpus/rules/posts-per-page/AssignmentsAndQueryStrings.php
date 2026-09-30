<?php

declare(strict_types=1);

// Ported from WPCS's PostsPerPageUnitTest.inc: array-key assignments, query
// strings, and non-decimal/float literals.

// @mago-expect lint:wordpress/posts-per-page
$query_args['posts_per_page'] = 999;

// @mago-expect lint:wordpress/posts-per-page
$query_args['posts_per_page'] ??= 200;

$query_args['posts_per_page'] = 1;
$query_args['posts_per_page'] = -1;
$query_args['nopaging'] = true;
$query_args['posts_per_page'] ??= 50;
$query_args['posts_per_page'] += 999;
$query_args['my_posts_per_page'] = 999;
$query_args['posts_per_page'] = '1e3';
$var = $query_args['posts_per_page'];

$query_args[
    // @mago-expect lint:wordpress/posts-per-page
    'posts_per_page'
] = 300;

// @mago-expect lint:wordpress/posts-per-page
_query_posts('nopaging=true&posts_per_page=999');

// @mago-expect lint:wordpress/posts-per-page
_query_posts('numberposts=999');

// @mago-expect lint:wordpress/posts-per-page
\get_posts('numberposts=500');

_query_posts('numberposts=-1');
_query_posts('numberposts');
_query_posts('posts_per_page=999&nopaging=true&posts_per_page=50');
_query_posts('nopaging=true&posts_per_page=');
$query = 'posts_per_page=' . (int) $_POST['limit'];

$args = [
    'posts_per_page' => +50,
    // @mago-expect lint:wordpress/posts-per-page
    'posts_per_page' => +200,
    'posts_per_page' => 0b1001011,
    // @mago-expect lint:wordpress/posts-per-page
    'posts_per_page' => 0b10010110,
    'posts_per_page' => 0x4B,
    // @mago-expect lint:wordpress/posts-per-page
    'posts_per_page' => 0x96,
    'posts_per_page' => 0113,
    // @mago-expect lint:wordpress/posts-per-page
    'posts_per_page' => 0226,
    'posts_per_page' => 0o113,
    // @mago-expect lint:wordpress/posts-per-page
    'posts_per_page' => 0o226,
    'posts_per_page' => 75.0,
    // @mago-expect lint:wordpress/posts-per-page
    'posts_per_page' => 150.000,
];
