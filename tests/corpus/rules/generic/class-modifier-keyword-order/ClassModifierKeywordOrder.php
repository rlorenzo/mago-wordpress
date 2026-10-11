<?php

declare(strict_types=1);

namespace Vendor\Project;

// The rule only fires on `readonly` together with `final` or `abstract`, which needs PHP 8.2; the
// corpus targets PHP 8.1, so the reported cases live in tests/Unit/RuleFixesTest.php.
final class E {}

abstract class F {}
