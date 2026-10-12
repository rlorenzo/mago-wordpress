<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function array_values;
use function in_array;
use function preg_match;
use function strlen;
use function strrpos;
use function substr;

/**
 * Looking around a docblock's phpcs tokens, for `generic/doc-comment`.
 *
 * @internal
 */
final class DocBlockScan
{
    private function __construct() {}

    /** @param array{code: string, text: string, offset: int, line: int} $token */
    public static function span(array $token): Span
    {
        return new Span($token['offset'], $token['offset'] + strlen($token['text']));
    }

    /**
     * The first token from $from to before $to that is neither whitespace nor a star.
     *
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    public static function next(array $t, int $from, int $to): ?int
    {
        for ($i = $from; $i < $to; ++$i) {
            if (!in_array($t[$i]['code'], ['ws', 'star'], strict: true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The nearest token at or before $from, down to $to, whose code is one of $codes (`match`) or
     * is none of them.
     *
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     * @param list<string> $codes
     */
    public static function previous(array $t, int $from, int $to, array $codes, bool $match): int
    {
        for ($i = $from; $i >= $to; --$i) {
            if (in_array($t[$i]['code'], $codes, strict: true) === $match) {
                return $i;
            }
        }

        return -1;
    }

    /** @param array{code: string, text: string, offset: int, line: int} $token The column (1-based, a tab counting as one) of a token. */
    public static function column(array $token, string $source): int
    {
        $lineStart = strrpos(substr($source, 0, $token['offset']), "\n");

        return $token['offset'] - ($lineStart === false ? -1 : $lineStart);
    }

    /**
     * The last token of the short description: it runs on over strings on the following lines,
     * tags' text included, as in the sniff.
     *
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    public static function shortEnd(array $t, int $short, int $close): int
    {
        $end = $short;
        for ($i = $short + 1; $i < $close; ++$i) {
            if ($t[$i]['code'] !== 'string') {
                continue;
            }

            if ($t[$i]['line'] !== ($t[$end]['line'] + 1)) {
                break;
            }

            $end = $i;
        }

        return $end;
    }

    /**
     * The tags grouped as the sniff does: tags on consecutive lines (a tag's text lines included)
     * are one group.
     *
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     * @param list<int> $tags
     *
     * @return list<list<int>>
     */
    public static function tagGroups(array $t, array $tags): array
    {
        $groups = [];
        $group = 0;
        foreach ($tags as $position => $tag) {
            if ($position > 0) {
                $last = self::previous($t, $tag - 1, $tags[$position - 1], ['string'], match: true);
                $last = $last === -1 ? $tags[$position - 1] : $last;
                if ($t[$last]['line'] !== ($t[$tag]['line'] - 1)) {
                    ++$group;
                }
            }

            $groups[$group][] = $tag;
        }

        return array_values($groups);
    }
}
