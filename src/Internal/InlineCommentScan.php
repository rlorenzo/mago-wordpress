<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function trim;

/**
 * What `generic/inline-comment` needs to know about the code around a comment.
 *
 * @internal
 */
final class InlineCommentScan
{
    private function __construct() {}

    /**
     * The token before the comment, past whitespace: its line, and whether it is a `}` (or a `;` or
     * `,` after one), in which case the sniff leaves a trailing comment alone.
     *
     * @param list<array{type: string, text: string, line: int, span: Span, adjacent: bool}> $tokens
     *
     * @return array{line: int, closeCurly: bool}
     */
    public static function before(SourceFile $file, array $tokens, int $i): array
    {
        $text = $file->contents;
        if ($i > 0 && $tokens[$i]['adjacent']) {
            return ['line' => $tokens[$i - 1]['line'], 'closeCurly' => false];
        }

        $p = $tokens[$i]['span']->start;
        while ($p > 0 && trim($text[$p - 1]) === '') {
            --$p;
        }

        if ($p === 0) {
            return ['line' => 0, 'closeCurly' => false];
        }

        $line = LineIndex::of($file, $p - 1);
        $char = $text[$p - 1];
        $close = $char === '}';
        if ($char === ',' || $char === ';') {
            $q = $p - 1;
            while ($q > 0 && trim($text[$q - 1]) === '') {
                --$q;
            }

            $close = $q > 0 && $text[$q - 1] === '}';
        }

        return ['line' => $line, 'closeCurly' => $close];
    }

    /**
     * The offsets of the `}` that close a function, method or closure body.
     *
     * @return array<int, true>
     */
    public static function functionClosers(LintContext $context): array
    {
        $closers = [];
        $text = $context->file->contents;
        foreach ([NodeKind::Function, NodeKind::Method, NodeKind::Closure] as $kind) {
            foreach (NodeIndex::ofKind($context->file, $context->node, $kind) as $node) {
                $end = $node->span->end - 1;
                if ($end >= 0 && $text[$end] === '}') {
                    $closers[$end] = true;
                }
            }
        }

        return $closers;
    }
}
