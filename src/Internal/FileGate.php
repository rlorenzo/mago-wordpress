<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function preg_match;

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

    public function passes(SourceFile $file): bool
    {
        if ($this->contents === $file->contents) {
            return $this->passes;
        }

        $this->contents = $file->contents;

        return $this->passes = preg_match($this->pattern, $file->contents) === 1;
    }
}
