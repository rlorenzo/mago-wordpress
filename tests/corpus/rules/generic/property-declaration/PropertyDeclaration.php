<?php

declare(strict_types=1);

final class PropertyDeclarationCases
{
    // fine
    public $a = null;
    protected static ?string $b = null;
    private readonly int $c;

    // var_is_flagged_and_has_no_visibility
    // @mago-expect lint:generic/property-declaration(2)
    var $d;

    // static_alone_has_no_visibility
    // @mago-expect lint:generic/property-declaration
    static $e;

    // several_properties_in_one_statement_report_once
    // @mago-expect lint:generic/property-declaration
    public $f, $g;

    // static_before_visibility
    // @mago-expect lint:generic/property-declaration
    static public $h;

    // readonly_before_visibility
    // @mago-expect lint:generic/property-declaration
    readonly public int $i;

    // spacing_after_type
    // @mago-expect lint:generic/property-declaration
    public int   $j;

    // underscore_is_the_warning_rule
    public $_k;

    // promoted_constructor_parameters_are_not_properties
    public function __construct(
        private $_l,
    ) {}

    // phpcs_ignore_silences_it
    // phpcs:ignore PSR2.Classes.PropertyDeclaration.VarUsed, PSR2.Classes.PropertyDeclaration.ScopeMissing
    var $m;
}
