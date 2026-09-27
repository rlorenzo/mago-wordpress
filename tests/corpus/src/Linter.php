<?php

declare(strict_types=1);

namespace Acme\Demo;

use function Acme\Legacy\value;

// @mago-expect lint:wordpress/no-legacy-helper
value('example');

