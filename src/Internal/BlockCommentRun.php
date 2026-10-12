<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function count;
use function in_array;
use function str_ends_with;

/**
 * The lines of a block comment as `Squiz.Commenting.BlockComment` gathers them: the first
 * token, then each token (comment or phpcs annotation) on the next line with nothing between,
 * up to the one that ends in the closing tag.
 *
 * @internal
 */
final class BlockCommentRun
{
    private function __construct() {}

    /**
     * @param list<array{type: string, text: string, line: int, span: \Mago\Sdk\Span, adjacent: bool}> $tokens
     *
     * @return non-empty-list<int>
     */
    public static function from(array $tokens, int $first): array
    {
        $run = [$first];
        if (str_ends_with($tokens[$first]['text'], '*/')) {
            return $run;
        }

        $line = $tokens[$first]['line'];
        for ($i = $first + 1; $i < count($tokens); ++$i) {
            $token = $tokens[$i];
            if (
                !$token['adjacent']
                || !in_array($token['type'], ['comment', 'phpcs'], strict: true)
                || ($token['line'] - 1) !== $line
            ) {
                break;
            }

            $line = $token['line'];
            $run[] = $i;
            if (str_ends_with($token['text'], '*/')) {
                break;
            }
        }

        return $run;
    }
}
