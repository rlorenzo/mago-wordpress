<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Syntax\SourceFile;
use PhpToken;

use function strlen;
use function strpos;
use function strspn;

use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_END_HEREDOC;
use const T_INLINE_HTML;
use const T_WHITESPACE;
use const T_YIELD_FROM;

/**
 * The leading spaces and tabs of the lines phpcs gives a token at column 1 that the indentation
 * sniffs read (`T_WHITESPACE`, `T_INLINE_HTML`, a doc comment's whitespace, the second and later lines
 * of a block comment, a heredoc or nowdoc closer), for `Generic.WhiteSpace.DisallowSpaceIndent` and
 * `Universal.WhiteSpace.PrecisionAlignment`. Lines inside strings and heredoc bodies are not listed.
 * A line without indentation is not listed either. Built once per file.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 */
final class LineIndents
{
    public const WHITESPACE = 0;

    public const DOC = 1;

    public const COMMENT = 2;

    public const HTML = 3;

    public const CLOSER = 4;

    /** The tab width the WordPress ruleset sets (`<arg name="tab-width" value="4"/>`). */
    public const TAB_WIDTH = 4;

    /**
     * @return list<array{start: int, length: int, kind: int, blank: bool, star: bool, tail: int}> `length` is
     *         the byte length of the indentation; `blank` that nothing but the line break follows it; `star`
     *         that a `*` does; `tail` the rest of a closer's token (the marker)
     */
    public static function of(SourceFile $file): array
    {
        return FileCache::remember($file, 'line-indents', static fn(): array => self::build($file->contents));
    }

    /** The display width of $whitespace starting at column 0, tabs going to the next tab stop. */
    public static function width(string $whitespace): int
    {
        $width = 0;
        for ($i = 0, $length = strlen($whitespace); $i < $length; ++$i) {
            $width += $whitespace[$i] === "\t" ? self::TAB_WIDTH - ($width % self::TAB_WIDTH) : 1;
        }

        return $width;
    }

    /**
     * @return list<array{start: int, length: int, kind: int, blank: bool, star: bool, tail: int}>
     */
    private static function build(string $text): array
    {
        $found = [];
        foreach (PhpToken::tokenize($text) as $token) {
            $id = $token->id;
            if ($id === T_WHITESPACE) {
                self::lines($found, $text, $token, self::WHITESPACE, firstLineToo: true);
            } elseif ($id === T_INLINE_HTML) {
                self::lines($found, $text, $token, self::HTML, firstLineToo: true);
            } elseif ($id === T_DOC_COMMENT) {
                self::lines($found, $text, $token, self::DOC, firstLineToo: false);
            } elseif ($id === T_COMMENT) {
                self::lines($found, $text, $token, self::COMMENT, firstLineToo: false);
            } elseif ($id === T_YIELD_FROM) {
                // `yield\n    from` is one token in PHP; phpcs makes the whitespace in it a token of its own.
                self::lines($found, $text, $token, self::WHITESPACE, firstLineToo: false);
            } elseif ($id === T_END_HEREDOC) {
                $length = strspn($token->text, " \t");
                if ($length > 0) {
                    $found[] = [
                        'start' => $token->pos,
                        'length' => $length,
                        'kind' => self::CLOSER,
                        'blank' => false,
                        'star' => false,
                        'tail' => strlen($token->text) - $length,
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * @param list<array{start: int, length: int, kind: int, blank: bool, star: bool, tail: int}> $found
     */
    private static function lines(array &$found, string $text, PhpToken $token, int $kind, bool $firstLineToo): void
    {
        $content = $token->text;
        $position = 0;
        $atLineStart = $firstLineToo && ($token->pos === 0 || $text[$token->pos - 1] === "\n");
        if (!$firstLineToo) {
            // Only the lines after the first are tokens of their own in phpcs.
            $newline = strpos($content, "\n");
            if ($newline === false) {
                return;
            }

            $position = $newline + 1;
            $atLineStart = true;
        }

        $end = strlen($content);
        while ($position < $end) {
            $newline = strpos($content, "\n", $position);
            if ($atLineStart) {
                $length = strspn($content, " \t", $position);
                if ($length > 0) {
                    $rest = $position + $length;
                    $found[] = [
                        'start' => $token->pos + $position,
                        'length' => $length,
                        'kind' => $kind,
                        'blank' =>
                            $newline !== false
                                && ($newline === $rest || $newline === ($rest + 1) && $content[$rest] === "\r"),
                        'star' => $rest < $end && $content[$rest] === '*',
                        'tail' => 0,
                    ];
                }
            }

            if ($newline === false) {
                return;
            }

            $position = $newline + 1;
            $atLineStart = true;
        }
    }
}
