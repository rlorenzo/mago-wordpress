<?php

declare(strict_types=1);

echo 1;
?>
<div>
<?php echo $var; ?>
<?php
// @mago-expect lint:generic/disallow-short-open-tag
?>
<?= $var; ?>
Some content <?php
// @mago-expect lint:generic/disallow-short-open-tag
?><?= $var ?> more content
<?php
// @mago-expect lint:generic/disallow-short-open-tag
?>
<?=$var; ?>
</div>
<?php
// @mago-expect lint:generic/disallow-short-open-tag
?>
<? echo 3; ?>
<?php echo 2;
