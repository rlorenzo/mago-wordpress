<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function implode;
use function ltrim;
use function preg_match;
use function preg_replace;
use function strlen;
use function trim;

/**
 * The checks of `Squiz.Commenting.BlockComment` that WordPress-Docs keeps, over the lines of one
 * block comment: each finding is a message, span, sniff code and optional fix.
 *
 * @internal
 */
final class BlockCommentChecks
{
    private function __construct() {}

    /**
     * @param list<array{type: string, text: string, line: int, span: Span, adjacent: bool}> $tokens
     * @param non-empty-list<int> $run
     *
     * @return list<array{string, Span, string, null|TextEdit}>
     */
    public static function findings(SourceFile $file, array $tokens, int $first, array $run, string $eol): array
    {
        $start = $tokens[$first];
        $texts = [];
        foreach ($run as $index) {
            $texts[] = $tokens[$index]['text'];
        }

        if (trim(implode('', $texts), "/* \t") === '') {
            $last = $tokens[$run[count($run) - 1]];

            return [[
                'Empty block comment not allowed',
                $start['span'],
                'Empty',
                TextEdit::delete(new Span($start['span']->start, $last['span']->end)),
            ]];
        }

        if (count($run) === 1) {
            return [];
        }

        $content = trim($start['text']);
        if ($content !== '/*' && $content !== '/**') {
            return [self::noNewLine($file, $start, $eol)];
        }

        $findings = self::lines($tokens, $run, $eol);
        $before = BlockCommentContext::before($file, $tokens, $first);
        $gap = $start['line'] - $before['line'];
        if ($before['opener'] && $gap !== 1) {
            $findings[] = ['Empty line not required before block comment', $start['span'], 'HasEmptyLineBefore', null];
        } elseif (!$before['opener'] && $gap < 2) {
            $findings[] = ['Empty line required before block comment', $start['span'], 'NoEmptyLineBefore', null];
        }

        return $findings;
    }

    /**
     * @param array{type: string, text: string, line: int, span: Span, adjacent: bool} $start
     *
     * @return array{string, Span, string, null|TextEdit}
     */
    private static function noNewLine(SourceFile $file, array $start, string $eol): array
    {
        $indent = BlockCommentContext::indent($file->contents, $start['span']->start);
        $comment = preg_replace('/^(\s*\/\*\*?)/', '$1' . $eol . $indent, $start['text'], 1);

        return [
            'Block comment text must start on a new line',
            $start['span'],
            'NoNewLine',
            TextEdit::replace($start['span'], $comment ?? $start['text']),
        ];
    }

    /**
     * @param list<array{type: string, text: string, line: int, span: Span, adjacent: bool}> $tokens
     * @param non-empty-list<int> $run
     *
     * @return list<array{string, Span, string, null|TextEdit}>
     */
    private static function lines(array $tokens, array $run, string $eol): array
    {
        $findings = [];
        $second = $tokens[$run[1]];
        if (trim($second['text']) === '') {
            // The line ends where the next one starts, whatever line ending it has.
            $lineEnd = isset($run[2]) ? $tokens[$run[2]]['span']->start : $second['span']->end + strlen($eol);
            $findings[] = [
                'Empty line not allowed at start of comment',
                $second['span'],
                'HasEmptyLine',
                TextEdit::delete(new Span($second['span']->start, $lineEnd)),
            ];
        } elseif (preg_match('/^\p{Ll}/u', ltrim($second['text'])) === 1) {
            $findings[] = ['Block comments must start with a capital letter', $second['span'], 'NoCapital', null];
        }

        $last = $tokens[$run[count($run) - 1]];
        $closer = ltrim($last['text']);
        if ($closer !== '*/' && $closer !== '**/') {
            $findings[] = ['Comment closer must be on a new line', $last['span'], 'CloserSameLine', null];
        }

        return $findings;
    }
}
