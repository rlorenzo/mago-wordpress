<?php

declare(strict_types=1);

// A report carries WPCS's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

class Myplugin_Settings
{
    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
    public $someProp;
}

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
$someVar = 1;

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
$value = $object->someProp;

// phpcs:ignore WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase
echo "Hello {$someName}";

// @mago-expect lint:wordpress/valid-variable-name
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
echo "Hello {$otherName}";

// @mago-expect lint:wordpress/valid-variable-name
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
$value = $object->otherProp;

