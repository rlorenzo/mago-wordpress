<?php

namespace Rules\ObjectOperator;

echo ObjectName::$prop;
echo ObjectName::functionCall();
echo ObjectName::class;

// @mago-expect lint:wordpress/object-operator-spacing(2)
echo ObjectName  ::   $prop;
// @mago-expect lint:wordpress/object-operator-spacing(2)
echo $object -> prop;
// @mago-expect lint:wordpress/object-operator-spacing(2)
echo $object ?-> prop;
// @mago-expect lint:wordpress/object-operator-spacing
echo $object ->prop;
// @mago-expect lint:wordpress/object-operator-spacing
echo $object-> prop;

// A line break is fine around the operator, but not before `class`.
echo $object
	->prop
	->other;
// @mago-expect lint:wordpress/object-operator-spacing
echo $object->method()
	->other()
	-> third();
echo ObjectName
	::
	$prop;

// @mago-expect lint:wordpress/object-operator-spacing(2)
echo ObjectName
	::
	class;

echo $object // Comment
	->prop;
