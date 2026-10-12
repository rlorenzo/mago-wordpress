<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function count;
use function preg_split;
use function strlen;

/**
 * A docblock split into the tokens phpcs 3 makes of it: `open`, `star`, `ws` (whitespace and the
 * line break), `tag`, `string` and `close`. A line's text is one `string` (a tag's text follows
 * the `tag`); on a docblock's last line it keeps the space in front of the closing tag, as in phpcs.
 *
 * @internal
 */
final class DocBlockTokens
{
    private function __construct() {}

    /**
     * @return list<array{code: string, text: string, offset: int, line: int}>
     */
    public static function tokenize(string $docblock, int $base, int $firstLine): array
    {
        $lines = preg_split('~(?<=\n)~', $docblock);
        if ($lines === false) {
            return [];
        }

        $tokens = [];
        $last = count($lines) - 1;
        $position = $base;
        foreach ($lines as $index => $line) {
            foreach (DocBlockLineTokens::of(
                $line,
                $index === 0,
                $index === $last,
                $position,
                $firstLine + $index,
            ) as $token) {
                $tokens[] = $token;
            }

            $position += strlen($line);
        }

        return $tokens;
    }
}
