<?php

// When no key ends before column 59 the sniff checks nothing, so keys padded by `mago format` stay.
$allLong = array(
	"a_really_really_really_really_really_really_long_key_here_x"      => 1,
	"a_really_really_really_really_really_really_long_key_here_yyyyy" => 2,
);
