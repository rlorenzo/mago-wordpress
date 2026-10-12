<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function in_array;
use function ltrim;
use function preg_match;
use function rtrim;
use function sprintf;
use function strlen;
use function substr;
use function trim;

/**
 * The spacing check `Squiz.Commenting.InlineComment` makes on the start of a `//` comment.
 *
 * @internal
 */
final class CommentSpacing
{
    private function __construct() {}

    /**
     * @return null|array{string, string} the message and the sniff code of the problem, if any
     */
    public static function problem(string $text): ?array
    {
        $comment = rtrim($text);
        if (trim(substr($comment, 2)) === '') {
            return null;
        }

        $spaces = 0;
        for ($i = 2, $length = strlen($comment); $i < $length; ++$i) {
            if ($comment[$i] === "\t") {
                return [
                    sprintf(
                        'Tab found before comment text; expected "// %s" but found "%s"',
                        ltrim(substr($comment, 2)),
                        $comment,
                    ),
                    'TabBefore',
                ];
            }

            if ($comment[$i] !== ' ') {
                break;
            }

            ++$spaces;
        }

        return match (true) {
            $spaces === 0 => [
                sprintf(
                    'No space found before comment text; expected "// %s" but found "%s"',
                    substr($comment, 2),
                    $comment,
                ),
                'NoSpaceBefore',
            ],
            $spaces > 1 => [
                sprintf(
                    'Expected 1 space before comment text but found %s; use block comment if you need indentation',
                    $spaces,
                ),
                'SpacingBefore',
            ],
            default => null,
        };
    }

    /** Whether text that starts with a letter, a sentence, does not end in a full stop, `!` or `?`. */
    public static function endsBadly(string $words): bool
    {
        return preg_match('/^\p{L}/u', $words) === 1 && !in_array(substr($words, -1), ['.', '!', '?'], strict: true);
    }
}
