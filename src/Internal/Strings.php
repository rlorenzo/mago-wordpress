<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function chr;
use function function_exists;
use function hexdec;
use function ltrim;
use function mb_strtolower;
use function octdec;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;
use function strtr;
use function substr;

/**
 * Decodes the text of quoted, heredoc and nowdoc strings, and other string helpers.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class Strings
{
    private function __construct() {}

    /**
     * Decodes the source text of a PHP string literal: single- or double-quoted
     * (with an optional `b` prefix), heredoc, or nowdoc. Returns NULL for text
     * that is not a string literal or that interpolates a variable.
     */
    public static function unquote(string $raw): ?string
    {
        // A binary string literal carries a `b` prefix before its quote.
        $literal = ltrim($raw, characters: 'bB');
        $quote = $literal[0] ?? '';
        if (($quote === "'" || $quote === '"') && strlen($literal) >= 2 && str_ends_with($literal, $quote)) {
            $body = substr($literal, offset: 1, length: -1);

            return $quote === "'" ? strtr($body, ["\\'" => "'", '\\\\' => '\\']) : self::decodeStatic($body);
        }

        $matches = [];
        $document = '/^<<<[ \t]*(["\']?)([A-Za-z_\x80-\xFF][A-Za-z0-9_\x80-\xFF]*)\1\r?\n(?:(.*)\r?\n)?([ \t]*)\2$/s';
        if (preg_match($document, $literal, $matches) !== 1) {
            return null;
        }

        // Flexible heredoc/nowdoc syntax: the closing marker's indentation comes off every line.
        $body = $matches[3] ?? '';
        if ($matches[4] !== '') {
            $body = (string) preg_replace(
                '/^' . preg_quote($matches[4], delimiter: '/') . '/m',
                replacement: '',
                subject: $body,
            );
        }

        return $matches[1] === "'" ? $body : self::decodeStatic($body);
    }

    /**
     * Decodes a double-quoted/heredoc body, or returns NULL when it interpolates.
     */
    private static function decodeStatic(string $body): ?string
    {
        // Escapes are matched first so that `\$name` does not count as interpolation.
        $matches = [];
        preg_match_all('/\\\\.|\$[A-Za-z_\x80-\xFF{]|\{\$/s', $body, $matches);
        foreach ($matches[0] as $match) {
            if ($match[0] !== '\\') {
                return null;
            }
        }

        return self::decode($body);
    }

    /**
     * Walks the parts of an interpolated string.
     *
     * Yields each part node with its decoded text, or with NULL for a
     * dynamic part (a variable or `{$expr}`).
     *
     * @return iterable<Node, ?string>
     */
    public static function compositeParts(SourceFile $file, Node $composite): iterable
    {
        $string = $file->getChildren($composite)[0] ?? $composite;
        $nowdoc = str_starts_with($file->getText($string), "<<<'");
        foreach ($file->getChildren($string) as $part) {
            $part = $file->getChildren($part)[0] ?? $part;
            if ($part->kind !== NodeKind::LiteralStringPart) {
                yield $part => null;
                continue;
            }

            $text = $file->getText($part);
            yield $part => $nowdoc ? $text : self::decode($text);
        }
    }

    /**
     * Decodes PHP's double-quoted/heredoc escape sequences: control
     * characters, octal and hex byte escapes, and `\u{...}` code points.
     * Unlike stripcslashes(), an unknown escape keeps its backslash.
     */
    public static function decode(string $text): string
    {
        $decoded = preg_replace_callback(
            '/\\\\(?:u\{([0-9A-Fa-f]+)\}|x([0-9A-Fa-f]{1,2})|([0-7]{1,3})|(.))/',
            static function (array $match): string {
                if ($match[1] !== '') {
                    return self::codepointToUtf8((int) hexdec($match[1]));
                }

                if ($match[2] !== '') {
                    return chr((int) hexdec($match[2]));
                }

                if ($match[3] !== '') {
                    return chr((int) octdec($match[3]));
                }

                return match ($match[4]) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'v' => "\x0B",
                    'e' => "\x1B",
                    'f' => "\x0C",
                    '\\', '$', '"' => $match[4],
                    default => '\\' . $match[4],
                };
            },
            $text,
        );

        return $decoded ?? $text;
    }

    /**
     * WPCS `SnakeCaseHelper::get_suggestion()`.
     */
    public static function snakeCase(string $name): string
    {
        $suggested = (string) preg_replace('`(?<!_|^)([A-Z])`', replacement: '_$1', subject: $name);

        if (preg_match('`^[a-z0-9_]+$`i', $suggested) === 1) {
            return strtolower($suggested);
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($suggested, encoding: 'UTF-8')
            : strtr($suggested, from: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', to: 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0x80) {
            return chr($codepoint);
        }

        if ($codepoint < 0x800) {
            return chr(0xC0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3F));
        }

        if ($codepoint < 0x1_0000) {
            return (
                chr(0xE0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3F))
                . chr(0x80 | ($codepoint & 0x3F))
            );
        }

        return (
            chr(0xF0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3F))
            . chr(0x80 | (($codepoint >> 6) & 0x3F))
            . chr(0x80 | ($codepoint & 0x3F))
        );
    }
}
