<?php

try {
} catch ( A | B $e ) {
}

try {
// @mago-expect lint:wordpress/operator-spacing(2)
} catch ( A|B $e ) {
}

try {
// @mago-expect lint:wordpress/operator-spacing
} catch ( A |B $e ) {
}

try {
// @mago-expect lint:wordpress/operator-spacing
} catch ( A| B $e ) {
}

try {
// @mago-expect lint:wordpress/operator-spacing(2)
} catch ( A  |  B $e ) {
}

try {
} catch ( A
	| B
	| C $e ) {
}

try {
} catch ( A
	| B
	// @mago-expect lint:wordpress/operator-spacing
	|C $e ) {
}

$x = 1|2;
function f( A|B $x ): C|D {
}
