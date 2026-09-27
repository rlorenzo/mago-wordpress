<?php

// A phpcs:disable of the sniff with no later phpcs:enable silences the
// whole-file report, wherever it sits, as WPCS's FileNameSniff does.
echo 'not hyphenated';

// phpcs:disable WordPress.Files.FileName -- the name is kept for back-compat.
echo 'still not hyphenated';
