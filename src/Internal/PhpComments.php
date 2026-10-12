<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function strlen;
use function strpos;
use function substr;

/**
 * Scanning past comments in PHP source text.
 *
 * @internal
 */
final class PhpComments
{
    private function __construct() {}

    /** The offset of the first token after whitespace and comments (docblocks included) from $offset. */
    public static function skipEmpty(string $text, int $offset): int
    {
        $length = strlen($text);
        while (true) {
            $offset = PhpText::skipSpace($text, $offset);
            $two = substr($text, $offset, 2);
            if ($two === '/*') {
                $close = strpos($text, '*/', $offset + 2);
                $offset = $close === false ? $length : $close + 2;
            } elseif ($two === '//' || ($text[$offset] ?? '') === '#' && substr($text, $offset, 2) !== '#[') {
                // A line comment ends at the newline, or at a closing tag.
                $body = [];
                preg_match('~\G[^\r\n]*?(?=\?>|[\r\n]|$)~', $text, $body, 0, $offset);
                $offset += strlen($body[0] ?? '');
            } else {
                return $offset;
            }
        }
    }
}
