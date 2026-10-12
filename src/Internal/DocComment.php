<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function array_key_exists;
use function count;
use function preg_split;
use function rtrim;
use function strlen;
use function substr;

/**
 * A docblock split into its tags the way phpcs 3 tokenizes one: a tag is the first
 * whitespace-delimited word of a line that starts with `@`; the rest of that line is the tag's
 * string, and the lines up to the next tag, with text on them, are its continuation strings.
 *
 * @internal
 */
final class DocComment
{
    private function __construct() {}

    /**
     * @return list<array{
     *     name: string,
     *     offset: int,
     *     content: ?string,
     *     contentOffset: int,
     *     continuation: list<array{text: string, indent: int, offset: int, spaceOffset: int, spaceLength: int}>,
     *     spaceBeforeClose: bool,
     * }>
     */
    public static function tags(string $docblock, int $base): array
    {
        $tags = [];
        $lineStart = 0;
        $lines = preg_split('~(?<=\n)~', $docblock) ?: [];
        $last = count($lines) - 1;
        foreach ($lines as $index => $line) {
            $raw = $line;
            $line = rtrim($line, "\r\n");
            [$skip, $space, $body] = DocCommentLine::split($line, $index === 0, $index === $last);
            $bodyStart = $skip + $space;
            // phpcs keeps the space in front of the closing `*/` in the tag's string.
            $spaceBeforeClose = $index === $last && $body !== rtrim($body);
            $body = rtrim($body);
            if ($body !== '' && $body[0] === '@') {
                $parts = preg_split('~\s+~', $body, 2) ?: [$body];
                $content = array_key_exists(1, $parts) && $parts[1] !== '' ? $parts[1] : null;
                $tags[] = [
                    'name' => $parts[0],
                    'offset' => $base + $lineStart + $bodyStart,
                    'content' => $content,
                    'contentOffset' => $base + $lineStart + $bodyStart + strlen($body) - strlen($content ?? ''),
                    'continuation' => [],
                    'spaceBeforeClose' => $spaceBeforeClose,
                ];
            } elseif ($body !== '' && $tags !== []) {
                $tags[count($tags) - 1]['continuation'][] = [
                    'text' => $body,
                    'indent' => $space,
                    'offset' => $base + $lineStart + $bodyStart,
                    'spaceOffset' => $base + $lineStart + $skip,
                    'spaceLength' => $space,
                ];
            }

            $lineStart += strlen($raw);
        }

        return $tags;
    }
}
