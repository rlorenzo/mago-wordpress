<?php

declare(strict_types=1);

// A report carries the sniff's own message code, so the upstream suppression spelling
// silences it, and a sibling message code does not.

final class SelfMemberReferenceCodes
{
    public const A = 1;

    public function run(): int
    {
        // phpcs:ignore Squiz.Classes.SelfMemberReference.IncorrectCase
        $a = Self::A;

        // @mago-expect lint:generic/self-member-reference
        // phpcs:ignore Squiz.Classes.SelfMemberReference.NotUsed
        $b = Self::A;

        return $a + $b;
    }
}
