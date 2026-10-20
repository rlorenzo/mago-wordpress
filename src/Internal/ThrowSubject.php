<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function in_array;
use function strtolower;

/**
 * What a single `throw` throws, read from the phpcs tokens the way
 * `Squiz.Commenting.FunctionCommentThrowTag` reads it.
 *
 * @internal
 */
final class ThrowSubject
{
    private function __construct() {}

    /**
     * What one `throw` throws: class names, and whether its subject is unknown.
     *
     * @return array{list<string>, int}
     */
    public static function of(PhpcsTokens $tokens, int $function, int $position, int $end): array
    {
        $next = $position + 1;
        $code = $tokens->code($next);
        // phpcs has T_SELF and T_PARENT tokens, which this branch does not take: `throw self::make()`.
        $named =
            $code === 'T_STRING' && !in_array(strtolower($tokens->content($next)), ['self', 'parent'], strict: true);
        if ($code === 'T_NEW' || $code === 'T_NS_SEPARATOR' || $named) {
            $name = self::className($tokens, $position, $next, $end);

            return [$name === null ? [] : [$name], 0];
        }

        if ($code === 'T_VARIABLE') {
            return [RethrownTypes::of($tokens, $function, $position, $next), 0];
        }

        return [[], 1];
    }

    /** The class name of `throw new Name` (or `throw Name::...`), found the way the sniff finds it. */
    private static function className(PhpcsTokens $tokens, int $throw, int $next, int $end): ?string
    {
        $start = $tokens->code($next) === 'T_NEW'
            ? self::nextOf($tokens, $throw, $end, ['T_NS_SEPARATOR', 'T_STRING'], local: true)
            : $next;
        if ($start === null) {
            return null;
        }

        $name = '';
        for ($i = $start; $i < $end; ++$i) {
            if (!in_array($tokens->code($i), ['T_NS_SEPARATOR', 'T_STRING'], strict: true)) {
                break;
            }

            $name .= $tokens->content($i);
        }

        return $name;
    }

    /**
     * @param list<string> $codes
     */
    private static function nextOf(PhpcsTokens $tokens, int $from, int $end, array $codes, bool $local = false): ?int
    {
        for ($i = $from; $i < $end; ++$i) {
            $code = $tokens->code($i);
            if (in_array($code, $codes, strict: true)) {
                return $i;
            }

            if ($local && $code === ';') {
                break;
            }
        }

        return null;
    }
}
