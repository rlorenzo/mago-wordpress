<?php

declare(strict_types=1);

// escaped_key_is_flagged (the file names the key only through an escape)
// @mago-expect lint:wordpress/posts-per-page
$query = new WP_Query(["posts_per_pag\x65" => 500]);
