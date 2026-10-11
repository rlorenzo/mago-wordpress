<?php

declare(strict_types=1);

namespace Vendor\Project;

class A
{
    // @mago-expect lint:generic/constant-modifier-keyword-order
    public final const ONE = 1;
    // @mago-expect lint:generic/constant-modifier-keyword-order
    private final const TWO = 2;

    final public const THREE = 3;
    public const FOUR = 4;
    final const FIVE = 5;
}
