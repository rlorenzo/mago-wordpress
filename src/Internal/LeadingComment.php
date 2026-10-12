<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

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
        while ($end > 0 && trim($text[$end - 1]) === '') {
            --$end;
        }

        $found = null;
        foreach (self::trivia($file) as $trivia) {
            if ($trivia->span->end === $end) {
                $found = $trivia;

                break;
            }

            if ($trivia->span->end > $end) {
                break;
            }
        }

        if ($found === null) {
            return null;
        }

        if ($found->kind !== TriviaKind::DocBlockComment) {
            $lineStart = strrpos(substr($text, 0, $found->span->start), "\n");
            $from = $lineStart === false ? 0 : $lineStart + 1;
            if (trim(substr($text, $from, $found->span->start - $from)) !== '') {
                return null;
            }
        }

        return ['kind' => $found->kind, 'span' => $found->span];
    }

    /** @return list<Trivia> */
    private static function trivia(SourceFile $file): array
    {
        return FileCache::remember($file, 'leading-comment-trivia', static function () use ($file): array {
            $trivia = $file->getTrivia();
            usort($trivia, static fn($a, $b): int => $a->span->start <=> $b->span->start);

            return $trivia;
        });
    }
}
