<?php

for ( $i = 0; $i < 10; $i++ ) {
	echo $i;
}

// @mago-expect lint:generic/for-loop-should-be-while-loop
for ( ; $it->valid(); ) {
	$it->next();
}

// @mago-expect lint:generic/for-loop-should-be-while-loop
for ( ;( ( $it1->valid() && $foo ) || ( ! $it2->value && ( $bar || false ) ) );/*Could be ignored*/ ) {
	$it1->next();
}

for ( $i = 0, $j = 10; $i < $j; $i++, $j-- ) {
	echo $i, $j;
}

// No test: not reported, as in the sniff.
for ( ;; ) {
	if ( $i >= 10 ) {
		break;
	}
	echo $i++;
}

// @mago-expect lint:generic/for-loop-should-be-while-loop
for ( ; $i < 10; ) :
	echo $i;
	$i++;
endfor;

for ( $i = 0, $len = count( $array ); $i < $len; $i++ ) :
	echo $array[ $i ];
endfor;
