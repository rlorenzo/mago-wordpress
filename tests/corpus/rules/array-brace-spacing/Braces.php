<?php

// Not (short) array.
$array['access'] = $foo;
[$a, $b] = $array;

// Keyword spacing.
$foo = array();
// @mago-expect lint:wordpress/array-brace-spacing
$foo = array ();
// @mago-expect lint:wordpress/array-brace-spacing
$foo = array /*comment*/ ();

// Empty arrays.
$foo = array();
$foo = [];
// @mago-expect lint:wordpress/array-brace-spacing
$foo = array(  );
// @mago-expect lint:wordpress/array-brace-spacing
$foo = [

];
// @mago-expect lint:wordpress/array-brace-spacing
$foo = [      ];

// Non-empty single-line arrays.
$foo = array( $a, $b );
$foo = [ $a, $b ];
$foo = [ /* comment */ ];
// @mago-expect lint:wordpress/array-brace-spacing
// @mago-expect lint:wordpress/array-brace-spacing
$foo = array($a, $b);
// @mago-expect lint:wordpress/array-brace-spacing
// @mago-expect lint:wordpress/array-brace-spacing
$foo = [$a, $b];
// @mago-expect lint:wordpress/array-brace-spacing
// @mago-expect lint:wordpress/array-brace-spacing
$foo = array(   $a, $b      );
// @mago-expect lint:wordpress/array-brace-spacing
// @mago-expect lint:wordpress/array-brace-spacing
$foo = [  $a, $b];
// @mago-expect lint:wordpress/array-brace-spacing
// @mago-expect lint:wordpress/array-brace-spacing
$foo = array(/* comment */ /* comment */   );

// Non-empty multi-line arrays.
$array = array(
    $a,
    $b
);

$array = [
    $a,
    $b
];

// @mago-expect lint:wordpress/array-brace-spacing
$array = array($a,
    // @mago-expect lint:wordpress/array-brace-spacing
    $b);

$array = [



    $a,
    // @mago-expect lint:wordpress/array-brace-spacing
    $b];

// @mago-expect lint:wordpress/array-brace-spacing
$array = array($a,
    $b
);

$array = array(
    // Comment at the first line is always fine.
    $a,
    $b
);

$array = array( // Allow trailing comment after opener.
    $a,
    $b
);

$array = array( /* Allow trailing comment after opener. */
    $a,
    $b
);

$array = array( // phpcs:ignore Stdn.Cat.Sniff -- Allow trailing annotation after opener.
    $a,
    $b
);

// @mago-expect lint:wordpress/array-brace-spacing
$array = array( /* Allow trailing comment after opener. */ /* But don't allow a second one */
    $a,
    $b
);

// @mago-expect lint:wordpress/array-brace-spacing
$array = array( /* This is still an error */ $a,
    $b
);

$foo = array(
    'key' => 'value', // Comment.
    // Comment.
);
