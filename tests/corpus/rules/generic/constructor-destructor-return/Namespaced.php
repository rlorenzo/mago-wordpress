<?php

declare(strict_types=1);

namespace Some\Name;

// In a namespace a method named like its class is a regular method.
class ReturnsAValue {
    function returnsAValue(): string
    {
        return 'php4style';
    }
}
