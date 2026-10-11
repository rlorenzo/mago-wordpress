<?php

declare(strict_types=1);

namespace Vendor\Project;

// @mago-expect lint:generic/lowercase-class-resolution-keyword
echo Foo::CLASS;
// @mago-expect lint:generic/lowercase-class-resolution-keyword
echo $object::Class;

echo Foo::class, Foo::CONSTANT, $object::class;
