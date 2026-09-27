<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInTernaryCondition
$mode = ($a = 1) ? 1 : 2;

// @mago-expect lint:wordpress/assignment-in-ternary-condition
// phpcs:ignore WordPress.CodeAnalysis.AssignmentInTernaryCondition.FoundInCondition
$mode = ($a = 1) ? 1 : 2;

