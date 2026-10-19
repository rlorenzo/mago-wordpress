<?php
$var = (int) $var2;
// @mago-expect lint:generic/cast-spacing
$var = ( int ) $var2;
// @mago-expect lint:generic/cast-spacing
$var = (int ) $var2;
// @mago-expect lint:generic/cast-spacing
$var = ( int) $var2;
// @mago-expect lint:generic/cast-spacing
$var = (	int	) $var2;

$var = (binary) $var2;
// @mago-expect lint:generic/cast-spacing
$var = (   binary   ) $var2;
