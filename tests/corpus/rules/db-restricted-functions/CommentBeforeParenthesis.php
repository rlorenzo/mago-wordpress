<?php

declare(strict_types=1);

// comment_between_name_and_parenthesis_is_reported
// The file gate must not require `(` right after the name; this is the only match in the file.
// @mago-expect lint:wordpress/db-restricted-functions
mysqli_query /* trivia */ ($db, 'SELECT 1');
