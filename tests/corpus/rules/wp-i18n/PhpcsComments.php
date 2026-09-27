<?php

declare(strict_types=1);

// Calls without an expectation must be silenced by a phpcs comment.

// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
$text = sprintf(__('%s items', 'my-plugin'), $count);

// A message code for another message does not silence this one.
// @mago-expect lint:wordpress/wp-i18n
$text = sprintf(__('%s items', 'my-plugin'), $count); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText

$text = __($dynamic, 'my-plugin'); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText

// phpcs:disable WordPress.WP.I18n
$text = __($dynamic, 'other-plugin');
// phpcs:enable WordPress.WP.I18n.TextDomainMismatch
// @mago-expect lint:wordpress/wp-i18n
$text = __('Hello', 'other-plugin');
$text = __($dynamic, 'my-plugin');
// phpcs:enable

// @mago-expect lint:wordpress/wp-i18n
$text = __('Hello', 'other-plugin');
