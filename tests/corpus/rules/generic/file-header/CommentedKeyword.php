<?php

namespace Foo\Bar;

use A\B;

use /* the functions */ function A\a;
use // note
    function A\b;

use /* the constants */ const A\C;

echo B::class;
