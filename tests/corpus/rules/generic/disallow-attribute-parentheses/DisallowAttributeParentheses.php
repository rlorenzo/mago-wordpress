<?php

declare(strict_types=1);

namespace Vendor\Project;

// @mago-expect lint:generic/disallow-attribute-parentheses
#[MyAttribute()]
function one(): void {}

// @mago-expect lint:generic/disallow-attribute-parentheses(2)
#[MyAttribute(), Other( /* none */ )]
function two(): void {}

#[MyAttribute]
#[Other(1), Third('x')]
function three(): void {}
