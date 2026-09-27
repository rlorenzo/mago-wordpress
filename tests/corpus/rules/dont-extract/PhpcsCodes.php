<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
extract($values);

// @mago-expect lint:wordpress/dont-extract
// phpcs:ignore WordPress.PHP.DontExtract.Found
extract($values);

