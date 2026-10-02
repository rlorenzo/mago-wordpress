<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.Security.NonceVerification.Missing
$a = sanitize_text_field(wp_unslash($_POST['a'] ?? ''));

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$b = absint($_GET['b'] ?? 0);

// @mago-expect lint:wordpress/nonce-verification
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$c = sanitize_text_field(wp_unslash($_POST['c'] ?? ''));
