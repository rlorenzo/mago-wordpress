<?php

$heredoc = <<<EOD
some text
EOD;

$nowdoc = <<<'EOD'
some text
EOD;

// @mago-expect lint:generic/here-nowdoc-identifier-spacing
$heredoc = <<< END
some text
END;

// @mago-expect lint:generic/here-nowdoc-identifier-spacing
$nowdoc = <<<             'END'
some text
END;

// @mago-expect lint:generic/here-nowdoc-identifier-spacing
$heredoc = <<<			"END"
some text
END;

// @mago-expect lint:generic/here-nowdoc-identifier-spacing
$nowdoc = <<<	'END'
some text
END;
