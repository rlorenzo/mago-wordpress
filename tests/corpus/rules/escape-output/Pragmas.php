<?php

declare(strict_types=1);

// The pragma forms `mago-wordpress convert-comments` writes (verified on Mago 1.51.0).

function myplugin_pragma_forms(string $title): void
{
    // Before the statement, with a reason.
    // @mago-expect lint:wordpress/escape-output -- reason text
    echo $title;
    echo $title; // @mago-expect lint:wordpress/escape-output -- trailing form
    // A count covers that many issues in the statement.
    // @mago-expect lint:wordpress/escape-output(2)
    echo $title, $title;
    // Several codes in one pragma.
    // @mago-expect lint:wordpress/escape-output(2), lint:wordpress/escape-output
    echo $title, $title, $title;
    // A pragma before a block covers the statements inside it.
    // @mago-expect lint:wordpress/escape-output(2)
    if ($title !== '') {
        echo $title;
        echo $title;
    }
}

/**
 * A docblock pragma covers the whole function.
 *
 * @mago-expect lint:wordpress/escape-output(2) -- why
 */
function myplugin_pragma_docblock(string $title): void
{
    echo $title;
    if ($title !== '') {
        echo $title;
    }
}
?>
<?php
// A pragma in a PHP block of its own covers the next block.
// @mago-expect lint:wordpress/escape-output
?>
<p><?php echo $title; ?></p>
