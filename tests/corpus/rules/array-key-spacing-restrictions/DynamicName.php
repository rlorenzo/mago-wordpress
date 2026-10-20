<?php

// A bracket after the `}` of a dynamic property name is an array access, not a short array.
// @mago-expect lint:wordpress/array-key-spacing-restrictions
$a = isset( $this->{$this->key}[$offset] ); // Bad.
$b = $this->{$name}[ $offset ]; // Good.
