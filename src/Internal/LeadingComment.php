<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function strlen;
use function strpbrk;
use function strrpos;
use function substr;
use function trim;
use function usort;

/**
 * The comment a declaration owns: the one directly before it, with only whitespace between.
 *
 * @internal
 */
final class LeadingComment
{
    private function __construct() {}

    /**
     * The comment ending just before $start. NULL when there is none, or when it trails code on its
     * own line (it belongs to that code, as in the phpcs comment sniffs).
     *
     * @return null|array{kind: TriviaKind, span: Span}
     */
    public static function before(SourceFile $file, int $start): ?array
    {
        $text = $file->contents;
        $end = $start;
        while ($end > 0 && strpbrk($text[$end - 1], " \t\n\r\v\0") !== false) {
            --$end;
        }

        $found = self::trivia($file)[$end] ?? null;

        if ($found === null) {
            return null;
        }

        if ($found->kind !== TriviaKind::DocBlockComment) {
            $lineStart = Strings::lastNewlineBefore($text, $found->span->start);
            $from = $lineStart === false ? 0 : $lineStart + 1;
            if (trim(substr($text, $from, $found->span->start - $from)) !== '') {
                return null;
            }
        }

        return ['kind' => $found->kind, 'span' => $found->span];
    }

    /**
     * The file's comments by the offset they end at.
     *
     * @return array<int, Trivia>
     */
    private static function trivia(SourceFile $file): array
    {
        return FileCache::remember($file, 'leading-comment-trivia', static function () use ($file): array {
            $trivia = $file->getTrivia();
            usort($trivia, static fn($a, $b): int => $a->span->start <=> $b->span->start);
            $byEnd = [];
            foreach ($trivia as $piece) {
                $byEnd[$piece->span->end] ??= $piece;
            }

            return $byEnd;
        });
    }
}
