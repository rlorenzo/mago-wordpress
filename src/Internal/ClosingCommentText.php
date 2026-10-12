<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

/**
 * The comment `Squiz.Commenting.ClosingDeclarationComment` expects after a declaration's closing brace.
 *
 * @internal
 */
final class ClosingCommentText
{
    private function __construct() {}

    public static function expected(PhpcsTokens $tokens, int $index, string $code): ?string
    {
        return match ($code) {
            'T_CLASS' => $tokens->code($index - 1) === 'T_NEW' ? null : '//end class',
            'T_INTERFACE' => '//end interface',
            'T_TRAIT' => '//end trait',
            'T_ENUM' => '//end enum',
            'T_FUNCTION' => self::functionComment($tokens, $index),
            default => null,
        };
    }

    public static function functionComment(PhpcsTokens $tokens, int $index): ?string
    {
        $name = $tokens->code($index + 1) === '&' ? $index + 2 : $index + 1;
        if ($tokens->code($name) !== 'T_STRING') {
            return null;
        }

        return '//end ' . $tokens->content($name) . '()';
    }
}
