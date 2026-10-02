<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

if (isset($_GET['a'])) {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $a = wp_unslash($_GET['a']);

    // @mago-expect lint:wordpress/validated-sanitized-input
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
    $a = wp_unslash($_GET['a']);
}

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
$b = $_POST['b'];
