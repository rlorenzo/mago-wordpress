<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function max;
use function preg_match;
use function substr;
use function trim;

/**
 * What stands before a block comment: the line of the previous token, and whether that is a `{`
 * or the opening tag, after which no blank line is wanted.
 *
 * @internal
 */
final class BlockCommentContext
{
    private function __construct() {}

    /**
     * @param list<array{type: string, text: string, line: int, span: \Mago\Sdk\Span, adjacent: bool}> $tokens
     *
     * @return array{line: int, opener: bool}
     */
    public static function before(SourceFile $file, array $tokens, int $i): array
    {
        if ($i > 0 && $tokens[$i]['adjacent']) {
            $previous = $tokens[$i - 1];
            $line = $previous['type'] === 'doc' ? LineIndex::of($file, $previous['span']->end - 1) : $previous['line'];

            return ['line' => $line, 'opener' => false];
        }

        $text = $file->contents;
        $position = $tokens[$i]['span']->start;
        while ($position > 0 && trim($text[$position - 1]) === '') {
            --$position;
        }

        if ($position === 0) {
            return ['line' => 0, 'opener' => false];
        }

        $opener =
            $text[$position - 1] === '{'
            || preg_match('~<\?(?:php|=)?$~i', substr($text, max(0, $position - 5), 5)) === 1
            || ScopeColon::at($text, $position - 1);

        return ['line' => LineIndex::of($file, $position - 1), 'opener' => $opener];
    }

    /** The whitespace in front of the comment on its line (the indent a fixer reuses). */
    public static function indent(string $text, int $start): string
    {
        $from = $start;
        while ($from > 0 && ($text[$from - 1] === ' ' || $text[$from - 1] === "\t")) {
            --$from;
        }

        return substr($text, $from, $start - $from);
    }

    /** The line break the file uses. */
    public static function eol(string $text): string
    {
        $match = [];

        return preg_match('/\r\n?|\n/', $text, $match) === 1 ? $match[0] : "\n";
    }
}
