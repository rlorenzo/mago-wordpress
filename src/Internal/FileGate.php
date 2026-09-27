<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function array_map;
use function implode;
use function preg_match;
use function preg_quote;

/**
 * A per-file text screen that decides whether a rule can match at all.
 *
 * A gate is correct only for a rule whose every match puts a known piece of
 * text in the source, so a rule that reports a missing thing cannot use
 * one. The result is cached for the worker's current file, because the
 * rules see the nodes of each file in sequence. The cache is keyed by
 * contents, so a file re-analyzed after an edit is screened again.
 *
 * @internal
 */
final class FileGate
{
    private ?string $contents = null;

    private bool $passes = true;

    /**
     * @param string $pattern A regex that passes a file when it matches.
     */
    public function __construct(
        private readonly string $pattern,
    ) {}

    /**
     * A gate that passes a file mentioning any of the words, case-insensitively
     * and as a whole word.
     *
     * @param list<string> $words
     */
    public static function forWords(array $words): self
    {
        $alternation = implode('|', array_map(static fn(string $word): string => preg_quote(
            $word,
            delimiter: '/',
        ), $words));

        return new self(pattern: "/(?<!\\w)(?:{$alternation})(?!\\w)/i");
    }

    public function passes(SourceFile $file): bool
    {
        if ($this->contents === $file->contents) {
            return $this->passes;
        }

        $this->contents = $file->contents;

        return $this->passes = preg_match($this->pattern, $file->contents) === 1;
    }
}
