<?php

// A phpcs:disable that a later phpcs:enable closes does not silence it.
// @mago-expect lint:wordpress/file-name
echo 'not hyphenated';

// phpcs:disable WordPress.Files
echo 'still not hyphenated';
// phpcs:enable WordPress.Files.FileName
