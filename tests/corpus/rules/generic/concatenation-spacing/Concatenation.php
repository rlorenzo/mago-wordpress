<?php
// @mago-expect lint:generic/concatenation-spacing(5)
$string = 'Hello'.$there.'. How are'.$you.$going."today $okay";
$string = 'Hello' . $there . '. How are' . $you . $going . "today $okay";
// @mago-expect lint:generic/concatenation-spacing
$string = 'Hello'.$there;
// @mago-expect lint:generic/concatenation-spacing
$string = 'Hello'. $there;
// @mago-expect lint:generic/concatenation-spacing
$string = 'Hello' .$there;

$y = '1'
   . '2'
   . '3';

echo 1 . 'test';
echo 1.1 . 'test';
echo 'test' . 1;
echo 'test' . 1.1;
// @mago-expect lint:generic/concatenation-spacing
echo 'test' . 1 . 'test' . 2.2 . 'test'   .   3;

get_current_screen()->add_help_tab( array(
'id'        => <<<EOD
Here comes some text.
EOD
. '</hr>',
) );

// phpcs:set Squiz.Strings.ConcatenationSpacing spacing 1

// @mago-expect lint:generic/concatenation-spacing(5)
$string = 'Hello'.$there.'. How are'.$you.$going.   "today $okay";
$string = 'Hello' . $there . '. How are' . $you . $going . "today $okay";
// @mago-expect lint:generic/concatenation-spacing
$string = 'Hello'.$there;
// @mago-expect lint:generic/concatenation-spacing
$string = 'Hello'. $there;
// @mago-expect lint:generic/concatenation-spacing
$string = 'Hello' .$there;

// phpcs:set Squiz.Strings.ConcatenationSpacing ignoreNewlines true
$y = '1'
   . '2'
   . '3';

$y = '1' .
     '2' .
     '3';

$y = '1'
. '2'
. '3';

$y = '1'
// @mago-expect lint:generic/concatenation-spacing(2)
   .'2'.
    '3'
// @mago-expect lint:generic/concatenation-spacing
   .  '4';
