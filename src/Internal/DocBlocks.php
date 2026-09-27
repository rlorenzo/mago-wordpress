<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;
use function strspn;
use function substr;

/**
 * Docblock helpers.
 *
 * @internal
 */
final class DocBlocks
{
    private function __construct() {}

    /**
     * Offsets of the first token after each `@deprecated` docblock, whitespace skipped.
     *
     * @return array<int, true>
     */
    public static function deprecatedStarts(SourceFile $file): array
    {
        $starts = [];
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $text = substr($file->contents, $trivia->span->start, $trivia->span->length());
            // PHPCS only tokenizes a tag at the start of a docblock line.
            if (preg_match('~^[ \t]*(?:/\*\*|\*)?[ \t]*@deprecated(?:\s|$)~m', $text) !== 1) {
                continue;
            }

            $end = $trivia->span->end;
            $starts[$end + strspn($file->contents, characters: " \t\r\n", offset: $end)] = true;
        }

        return $starts;
    }
}
