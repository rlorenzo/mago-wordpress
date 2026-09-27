<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.CodeAnalysis.EscapedNotTranslated.Found
echo esc_html('text', 'domain');

// @mago-expect lint:wordpress/escaped-not-translated
// phpcs:ignore WordPress.CodeAnalysis.EscapedNotTranslated.Missing
echo esc_html('text', 'domain');

