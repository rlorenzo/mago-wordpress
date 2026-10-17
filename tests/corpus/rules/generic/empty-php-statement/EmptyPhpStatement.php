<?php

declare(strict_types=1);

echo 1;
?>
<input name="<?php echo 2; ?>" />
<?php /* comment */ ?>
<?php
// @mago-expect lint:generic/empty-php-statement
?>
<input name="<?php ?>" />
<?php
// @mago-expect lint:generic/empty-php-statement
?>
<input name="<?php


?>" />
<?php
// @mago-expect lint:generic/empty-php-statement
?>
<input name="<?= ?>" />
<?php
// @mago-expect lint:generic/empty-php-statement
?>
<input name="<?= ; ?>" />
<?= 'some text' ?>
<?php echo 3;
