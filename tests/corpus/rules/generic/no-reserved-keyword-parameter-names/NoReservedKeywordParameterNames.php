<?php

declare(strict_types=1);

// descriptive_names_are_fine
function no_reserved_keywords($parameter, $descriptive_name): void {}

// each_reserved_name_is_flagged_any_case
// @mago-expect lint:generic/no-reserved-keyword-parameter-names(2)
function has_reserved_keywords($String, $echo = true): void {}

// closures_arrow_functions_and_promoted_properties_too
// @mago-expect lint:generic/no-reserved-keyword-parameter-names(2)
$closure = static fn($callable, $__FILE__) => $callable($__FILE__);

final class Promoted
{
    // @mago-expect lint:generic/no-reserved-keyword-parameter-names
    public function __construct(
        private ?string $parent = null,
    ) {}
}
