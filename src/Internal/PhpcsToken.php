<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

/**
 * One token as phpcs 3 sees it; see PhpcsTokenStream.
 *
 * @internal
 */
final class PhpcsToken
{
    /** Parentheses that enclose the token. */
    public int $parens = 0;

    /** On `(`, `[`, `{` and `#[`: the matching closer. */
    public ?int $closer = null;

    /** On `)`, `]` and `}`: the matching opener. */
    public ?int $opener = null;

    /** On `function`, `class`, `match`, `try` and similar keywords: their `{`. */
    public ?int $scopeOpener = null;

    /** On the same keywords (and `fn`): the end of their scope. */
    public ?int $scopeCloser = null;

    /** On a heredoc line: whether it interpolates. */
    public bool $embed = false;

    /**
     * @param string $code the phpcs token name, e.g. `T_STRING_CONCAT` or `T_INLINE_THEN`
     * @param int $pos byte offset in the file
     */
    public function __construct(
        public string $code,
        public readonly string $content,
        public readonly int $pos,
        public readonly int $line,
    ) {}
}
