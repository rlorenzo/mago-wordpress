<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function ltrim;
use function preg_split;
use function rtrim;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use function usort;

use const PREG_SPLIT_OFFSET_CAPTURE;

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
    public static function tokens(SourceFile $file): array
    {
        return FileCache::remember($file, 'phpcs-comments', static fn(): array => self::build($file));
    }

    /** @return list<array{type: string, text: string, line: int, span: Span, adjacent: bool}> */
    private static function build(SourceFile $file): array
    {
        $trivia = $file->getTrivia();
        usort($trivia, static fn($a, $b): int => $a->span->start <=> $b->span->start);
        $tokens = [];
        $end = null;
        foreach ($trivia as $comment) {
            $start = $comment->span->start;
            $adjacent = $end !== null && trim(substr($file->contents, $end, $start - $end)) === '';
            $end = $comment->span->end;
            $line = LineIndex::of($file, $start);
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

            /** @var list<array{string, int}> $parts */
            $parts = preg_split('/\r\n|\r|\n/', $text, -1, PREG_SPLIT_OFFSET_CAPTURE) ?: [[$text, 0]];
            foreach ($parts as $index => [$part, $partOffset]) {
                $tokens[] = [
                    'type' => self::isInstruction($part) ? 'phpcs' : 'comment',
                    'text' => $part,
                    'line' => $line + $index,
                    'span' => new Span($start + $partOffset, $start + $partOffset + strlen($part)),
                    'adjacent' => $index > 0 || $adjacent,
                ];
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
