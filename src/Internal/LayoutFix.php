<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;

/**
 * The fixer of phpcs for one changeset over a Layout: `replaceToken()`, `addContent()`,
 * `addContentBefore()` and the newline variants, collected as one edit per touched token.
 *
 * @internal
 */
final class LayoutFix
{
    /** @var array<int, string> token => its content now */
    private array $content = [];

    public function __construct(
        private readonly Layout $layout,
    ) {}

    public function replace(int $i, string $text): void
    {
        $this->content[$i] = $text;
    }

    public function after(int $i, string $text): void
    {
        $this->content[$i] = ($this->content[$i] ?? $this->layout->t[$i]->text) . $text;
    }

    public function before(int $i, string $text): void
    {
        $this->content[$i] = $text . ($this->content[$i] ?? $this->layout->t[$i]->text);
    }

    public function newlineAfter(int $i): void
    {
        $this->after($i, $this->layout->eol);
    }

    public function newlineBefore(int $i): void
    {
        $this->before($i, $this->layout->eol);
    }

    /** @return list<TextEdit> */
    public function edits(): array
    {
        return $this->layout->edits($this->content);
    }
}
