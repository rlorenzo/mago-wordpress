<?php

declare(strict_types=1);

$items = array( 1, 2 );
?>
<ul>
<?php foreach ( $items as $item ) : ?>
	<li><?php echo $item; ?></li>
<?php endforeach; ?>
</ul>
<?php
// @mago-expect lint:generic/embedded-php
echo 1; ?>
<?php // phpcs:disable Squiz.PHP.EmbeddedPhp.ContentBeforeOpen,Squiz.PHP.EmbeddedPhp.ContentAfterEnd ?>
<p><?php
	echo 2;
?></p>
<?php // phpcs:enable Squiz.PHP.EmbeddedPhp ?>
<p><?= $items[0]; ?></p>
<p><?php if ( $items ) { ?>yes<?php } ?></p>
<?php
// @mago-expect lint:generic/embedded-php
$a = array( 1 ); echo 3 ?>
<p>end</p>
