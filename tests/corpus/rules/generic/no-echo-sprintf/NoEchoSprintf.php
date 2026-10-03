<?php

declare(strict_types=1);

// echo_sprintf_and_vsprintf_are_flagged
// @mago-expect lint:generic/no-echo-sprintf
echo sprintf('%s items', $count);
// @mago-expect lint:generic/no-echo-sprintf
echo \vsprintf('%s of %s', [$a, $b]);

// compound_echoes_and_other_calls_are_fine
echo sprintf('%s', $a), $b;
echo sprintf('%s', $a) . $b;
printf('%s', $a);
echo esc_html(sprintf('%s', $a));

// phpcs_ignore_silences_it
// phpcs:ignore Universal.CodeAnalysis.NoEchoSprintf.Found
echo sprintf('%s', $a);
