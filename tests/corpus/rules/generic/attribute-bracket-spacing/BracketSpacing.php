<?php

#[Attr]
#[Attr, Other(1)]
#[Attr(['a' => [1]])]
function ok() {}

// @mago-expect lint:generic/attribute-bracket-spacing(2)
#[ Attr ]
function spaces() {}

// @mago-expect lint:generic/attribute-bracket-spacing
#[ Attr]
function openerSpace() {}

// @mago-expect lint:generic/attribute-bracket-spacing
#[Attr ]
function closerSpace() {}

// @mago-expect lint:generic/attribute-bracket-spacing(2)
#[
	Attr
]
function newlines() {}

// @mago-expect lint:generic/attribute-bracket-spacing
#[Attr,
	Other
 ]
function multiLine() {}

class Example
{
	// @mago-expect lint:generic/attribute-bracket-spacing(2)
	#[  Attr  ]
	public int $property;
}
