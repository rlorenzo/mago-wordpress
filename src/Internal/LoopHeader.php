<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;

use function array_slice;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * The tokens between a `for`, `while` or do-`while` loop's parentheses, as phpcs sees
 * them. Mago's syntax tree flattens a `for` header into one run of expressions, so the
 * sniffs that count its semicolons are ported over tokens instead.
 *
 * @internal
 */
final class LoopHeader
{
    private function __construct() {}

    /**
     * @return list<PhpToken> with `pos` as an offset into the file; empty when the header
     *         cannot be found
     */
    public static function tokens(SourceFile $file, Node $loop): array
    {
        // The header is before a for/while body, and after a do-while's last keyword (`while`).
        $start = $loop->span->start;
        $end = $loop->span->end;
        foreach ($file->getChildren($loop) as $child) {
            $isDoWhile = $loop->kind === NodeKind::DoWhile;
            $start = $isDoWhile && $child->kind === NodeKind::Keyword ? $child->span->start : $start;
            $end = !$isDoWhile && str_ends_with($child->kind->value, 'Body') ? $child->span->start : $end;
        }

        $prefix = '<?php ';
        $tokens = PhpToken::tokenize($prefix . substr($file->contents, $start, $end - $start));
        $inner = [];
        $depth = 0;
        foreach (array_slice($tokens, offset: 1) as $token) {
            if ($token->text === '(' && $depth++ === 0) {
                continue;
            }

            if ($token->text === ')' && --$depth === 0) {
                return $inner;
            }

            if ($depth > 0) {
                $token->pos += $start - strlen($prefix);
                $inner[] = $token;
            }
        }

        return [];
    }
}
