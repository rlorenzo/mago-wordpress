<?php

function ok(): int {}
function nullable(): ?int {}
function union(): int|string {}
$closure = function (): void {};
$use = function () use ( $a ): void {};
$arrow = fn(): int => 1;

// @mago-expect lint:generic/return-type-declaration
function space_before_colon() : int {}
// @mago-expect lint:generic/return-type-declaration
function no_space_after_colon():int {}
// @mago-expect lint:generic/return-type-declaration
function two_spaces():  int {}
// @mago-expect lint:generic/return-type-declaration
function nullable_no_space():?int {}
// @mago-expect lint:generic/return-type-declaration
function nullable_two_spaces():  ?int {}
// @mago-expect lint:generic/return-type-declaration
$closure = function () : void {};
// @mago-expect lint:generic/return-type-declaration
$closure = function ():void {};
// @mago-expect lint:generic/return-type-declaration(2)
$use = function () use ( $a ) :void {};
// @mago-expect lint:generic/return-type-declaration
$arrow = fn() : int => 1;
// @mago-expect lint:generic/return-type-declaration
$arrow = fn():int => 1;
function newline_after_colon():
// @mago-expect lint:generic/return-type-declaration
int {}

interface Bar {
// @mago-expect lint:generic/return-type-declaration
	public function method() : int;
// @mago-expect lint:generic/return-type-declaration
	public function other():int;
}
