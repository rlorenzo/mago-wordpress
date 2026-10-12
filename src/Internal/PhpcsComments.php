<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function explode;
use function ltrim;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function substr_count;
use function trim;
use function usort;

/**
 * A file's comments as the phpcs tokenizer makes them, for the sniffs written over comment tokens.
 *
 * @internal
 */
final class PhpcsComments
{
    private function __construct() {}

    /**
     * The file's comments as phpcs tokenizes them: a block comment is one token per line; a
     * `phpcs:` instruction is `phpcs`; a docblock is `doc`. `adjacent` is whether only
     * whitespace separates the token from the one before.
     *
     * @return list<array{type: string, text: string, line: int, span: Span, adjacent: bool}>
     */
    public static function tokens(SourceFile $file, string $eol): array
    {
        $trivia = $file->getTrivia();
        usort($trivia, static fn($a, $b): int => $a->span->start <=> $b->span->start);
        $tokens = [];
        $end = null;
        foreach ($trivia as $comment) {
            $start = $comment->span->start;
            $adjacent = $end !== null && trim(substr($file->contents, $end, $start - $end)) === '';
            $end = $comment->span->end;
            $line = substr_count($file->contents, "\n", 0, $start) + 1;
            $text = $file->getText($comment->span);
            if ($comment->kind === TriviaKind::DocBlockComment) {
                $tokens[] = [
                    'type' => 'doc',
                    'text' => $text,
                    'line' => $line,
                    'span' => $comment->span,
                    'adjacent' => $adjacent,
                ];
                continue;
            }

            $offset = $start;
            foreach (explode($eol, $text) as $index => $part) {
                $tokens[] = [
                    'type' => self::isInstruction($part) ? 'phpcs' : 'comment',
                    'text' => $part,
                    'line' => $line + $index,
                    'span' => new Span($offset, $offset + strlen($part)),
                    'adjacent' => $index > 0 || $adjacent,
                ];
                $offset += strlen($part) + strlen($eol);
            }
        }

        return $tokens;
    }

    /** A comment phpcs turns into a T_PHPCS_* token. */
    public static function isInstruction(string $text): bool
    {
        $text = strtolower(rtrim(ltrim($text, " \t/*#"), " */\t\r\n"));
        $text = str_starts_with($text, '@phpcs:') ? substr($text, 1) : $text;
        foreach (['phpcs:set', 'phpcs:ignorefile', 'phpcs:disable', 'phpcs:enable', 'phpcs:ignore'] as $prefix) {
            if (str_starts_with($text, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
