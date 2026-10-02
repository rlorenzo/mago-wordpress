<?php

declare(strict_types=1);

namespace Corpus\SelfMember;

final class SelfMemberReferenceCases
{
    public const A = 1;

    // own_name_in_a_constant_default_is_flagged
    // @mago-expect lint:generic/self-member-reference
    public const B = SelfMemberReferenceCases::A;

    public static function run(): int
    {
        // fully_qualified_own_name_is_flagged
        // @mago-expect lint:generic/self-member-reference
        $a = \Corpus\SelfMember\SelfMemberReferenceCases::A;

        // self_in_another_case_is_flagged
        // @mago-expect lint:generic/self-member-reference
        $b = SELF::A;

        // self_static_and_other_classes_are_fine
        $c = self::A + static::A + \PHP_INT_MAX + Other::A;

        // a_closure_is_its_own_scope_like_the_sniff
        $f = static fn(): int => 0;
        $g = function (): int {
            return SelfMemberReferenceCases::A;
        };

        // phpcs_ignore_silences_it
        // phpcs:ignore Squiz.Classes.SelfMemberReference.NotUsed
        $d = SelfMemberReferenceCases::A;

        return $a + $b + $c + $d + $f() + $g();
    }
}

final class Other
{
    public const A = 2;
}

// the_class_attributes_are_outside_its_scope
#[\Attribute(SelfMemberReferenceAttribute::TARGET)]
final class SelfMemberReferenceAttribute
{
    public const TARGET = 1;
}
