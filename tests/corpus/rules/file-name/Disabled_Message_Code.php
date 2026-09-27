<?php

// WPCS only honours a disable of the sniff, its category or standard.
// @mago-expect lint:wordpress/file-name
echo 'not hyphenated';

// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
echo 'still not hyphenated';
