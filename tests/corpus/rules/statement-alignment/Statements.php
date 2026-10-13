<?php

function statement_alignment_fixture() {
	$aligned = 1;
	$other   = 2;

	// @mago-expect lint:wordpress/statement-alignment(2)
	$short = 1;
	$longer_name = 2;
	$third = 3;

	$far_apart_variable_name_that_needs_more_than_forty_spaces_of_padding = 1;
	$s = 2;
}

class Statement_Alignment_Comment_Fixture {
	// @mago-expect lint:wordpress/statement-alignment
	const A /* comment */ = 1;
	const LONGER_NAME = 2;
}

class Statement_Alignment_Property_Comment_Fixture {
	// @mago-expect lint:wordpress/statement-alignment
	public $p /* comment */ = 1;
	public $longer_property = 2;
}
