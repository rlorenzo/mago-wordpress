<?php

declare(strict_types=1);

namespace My\Plugin;

// namespaced_const_declaration_is_not_flagged
const STYLESHEETPATH = 'something';

// define_in_namespace_is_still_global_and_flagged
// @mago-expect lint:wordpress/discouraged-constants
define('TEMPLATEPATH', 'something');
