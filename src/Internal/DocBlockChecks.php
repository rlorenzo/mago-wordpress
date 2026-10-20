<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function count;
use function max;
use function preg_match;
use function str_repeat;

/**
 * The description and tag checks of `Generic.Commenting.DocComment` over a docblock's phpcs
 * tokens: each finding is a message, span, sniff code and optional fix.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class DocBlockChecks
{
    private function __construct() {}

    /**
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     *
     * @return list<array{string, Span, string, null|TextEdit}>
     */
    public static function descriptions(array $t, int $short, int $close): array
    {
        $findings = [];
        $end = DocBlockScan::shortEnd($t, $short, $close);

        if (preg_match('/^\p{Ll}/u', $t[$short]['text']) === 1) {
            $findings[] = [
                'Doc comment short description must start with a capital letter',
                DocBlockScan::span($t[$short]),
                'ShortNotCapital',
                null,
            ];
        }

        $long = DocBlockScan::next($t, $end + 1, $close - 1);
        if ($long === null || $t[$long]['code'] !== 'string') {
            return $findings;
        }

        if ($t[$long]['line'] !== ($t[$end]['line'] + 2)) {
            $findings[] = [
                'There must be exactly one blank line between descriptions in a doc comment',
                DocBlockScan::span($t[$long]),
                'SpacingBetween',
                DocBlockFixes::betweenFix($t, $end, $long),
            ];
        }

        if (preg_match('/^\p{Ll}/u', $t[$long]['text']) === 1) {
            $findings[] = [
                'Doc comment long description must start with a capital letter',
                DocBlockScan::span($t[$long]),
                'LongNotCapital',
                null,
            ];
        }

        return $findings;
    }

    /**
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     *
     * @return list<array{string, Span, string, null|TextEdit}>
     */
    public static function tags(array $t, int $close, string $source): array
    {
        $findings = [];
        $tags = [];
        foreach ($t as $index => $token) {
            if ($token['code'] === 'tag') {
                $tags[] = $index;
            }
        }

        if ($tags === []) {
            return $findings;
        }

        $indent = str_repeat(' ', max(0, DocBlockScan::column($t[0], $source)));
        $first = $tags[0];
        $before = DocBlockScan::previous($t, $first - 1, 0, ['ws', 'star'], match: false);
        if ($t[$first]['line'] !== ($t[$before]['line'] + 2) && $t[$before]['code'] !== 'open') {
            $findings[] = [
                'There must be exactly one blank line before the tags in a doc comment',
                DocBlockScan::span($t[$first]),
                'SpacingBeforeTags',
                DocBlockFixes::blankLineFix($t, $before, $first, $indent, $source),
            ];
        }

        $groups = DocBlockScan::tagGroups($t, $tags);

        foreach ($groups as $members) {
            $lastTag = $members[count($members) - 1];
            $next = null;
            for ($i = $lastTag + 3; $i < $close; ++$i) {
                if ($t[$i]['code'] === 'tag' || $t[$i]['code'] === 'phpcs') {
                    $next = $i;

                    break;
                }
            }

            if ($next === null) {
                continue;
            }

            $prev = DocBlockScan::previous($t, $next - 1, 0, ['tag', 'string'], match: true);
            if ($t[$next]['line'] !== ($t[$prev]['line'] + 2)) {
                $findings[] = [
                    'There must be a single blank line after a tag group',
                    DocBlockScan::span($t[$lastTag]),
                    'SpacingAfterTagGroup',
                    DocBlockFixes::blankLineFix($t, $prev, $next, $indent, $source),
                ];
            }
        }

        return $findings;
    }
}
