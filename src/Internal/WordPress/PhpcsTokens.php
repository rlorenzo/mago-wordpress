<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal\WordPress;

use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\FileCache;

use function array_map;
use function array_pop;
use function array_slice;
use function count;
use function explode;
use function implode;
use function in_array;
use function strlen;
use function substr;

/**
 * PHP's own tokens in the shape phpcs gives them, for the WPCS sniffs that are token walks
 * (`DB.PreparedSQL`, `DB.DirectDatabaseQuery`): whitespace and comments dropped, a qualified
 * name split into its parts (`ns` separators and `name`s), and an interpolated string or
 * heredoc as one `string` token with its embeds (`TextStrings::getEmbeds()`).
 *
 * Each token is `t` (type), `x` (source text), `p` (byte offset), `m` (the matching bracket's
 * index, on brackets), `short` (a short array's bracket), `e` (a string's embeds as text and
 * offset) and `h` (a heredoc or nowdoc).
 *
 * @internal
 * @mago-expect lint:halstead
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PhpcsTokens
{
    /** PHP tokens phpcs's token walks treat as plain: numbers, increments, casts, heredoc ends. */
    private const PLAIN = [
        T_LNUMBER,
        T_DNUMBER,
        T_INC,
        T_DEC,
        T_POW,
        T_STRING_CAST,
        T_ARRAY_CAST,
        T_OBJECT_CAST,
        T_END_HEREDOC,
    ];

    private function __construct() {}

    /**
     * @return list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}>
     */
    public static function of(SourceFile $file): array
    {
        return FileCache::remember($file, 'phpcs-db-tokens', static fn(): array => self::tokenize($file->contents));
    }

    /**
     * phpcs's `File::findEndOfStatement()`.
     *
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    public static function endOfStatement(array $tokens, int $start): int
    {
        $last = $start;
        $count = count($tokens);
        for ($k = $start; $k < $count; $k++) {
            $type = $tokens[$k]['t'];
            if ($k !== $start && in_array($type, [':', ',', '=>', ';'], strict: true)) {
                return $k;
            }

            if ($k !== $start && in_array($type, [')', ']', '}'], strict: true)) {
                return $last;
            }

            if (in_array($type, ['(', '[', '#[', '{'], strict: true) && ($tokens[$k]['m'] ?? null) !== null) {
                $k = $tokens[$k]['m'];
            }

            $last = $k;
        }

        return $count - 1;
    }

    /**
     * The per-line text tokens phpcs makes of a string or inline HTML token (a heredoc's body
     * lines only), as text and offset.
     *
     * @param array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool} $token
     * @return list<array{string, int}>
     */
    public static function textLines(array $token): array
    {
        $lines = [];
        $offset = $token['p'];
        foreach (explode("\n", $token['x']) as $line) {
            $lines[] = [$line, $offset];
            $offset += strlen($line) + 1;
        }

        return $token['h'] ?? false ? array_slice($lines, offset: 1, length: count($lines) - 2) : $lines;
    }

    /**
     * @return list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}>
     */
    private static function tokenize(string $contents): array
    {
        $raw = PhpToken::tokenize($contents);
        $count = count($raw);
        $tokens = [];
        $open = [];
        for ($k = 0; $k < $count; $k++) {
            $token = $raw[$k];
            if ($token->isIgnorable()) {
                continue;
            }

            if ($token->text === '"' || $token->id === T_START_HEREDOC) {
                $k = self::interpolated($raw, $k, $contents, $tokens);
                continue;
            }

            if (in_array($token->id, [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE], strict: true)) {
                $offset = $token->pos;
                foreach (explode('\\', $token->text) as $index => $part) {
                    if ($index > 0) {
                        $tokens[] = ['t' => 'ns', 'x' => '\\', 'p' => $offset++];
                    }

                    if ($part !== '') {
                        $tokens[] = ['t' => 'name', 'x' => $part, 'p' => $offset];
                    }

                    $offset += strlen($part);
                }

                continue;
            }

            $type = self::type($token, $tokens);
            $index = count($tokens);
            $tokens[] = ['t' => $type, 'x' => $token->text, 'p' => $token->pos];
            if ($type === '[') {
                // phpcs's short array: a `[` that does not follow something it can index.
                $tokens[$index]['short'] = !in_array(
                    $tokens[$index - 1]['t'] ?? '',
                    ['var', 'name', 'string', 'text', ')', ']', '}'],
                    strict: true,
                );
            }

            if (in_array($type, ['(', '[', '#[', '{'], strict: true)) {
                $open[] = $index;
            } elseif (in_array($type, [')', ']', '}'], strict: true) && $open !== []) {
                $opener = (int) array_pop($open);
                $tokens[$opener]['m'] = $index;
                $tokens[$index]['short'] = $tokens[$opener]['short'] ?? false;
            }
        }

        return $tokens;
    }

    /**
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    private static function type(PhpToken $token, array $tokens): string
    {
        $previous = $tokens[count($tokens) - 1]['t'] ?? '';

        return match (true) {
            $token->id === T_VARIABLE => 'var',
            $token->id === T_STRING => 'name',
            $token->id === T_NS_SEPARATOR => 'ns',
            $token->id === T_CONSTANT_ENCAPSED_STRING => 'text',
            $token->id === T_INLINE_HTML => 'html',
            $token->id === T_FUNCTION => 'function',
            $token->id === T_ATTRIBUTE => '#[',
            in_array($token->id, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], strict: true)
                => 'objop',
            in_array($token->id, [T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST], strict: true) => 'safecast',
            in_array($token->id, self::PLAIN, strict: true),
            in_array($token->text, ['.', '+', '-', '*', '/', '%'], strict: true),
                => 'ignored',
            in_array($token->text, ['(', ')', '[', ']', '{', '}', ',', ';'], strict: true) => $token->text,
            $token->id === T_CLOSE_TAG => ';',
            $token->id === T_DOUBLE_ARROW => '=>',
            // A named argument's colon; any other is a ternary's (T_INLINE_ELSE in phpcs).
            $token->text === ':'
                && $previous === 'name'
                && in_array($tokens[count($tokens) - 2]['t'] ?? '', ['(', ','], strict: true)
                => ':',
            default => 'other',
        };
    }

    /**
     * Adds a double-quoted string or heredoc from $raw[$k] as one `string` token with its
     * embeds; returns the index of its closing token.
     *
     * @param list<PhpToken> $raw
     * @param list<array{t: string, x: string, p: int, m?: int, short?: bool, e?: list<array{string, int}>, h?: bool}> $tokens
     */
    private static function interpolated(array $raw, int $k, string $contents, array &$tokens): int
    {
        $start = $raw[$k]->pos;
        $closer = $raw[$k]->text === '"' ? '"' : null;
        $count = count($raw);
        $embeds = [];
        for ($k++; $k < $count; $k++) {
            $token = $raw[$k];
            if ($closer !== null ? $token->text === $closer : $token->id === T_END_HEREDOC) {
                break;
            }

            if ($token->id === T_ENCAPSED_AND_WHITESPACE) {
                continue;
            }

            $first = $k;
            if ($token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES) {
                for ($depth = 1; $depth > 0 && ($k + 1) < $count;) {
                    $text = $raw[++$k]->text;
                    $depth += match (true) {
                        $text === '}' => -1,
                        $text === '{' || $raw[$k]->id === T_CURLY_OPEN || $raw[$k]->id === T_DOLLAR_OPEN_CURLY_BRACES
                            => 1,
                        default => 0,
                    };
                }
            } elseif (($raw[$k + 1]->text ?? '') === '[') {
                while (($k + 1) < $count && $raw[$k]->text !== ']') {
                    $k++;
                }
            } elseif (in_array($raw[$k + 1]->id ?? 0, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], strict: true)) {
                $k += 2;
            }

            $embeds[] = [
                implode('', array_map(
                    static fn(PhpToken $part): string => $part->text,
                    array_slice($raw, $first, $k - $first + 1),
                )),
                $raw[$first]->pos,
            ];
        }

        $last = $raw[$k] ?? $raw[$count - 1];
        $tokens[] = [
            't' => 'string',
            'x' => substr($contents, $start, $last->pos + strlen($last->text) - $start),
            'p' => $start,
            'e' => $embeds,
            'h' => $closer === null,
        ];

        return $k;
    }
}
