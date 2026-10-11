<?php

namespace Vendor\Project\FirstNamespace {
    // The first declaration is fine.
}

// @mago-expect lint:generic/one-namespace-declaration-per-file
namespace Vendor\Project\SecondNamespace {
    echo namespace\CONSTANT_NAME;
}

// @mago-expect lint:generic/one-namespace-declaration-per-file
namespace Vendor\Project\ThirdNamespace {
    $foo = namespace\function_call();
}
