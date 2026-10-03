<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
$json = json_encode($data);

// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
$ch = curl_init();

// @mago-expect lint:wordpress/alternative-functions
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
$ch = curl_exec($ch);
