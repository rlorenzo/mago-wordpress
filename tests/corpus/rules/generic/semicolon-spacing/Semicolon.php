<?php
$test = $this->testThis();
// @mago-expect lint:generic/semicolon-spacing
$test = $this->testThis() ;
// @mago-expect lint:generic/semicolon-spacing
$test = $this->testThis()           ;
// @mago-expect lint:generic/semicolon-spacing(2)
for ($var = 1   ; $var < 10  ; $var++) {
// @mago-expect lint:generic/semicolon-spacing
    echo $var ;
}
// @mago-expect lint:generic/semicolon-spacing
$test = $this->testThis()  /* comment here */;
// @mago-expect lint:generic/semicolon-spacing
$test = $this->testThis()  /* comment here */  ;

$hello ='foo';
;

// @mago-expect lint:generic/semicolon-spacing
$sum = $a /* + $b */;
$sum = $a // + $b
// @mago-expect lint:generic/semicolon-spacing
;

/*
 * Test that the sniff does *not* throw incorrect errors for semicolons in
 * "empty" parts of a `for` control structure.
 */
for ($i = 1; ; $i++) {}
for ( ; $ptr >= 0; $ptr-- ) {}
for ( ; ; ) {}

// But it should when the semicolon in a `for` follows a comment (but shouldn't move the semicolon).
// @mago-expect lint:generic/semicolon-spacing
for ( /* Deliberately left empty. */ ; $ptr >= 0; $ptr-- ) {}
// @mago-expect lint:generic/semicolon-spacing(2)
for ( $i = 1 ; /* Deliberately left empty. */ ; $i++ ) {}

switch ($foo) {
    case 'foo':
    ;
    break
// @mago-expect lint:generic/semicolon-spacing
    ;
}

// This is an empty statement and should be ignored.
if ($foo) {
;
}
