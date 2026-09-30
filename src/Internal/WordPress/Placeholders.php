<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

/**
 * The `$wpdb->prepare()` placeholder grammar of WPCS
 * `WordPress.DB.PreparedSQLPlaceholders`.
 *
 * @internal
 */
final class Placeholders
{
    /**
     * WPCS `PREPARE_PLACEHOLDER_REGEX` between `%` and the type: argument
     * number, sign, padding (`0` or a custom `'x`), alignment, width and
     * precision. A space pads only together with a width. Has no capture
     * groups, so it concatenates into patterns with backreferences.
     */
    public const SPEC = '(?:[0-9]+\\\\?\$)?[+-]?(?:(?:0|\'.)?-?[0-9]*(?:\.(?:[ 0]|\'.)?[0-9]+)?|[ ]?-?[0-9]+(?:\.(?:[ 0]|\'.)?[0-9]+)?)';

    /**
     * WPCS `PREPARE_PLACEHOLDER_REGEX`: a supported placeholder, not the
     * tail of a literal `%%`.
     */
    public const PLACEHOLDER = '(?<![^%]%)%' . self::SPEC . '[dfFsi]';

    /**
     * WPCS `UNSUPPORTED_PLACEHOLDER_REGEX`: a `%` that is neither a literal
     * `%%` nor a supported placeholder, with the text that follows it up to
     * a space or quote. The possessive specifier parts make `%1$ 10.3i`
     * stop at `%1$`.
     */
    public const UNSUPPORTED = '`(?<!%)(%(?!%[^%]|%%[dfFsi])(?:[0-9]+\\\\??\$)?+[+-]?+(?:(?:0|\'.)?+-?+[0-9]*+(?:\.(?:[ 0]|\'.)?[0-9]+)?+|(?:[ ])?+-?+[0-9]++(?:\.(?:[ 0]|\'.)?[0-9]+)?+)(?![dfFsi])(?:[^ \'"]*|$))`';

    /**
     * WPCS: the quoted (group 2) or `CONCAT()` (group 3) operand of a
     * `LIKE` that is not a bare `%s`.
     */
    public const LIKE = '`\s+LIKE\s*(?:(["\'])(?!%s(?:\1|$))(?P<content>.*?)(?:\1|$)|(?:concat\((?![^\)]*%s[^\)]*\))(?P<concat>[^\)]*))\))`i';

    private function __construct() {}
}
