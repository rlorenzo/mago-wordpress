<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function count;
use function in_array;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function strspn;
use function substr;

/**
 * Joining `echo`, `print`, `return` or `throw` to an argument that `mago format` broke onto the next
 * line, one level deeper, leaves the rest of the statement one level too deep. phpcbf's later passes
 * re-indent it; these are the edits that take that level off the statement's later lines, which lines
 * inside a multi-line string or heredoc keep as they are.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class JoinedStatement
{
    private const KEYWORDS = ['T_ECHO', 'T_PRINT', 'T_RETURN', 'T_THROW'];

    private function __construct() {}

    /**
     * @param int $keyword offset of the keyword
     * @param int $next offset of the token the keyword is joined to
     *
     * @return list<TextEdit>
     */
    public static function dedent(PhpcsTokens $tokens, string $text, string $code, int $keyword, int $next): array
    {
        $index = $tokens->indexAt($next);
        $end = $index === null || !in_array($code, self::KEYWORDS, strict: true)
            ? null
            : self::statementEnd($tokens, $index);
        $remove = self::extra(self::indentOf($text, $keyword), self::indentOf($text, $next));

        return $end === null || $remove === '' ? [] : self::strip($tokens, $text, $next, $end, $remove);
    }

    /** What the line of the joined token is indented by beyond the line of the keyword. */
    private static function extra(string $keywordIndent, string $nextIndent): string
    {
        return str_starts_with($nextIndent, $keywordIndent) ? substr($nextIndent, strlen($keywordIndent)) : '';
    }

    /**
     * @return list<TextEdit>
     */
    private static function strip(PhpcsTokens $tokens, string $text, int $start, int $end, string $remove): array
    {
        $protected = MultilineTokens::within($tokens, $start, $end);
        $edits = [];
        $at = strpos($text, "\n", $start);
        while ($at !== false && $at < $end) {
            $lineStart = $at + 1;
            $at = strpos($text, "\n", $lineStart);
            if (
                $lineStart < $end
                && MultilineTokens::isCode($protected, $lineStart)
                && str_starts_with(substr($text, $lineStart, strlen($remove)), $remove)
            ) {
                $edits[] = TextEdit::delete(new Span($lineStart, $lineStart + strlen($remove)));
            }
        }

        return $edits;
    }

    /** The whitespace at the start of the line that holds $offset. */
    private static function indentOf(string $text, int $offset): string
    {
        $newline = Strings::lastNewlineBefore($text, $offset);
        $start = $newline === false ? 0 : $newline + 1;
        $line = substr($text, $start, $offset - $start);

        return substr($line, 0, strspn($line, " \t"));
    }

    /** The offset of the `;` or closing tag that ends the statement holding token $index. */
    private static function statementEnd(PhpcsTokens $tokens, int $index): ?int
    {
        for ($i = $index, $count = count($tokens->codes); $i < $count; ++$i) {
            $closer = $tokens->closer($i);
            if ($closer !== null && $closer > $i) {
                $i = $closer;

                continue;
            }

            if ($tokens->code($i) === ';' || $tokens->code($i) === 'T_CLOSE_TAG') {
                return $tokens->pos($i);
            }
        }

        return null;
    }
}
