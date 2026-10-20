<?php

declare(strict_types=1);

// A return without a value is fine, even with a comment before the semicolon.
$anon = new class extends ReturnNoValue {
    public function __construct() {
        if ($foo) {
            return   ;
        } else {
            return /* comments are fine */
            // Even when spread over multiple lines.
            ;
        }

        return;
    }

    // A method that is not a constructor may return.
    public function returnsavalue() {
        return 'value';
    }
};

class ReturnsAValue {
    public function __construct() {
        // @mago-expect lint:generic/constructor-destructor-return-warning
        return $this;
    }

    public function __destruct() {
        // @mago-expect lint:generic/constructor-destructor-return-warning
        return 'destructed';
    }

    function returnsavalue()
    {
        // @mago-expect lint:generic/constructor-destructor-return-warning
        return 'php4style';
    }
}

class NestedFunctions {
    public function __construct() {
        function global_function($foo) {
            return $foo;
        }

        $callback = fn() => 10;
    }
}

// A by-reference constructor is still a constructor.
class ByReference {
    public function &__construct() {
        // @mago-expect lint:generic/constructor-destructor-return-warning
        return $this;
    }
}
