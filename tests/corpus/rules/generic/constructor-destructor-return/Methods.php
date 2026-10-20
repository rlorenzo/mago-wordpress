<?php

declare(strict_types=1);

// A global function, and methods that are not constructors, may return.
function __construct_like(): int {
    return 123;
}

class NotAConstructor {
    public function Foo(): void {}

    public function Bar() {
        return $this;
    }
}

class NoReturn {
    protected function __construct()
    {
        return;
    }

    protected function __destruct()
    {
        // Do something.
    }

    function NoReturn()
    {
        // Do something.
    }
}

// A PHP 4-style constructor is a method named like its class.
class ReturnsAValue {
    // @mago-expect lint:generic/constructor-destructor-return
    function returnsavalue(): string
    {
        return 'php4style';
    }
}

// A nested closure may return a value.
class NestedClosures {
    public function __construct() {
        do_something(
            function () {
                return true;
            },
        );
    }
}
