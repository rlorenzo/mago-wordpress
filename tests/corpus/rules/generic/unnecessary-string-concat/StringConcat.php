<?php

$a = 'text';

// @mago-expect lint:generic/unnecessary-string-concat
$one = 'My ' . 'string';
// @mago-expect lint:generic/unnecessary-string-concat
$two = "My " . "string";
// @mago-expect lint:generic/unnecessary-string-concat
$three = "$a text" . "more";
// @mago-expect lint:generic/unnecessary-string-concat(2)
$four = 'a' . 'b' . 'c';
// @mago-expect lint:generic/unnecessary-string-concat
$five = $a . 'one' . 'two';
// @mago-expect lint:generic/unnecessary-string-concat
echo $data['my' . 'index'];
// @mago-expect lint:generic/unnecessary-string-concat
$six = 'a'
	. 'b';

$mixed = 'My ' . "string";
$number = 'My ' . 1234;
$variable = 'My ' . $a . ' test';
$code = ' ?' . '>';
$tag = '<' . '?php ';
$comment_after = 'My' . /* comment */ 'string';
$comment_before = 'My' /* comment */ . 'string';
$sum = 1 + 1;
