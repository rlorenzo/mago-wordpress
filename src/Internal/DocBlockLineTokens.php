<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use function preg_match;
use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The phpcs tokens of one docblock line (see {@see DocBlockTokens}).
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class DocBlockLineTokens
{
    /** @var list<array{code: string, text: string, offset: int, line: int}> */
    private array $tokens = [];

    private int $cursor = 0;

    private function __construct(
        private readonly string $line,
        private readonly int $offset,
        private readonly int $number,
    ) {}

    /**
     * @return list<array{code: string, text: string, offset: int, line: int}>
     */
    public static function of(string $line, bool $first, bool $last, int $offset, int $number): array
    {
        $eol = '';
        $match = [];
        if (preg_match('~\r?\n$~', $line, $match) === 1) {
            $eol = $match[0];
            $line = substr($line, 0, -strlen($eol));
        }

        $tokens = new self($line, $offset, $number);
        $tokens->start($first);
        $tokens->body($last);
        if ($eol !== '') {
            $tokens->push('ws', $eol);
        }

        return $tokens->tokens;
    }

    private function start(bool $first): void
    {
        if ($first) {
            // phpcs takes every star after `/**` into the opening tag (`/*****` is a docblock too).
            $match = [];
            $extra = preg_match('~^\*+(?=[^*/]|$)~', substr($this->line, $this->cursor + 3), $match) === 1
                ? $match[0]
                : '';
            $this->push('open', '/**' . $extra);
            $this->space();

            return;
        }

        $this->space();
        $rest = substr($this->line, $this->cursor);
        $match = [];
        if (preg_match('~^\*+/~', $rest, $match) === 1) {
            $this->push('close', $match[0]);
        } elseif (str_starts_with($rest, '*')) {
            $this->push('star', '*');
        }

        $this->space();
    }

    private function body(bool $last): void
    {
        $start = $this->cursor;
        $body = substr($this->line, $start);
        $closes = $last && str_ends_with($body, '*/');
        if ($closes) {
            $body = substr($body, 0, -2);
        }

        // Trailing space stays in the string in front of a closing tag and is its own token elsewhere.
        $text = $closes ? $body : rtrim($body);
        $match = [];
        if ($text !== '' && preg_match('~^@[^\s{]+~', $text, $match) === 1) {
            $this->push('tag', $match[0]);
            $this->space();
            $text = substr($text, $this->cursor - $start);
        }

        if ($text !== '') {
            // phpcs turns a `phpcs:ignore`-style line into a token of its own, not a description.
            $this->push(preg_match('~^phpcs:~i', $text) === 1 ? 'phpcs' : 'string', $text);
        }

        if ($closes) {
            $this->push('close', '*/');
        } elseif (substr($this->line, $this->cursor) !== '') {
            $this->push('ws', substr($this->line, $this->cursor));
        }
    }

    private function space(): void
    {
        $match = [];
        if (preg_match('~^[ \t]+~', substr($this->line, $this->cursor), $match) === 1) {
            $this->push('ws', $match[0]);
        }
    }

    private function push(string $code, string $text): void
    {
        $this->tokens[] = [
            'code' => $code,
            'text' => $text,
            'offset' => $this->offset + $this->cursor,
            'line' => $this->number,
        ];
        $this->cursor += strlen($text);
    }
}
