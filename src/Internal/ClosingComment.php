<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function str_starts_with;
use function substr;

/**
 * The `//end class` comment check of `Squiz.Commenting.ClosingDeclarationComment` for one token.
 *
 * @internal
 */
final class ClosingComment
{
    private function __construct() {}

    /**
     * @return null|array{int, string, string, TextEdit} the closing brace, the sniff code, the
     *                                                    expected comment and the fix
     */
    public static function of(PhpcsTokens $tokens, string $text, int $index, string $code): ?array
    {
        $expected = ClosingCommentText::expected($tokens, $index, $code);
        $closer = $expected === null ? null : $tokens->scopeCloser($index);
        if ($expected === null || $closer === null) {
            return null;
        }

        $brace = $tokens->pos($closer);
        $after = $brace + 1;
        $direct = self::startsComment($text, $after);
        $start = $direct ? $after : PhpText::skipSpace($text, $after);
        if (!self::startsComment($text, $start)) {
            return null;
        }

        [$comment, $end, $ending] = LineComment::at($text, $start);
        // A comment right after the brace must be the expected one; one further on must not be.
        if (($comment === $expected) === $direct) {
            return null;
        }

        return [
            $brace,
            $direct ? 'Incorrect' : 'Misplaced',
            $expected,
            TextEdit::replace(new Span($after, $end), $expected . $ending),
        ];
    }

    private static function startsComment(string $text, int $offset): bool
    {
        return (
            str_starts_with(substr($text, $offset, 2), '//')
            || ($text[$offset] ?? '') === '#'
            && ($text[$offset + 1] ?? '') !== '['
        );
    }
}
