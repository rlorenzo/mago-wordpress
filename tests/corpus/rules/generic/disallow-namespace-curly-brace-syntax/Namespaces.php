<?php

declare(strict_types=1);

// @mago-expect lint:generic/disallow-namespace-curly-brace-syntax
namespace Foo {
	class Bar {}
}

// @mago-expect lint:generic/disallow-namespace-curly-brace-syntax
namespace {
	class Baz {}
}
