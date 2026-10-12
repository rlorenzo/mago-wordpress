<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function array_keys;
use function count;
use function sprintf;
use function str_ends_with;
use function strpos;
use function substr;

/**
 * Compares the exceptions a function throws with its `@throws` tags, as
 * `Squiz.Commenting.FunctionCommentThrowTag` does.
 *
 * @internal
 */
final class ThrowTags
{
    private function __construct() {}

    /**
     * @param list<string> $exceptions the distinct classes thrown
     * @param array<string, true> $tags the first word of each `@throws` tag
     *
     * @return list<array{string, string}> message and sniff code of each problem
     */
    public static function problems(array $exceptions, int $unknown, array $tags): array
    {
        $expected = count($exceptions) + $unknown;
        if ($expected !== count($tags)) {
            return [[
                sprintf('Expected %s @throws tag(s) in function comment; %s found', $expected, count($tags)),
                'WrongNumber',
            ]];
        }

        $problems = [];
        foreach ($exceptions as $throw) {
            if (isset($tags[$throw]) || self::endsWithTag($tags, $throw)) {
                continue;
            }

            $problems[] = [sprintf('Missing @throws tag for "%s" exception', $throw), 'Missing'];
        }

        return $problems;
    }

    /** @param array<string, true> $tags */
    private static function endsWithTag(array $tags, string $throw): bool
    {
        foreach (array_keys($tags) as $tag) {
            if (str_ends_with((string) $tag, $throw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first word of each `@throws` tag that has text.
     *
     * @return array<string, true>
     */
    public static function fromDocblock(string $docblock, int $base): array
    {
        $tags = [];
        foreach (DocComment::tags($docblock, $base) as $tag) {
            if ($tag['name'] === '@throws' && $tag['content'] !== null) {
                $space = strpos($tag['content'], ' ');
                $tags[$space === false ? $tag['content'] : substr($tag['content'], 0, $space)] = true;
            }
        }

        return $tags;
    }
}
