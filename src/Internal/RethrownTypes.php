<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function explode;
use function trim;

/**
 * The exception types of the `catch` block whose variable a function rethrows.
 *
 * @internal
 */
final class RethrownTypes
{
    private function __construct() {}

    /**
     * The types of the nearest `catch` when it caught the variable being thrown.
     *
     * @return list<string>
     */
    public static function of(PhpcsTokens $tokens, int $function, int $throw, int $variable): array
    {
        $catch = null;
        for ($i = $throw; $i > $function; --$i) {
            if ($tokens->code($i) === 'T_CATCH') {
                $catch = $i;

                break;
            }
        }

        $open = $catch === null ? null : $catch + 1;
        $close = $open === null ? null : $tokens->closer($open);
        if ($open === null || $close === null) {
            return [];
        }

        $caught = null;
        for ($i = $close - 1; $i > $open; --$i) {
            if ($tokens->code($i) === 'T_VARIABLE') {
                $caught = $i;

                break;
            }
        }

        if ($caught === null || $tokens->content($caught) !== $tokens->content($variable)) {
            return [];
        }

        $types = '';
        for ($i = $open + 1; $i < $caught; ++$i) {
            $types .= $tokens->content($i);
        }

        $result = [];
        foreach (explode('|', $types) as $type) {
            $result[] = trim($type);
        }

        return $result;
    }
}
