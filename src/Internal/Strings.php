<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function chr;
use function hexdec;
use function octdec;
use function preg_replace_callback;
use function str_starts_with;

/**
 * Decodes the text of double-quoted, heredoc and nowdoc strings.
 *
 * @internal
 */
final class Strings
{
    private function __construct() {}

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
