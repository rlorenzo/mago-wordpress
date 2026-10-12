<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function preg_match;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * One line of a docblock, split into its comment leader, indent and text.
 *
 * @internal
 */
final class DocCommentLine
{
    private function __construct() {}

    /**
     * The comment-leader width, the indent after it and the text of one docblock line.
     *
     * @return array{int, int, string}
     */
    public static function split(string $line, bool $first, bool $last): array
    {
        $skip = 0;
        $star = [];
        if ($first && str_starts_with($line, '/**')) {
            $skip = 3;
        } elseif (preg_match('~^[ \t]*\*(?!/)~', $line, $star) === 1) {
            $skip = strlen($star[0]);
        }

        // Without a star the whitespace in front of the text is the indent.
        $blank = [];
        $from = $skip === 0 && !$first ? $line : substr($line, $skip);
        $space = preg_match('~^[ \t]*~', $from, $blank) === 1 ? strlen($blank[0]) : 0;
        $body = substr($line, $skip + $space);
        if ($last && str_ends_with($body, '*/')) {
            $body = substr($body, 0, -2);
        }

        return [$skip, $space, $body];
    }
}
