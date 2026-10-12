<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;

use function count;
use function str_starts_with;

/**
 * Consecutive `//` comments, which `Squiz.Commenting.InlineComment` treats as one comment.
 *
 * @internal
 */
final class CommentRun
{
    private function __construct() {}

    /**
     * The indexes of the run of `//` comments on consecutive lines that starts at $i, which the
     * sniff treats as one comment.
     *
     * @param list<array{type: string, text: string, line: int, span: Span, adjacent: bool}> $tokens
     *
     * @return non-empty-list<int>
     */
    public static function from(array $tokens, int $i): array
    {
        $run = [$i];
        for ($j = $i + 1; $j < count($tokens); ++$j) {
            $next = $tokens[$j];
            if (
                $next['type'] !== 'comment'
                || !$next['adjacent']
                || $next['line'] !== ($tokens[$j - 1]['line'] + 1)
                || !str_starts_with($next['text'], '//')
            ) {
                break;
            }

            $run[] = $j;
        }

        return $run;
    }
}
