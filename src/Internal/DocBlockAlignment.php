<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function count;
use function max;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * The checks of `Squiz.Commenting.DocCommentAlignment` over a docblock's phpcs tokens: the stars
 * line up one column in from the opener, and a star is followed by a space.
 *
 * @internal
 */
final class DocBlockAlignment
{
    private function __construct() {}

    /**
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     *
     * @return list<array{string, Span, string, null|TextEdit}> message, span, sniff code and fix
     */
    public static function findings(array $t, string $source): array
    {
        $findings = [];
        $required = DocBlockScan::visualColumn($t[0], $source) + 1;
        $count = count($t);
        for ($i = 1; $i < $count; ++$i) {
            $token = $t[$i];
            if ($token['code'] !== 'star' && $token['code'] !== 'close') {
                continue;
            }

            if ($token['code'] === 'close' && !self::firstOnLine($t, $i)) {
                continue;
            }

            $column = DocBlockScan::visualColumn($token, $source);
            if ($column !== $required) {
                $findings[] = [
                    sprintf(
                        'Expected %s space%s before asterisk; %s found',
                        $required - 1,
                        ($required - 1) === 1 ? '' : 's',
                        $column - 1,
                    ),
                    DocBlockScan::span($token),
                    'SpaceBeforeStar',
                    self::padding($t, $i, $column, str_repeat(' ', max(0, $required - 1))),
                ];
            }

            if ($token['code'] === 'star' && self::textFollows($t, $i) && $t[$i + 1]['code'] !== 'ws') {
                $findings[] = [
                    'Expected 1 space after asterisk; 0 found',
                    DocBlockScan::span($token),
                    'NoSpaceAfterStar',
                    TextEdit::insert($token['offset'] + 1, ' '),
                ];
            }
        }

        return $findings;
    }

    /**
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    private static function firstOnLine(array $t, int $i): bool
    {
        $prev = DocBlockScan::previous($t, $i - 1, 0, ['ws'], match: false);

        return $prev === -1 || $t[$prev]['line'] !== $t[$i]['line'];
    }

    /**
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    private static function textFollows(array $t, int $i): bool
    {
        return isset($t[$i + 2]) && $t[$i + 2]['line'] === $t[$i]['line'];
    }

    /**
     * @param list<array{code: string, text: string, offset: int, line: int}> $t
     */
    private static function padding(array $t, int $i, int $column, string $padding): TextEdit
    {
        if ($column === 1) {
            return TextEdit::insert($t[$i]['offset'], $padding);
        }

        $before = $t[$i - 1];

        return TextEdit::replace(new Span($before['offset'], $before['offset'] + strlen($before['text'])), $padding);
    }
}
