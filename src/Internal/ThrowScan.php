<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

/**
 * Finding the tokens `Squiz.Commenting.FunctionCommentThrowTag` stops at.
 *
 * @internal
 */
final class ThrowScan
{
    private function __construct() {}

    /** The next `throw`, closure or anonymous class: the tokens the sniff stops at. */
    public static function nextScope(PhpcsTokens $tokens, int $from, int $end): ?int
    {
        for ($i = $from; $i < $end; ++$i) {
            $code = $tokens->code($i);
            if ($code === 'T_THROW') {
                return $i;
            }

            if ($code === 'T_CLASS' && $tokens->code($i - 1) === 'T_NEW') {
                return $i;
            }

            if ($code === 'T_FUNCTION') {
                $next =
                    $tokens->code($i + 1) === 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG'
                    || $tokens->code($i + 1) === '&'
                        ? $i + 2
                        : $i + 1;
                if ($tokens->code($next) === '(') {
                    return $i;
                }
            }
        }

        return null;
    }
}
