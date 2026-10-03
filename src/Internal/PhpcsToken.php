<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use PhpToken;

/**
 * One token as phpcs 3 sees it; see PhpcsTokenStream. `text`, `pos` (byte offset) and
 * `line` are PHP's. Extending PhpToken lets the stream keep PHP's own token objects
 * instead of copying each one, which halves its memory on a large file.
 *
 * @internal
 */
final class PhpcsToken extends PhpToken
{
    /** The phpcs token name, e.g. `T_STRING_CONCAT` or `T_INLINE_THEN`. */
    public string $code = '';

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

    /** A token phpcs makes that PHP does not, such as one part of a qualified name. */
    public static function of(string $code, string $text, int $pos, int $line): self
    {
        $token = new self(0, $text, $line, $pos);
        $token->code = $code;

        return $token;
    }
}
