<?php

declare(strict_types=1);

namespace Vendor\Project;

// @mago-expect lint:generic/uppercase-magic-constants
echo __line__;
// @mago-expect lint:generic/uppercase-magic-constants
echo __Dir__;
// @mago-expect lint:generic/uppercase-magic-constants(2)
echo __file__, __Namespace__;

echo __LINE__, __DIR__, __FILE__, __NAMESPACE__;
