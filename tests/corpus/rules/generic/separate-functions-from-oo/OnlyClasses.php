<?php

declare(strict_types=1);

// methods_closures_and_anonymous_classes_are_not_function_declarations
final class SeparateFunctionsOnlyClasses
{
    public function method(): object
    {
        $f = static function (): void {};
        $f();

        return new class {
            public function inner(): void {}
        };
    }
}

interface SeparateFunctionsInterface {}
