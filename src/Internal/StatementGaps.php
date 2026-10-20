<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function substr_count;

/**
 * Whether a statement has a blank or comment-only line inside it (outside an array), which ends
 * the block of adjacent assignments in `Generic.Formatting.MultipleStatementAlignment`.
 *
 * @internal
 */
final class StatementGaps
{
    private function __construct() {}

    public static function splits(SourceFile $file, Node $statement): bool
    {
        $tokens = PhpcsTokens::of($file);
        $index = $tokens->indexAt($statement->span->start);
        if ($index === null) {
            return false;
        }

        $arrayEnd = null;
        $last = null;
        for ($i = $index; $tokens->code($i) !== null && $tokens->pos($i) < $statement->span->end; ++$i) {
            $line = LineIndex::of($file, $tokens->pos($i));
            // The array's own closing token ends it before the blank-line test, as in the sniff.
            if ($i === $arrayEnd) {
                $arrayEnd = null;
            }

            if ($last !== null && ($line - $last) > 1 && $arrayEnd === null) {
                return true;
            }

            $arrayEnd ??= self::arrayEnd($tokens, $i);
            $last = self::endLine($file, $tokens, $i, $line);
            $closer = $tokens->code($i) === '{' ? $tokens->closer($i) : null;
            if ($closer !== null) {
                // A body is its own block: skipped, and the closing brace counts as the last code.
                $i = $closer;
                $last = LineIndex::of($file, $tokens->pos($closer));
            }
        }

        return false;
    }

    /** phpcs splits a multi-line string into one token per line, so a string ends on its last line. */
    private static function endLine(SourceFile $file, PhpcsTokens $tokens, int $i, int $line): int
    {
        if (!in_array(
            $tokens->code($i),
            ['T_CONSTANT_ENCAPSED_STRING', 'T_DOUBLE_QUOTED_STRING', 'T_ENCAPSED_AND_WHITESPACE', 'T_HEREDOC'],
            strict: true,
        )) {
            return $line;
        }

        return $line + substr_count($tokens->content($i), "\n");
    }

    private static function arrayEnd(PhpcsTokens $tokens, int $i): ?int
    {
        $code = $tokens->code($i);
        if ($code === 'T_ARRAY') {
            return $tokens->closer($i + 1);
        }

        return $code === 'T_OPEN_SHORT_ARRAY' ? $tokens->closer($i) : null;
    }
}
