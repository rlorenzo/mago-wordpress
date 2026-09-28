<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function array_fill_keys;
use function count;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function strtolower;

/**
 * A per-file text screen that decides whether a rule can match at all.
 *
 * A gate is correct only for a rule whose every match puts a known piece of
 * text in the source, so a rule that reports a missing thing cannot use
 * one. The result is cached for the worker's current file, because the
 * rules see the nodes of each file in sequence. The cache is keyed by
 * contents, so a file re-analyzed after an edit is screened again.
 *
 * Word gates share one lowercase word set per file, so each costs a few
 * hash lookups instead of a regex over the whole file.
 *
 * @internal
 */
final class FileGate
{
    /** @var null|WeakMap<SourceFile, array<string, true>> */
    private static ?WeakMap $wordSets = null;

    private ?string $contents = null;

    private bool $passes = true;

    /**
     * @param string|list<string> $pattern Regexes that pass a file when one matches.
     * @param array<string, true> $words Lowercase words that pass a file containing one as a whole word.
     */
    public function __construct(
        private readonly string|array $pattern,
        private readonly array $words = [],
    ) {}

    /**
     * A gate that passes a file mentioning any of the words, case-insensitively
     * and as a whole word, or matching $pattern.
     *
     * @param list<string> $words
     */
    public static function forWords(array $words, ?string $pattern = null): self
    {
        $plain = [];
        $other = [];
        foreach ($words as $word) {
            if (preg_match('/^\w+$/', $word) === 1) {
                $plain[] = strtolower($word);
                continue;
            }

            $other[] = preg_quote($word, delimiter: '/');
        }

        $patterns = $pattern === null ? [] : [$pattern];
        // A word with a non-word character is not one entry of the word set.
        if ($other !== []) {
            $alternation = implode('|', $other);
            $patterns[] = "/(?<!\\w)(?:{$alternation})(?!\\w)/i";
        }

        return new self($patterns, array_fill_keys($plain, value: true));
    }

    public function passes(SourceFile $file): bool
    {
        if ($this->contents === $file->contents) {
            return $this->passes;
        }

        $this->contents = $file->contents;

        return $this->passes = $this->hasWord($file) || $this->matchesPattern($file->contents);
    }

    private function matchesPattern(string $contents): bool
    {
        foreach ((array) $this->pattern as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasWord(SourceFile $file): bool
    {
        if ($this->words === []) {
            return false;
        }

        $present = self::wordsIn($file);
        [$small, $large] = count($present) < count($this->words) ? [$present, $this->words] : [$this->words, $present];
        foreach ($small as $word => $_) {
            if (($large[$word] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private static function wordsIn(SourceFile $file): array
    {
        self::$wordSets ??= new WeakMap();
        $words = self::$wordSets[$file] ?? null;
        if ($words === null) {
            $matches = [];
            preg_match_all('/\w+/', strtolower($file->contents), $matches);
            $words = array_fill_keys($matches[0], value: true);
            self::$wordSets[$file] = $words;
        }

        return $words;
    }
}
