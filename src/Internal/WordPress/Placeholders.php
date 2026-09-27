<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

use function preg_replace;

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

    private function __construct() {}

    /**
     * Removes the quoted or `CONCAT()` operand of each `LIKE` that is not
     * a bare `%s`. WPCS: its SQL wildcards, as in `LIKE '%foo%'`, are not
     * placeholders to validate, though `wpdb::prepare()` still counts them.
     */
    public static function withoutLikeOperands(string $text): string
    {
        return (string) preg_replace(
            '`(\s+LIKE\s*)(?:(["\'])(?!%s(?:\2|$)).*?(\2|$)|(concat\()(?![^\)]*%s[^\)]*\))[^\)]*(\)))`i',
            replacement: '$1$2$3$4$5',
            subject: $text,
        );
    }
}
