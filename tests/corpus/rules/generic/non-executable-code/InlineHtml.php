<?php

declare(strict_types=1);

// inline_html_and_code_after_a_return_are_flagged_once_per_line
// @mago-expect lint:generic/non-executable-code(3)
function html_after_return(bool $a): int
{
    if ($a) {
        return 1;
        ?>
        <p>unreachable html</p>
        <?php
        echo 'after';
    }

    return 2;
}

// inline_html_after_a_throw_that_closes_the_tag_is_flagged
// @mago-expect lint:generic/non-executable-code
function html_after_throw(): void
{
    throw new RuntimeException(); ?>
<div>after throw</div>
<?php
}
