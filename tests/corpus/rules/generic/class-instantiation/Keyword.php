<?php

enum Status: string
{
    public const NEW = 'new';
}

$a = Status::NEW;
$b = array( \Foo\Status::NEW, 'x' );

// @mago-expect lint:generic/class-instantiation
$c = new Foo;
