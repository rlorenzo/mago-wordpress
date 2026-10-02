<?php

declare(strict_types=1);

// a_heredoc_with_nothing_interpolated_is_flagged
// @mago-expect lint:generic/unnecessary-heredoc
$a = <<<EOT
plain text
EOT;

// interpolation_escapes_and_nowdocs_are_fine
$b = <<<EOT
Hello {$name}
EOT;
$c = <<<EOT
tab\there
EOT;
$d = <<<'EOT'
plain text
EOT;

// phpcs_ignore_silences_it
// phpcs:ignore Generic.Strings.UnnecessaryHeredoc.Found
$e = <<<EOT
plain text
EOT;
