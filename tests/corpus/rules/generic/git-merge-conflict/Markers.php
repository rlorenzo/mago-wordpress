<?php

declare(strict_types=1);

// markers_in_a_nowdoc_are_flagged
// @mago-expect lint:generic/git-merge-conflict(3)
$template = <<<'EOT'
<<<<<<< HEAD
<p>ours</p>
=======
<p>theirs</p>
>>>>>>> feature
EOT;

// indented_or_partial_markers_are_not_flagged
$text = <<<'EOT'
  <<<<<<< HEAD
<<<<<<< main
========
>>>>>>>no-space
EOT;
