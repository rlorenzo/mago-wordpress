<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\Layout;

/**
 * One token as phpcs 3 gives it to a sniff that reads layout (`line`, `column`, scopes, conditions); see
 * {@see PhpcsLayout}. Properties follow the keys of the phpcs token array.
 *
 * @internal
 * @mago-expect lint:too-many-properties
 */
final class LayoutToken
{
    /** The phpcs token name, e.g. `T_OPEN_CURLY_BRACKET`. */
    public string $code;

    /** The text; tabs are replaced by spaces in the tokens phpcs replaces them in, as with a tab width. */
    public string $content;

    /** The text as written, when tabs were replaced. */
    public ?string $origContent = null;

    /** Byte offset in the file. */
    public int $pos = 0;

    public int $line = 1;

    public int $column = 1;

    /** Width in columns, without the line break. */
    public int $length = 0;

    public int $level = 0;

    /** @var array<int, int|string> scope-owning token => its code, outermost first */
    public array $conditions = [];

    public ?int $scopeCondition = null;

    public ?int $scopeOpener = null;

    public ?int $scopeCloser = null;

    public ?int $parenthesisOpener = null;

    public ?int $parenthesisCloser = null;

    public ?int $parenthesisOwner = null;

    public ?int $bracketOpener = null;

    public ?int $bracketCloser = null;

    public ?int $commentCloser = null;

    /** @var array<int, int> opening parenthesis => its closer, for the parentheses around the token */
    public array $nestedParenthesis = [];

    /** On a `phpcs:set` comment: the sniff, property and value. */
    public ?string $sniffCode = null;

    public ?string $sniffProperty = null;

    public ?string $sniffPropertyValue = null;

    public function __construct(string $code, string $content)
    {
        $this->code = $code;
        $this->content = $content;
    }
}
