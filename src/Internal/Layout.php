<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function ltrim;
use function max;
use function preg_split;
use function str_contains;
use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function substr_count;

use const PREG_SPLIT_NO_EMPTY;

/**
 * A file's tokens laid out as phpcs 3 hands them to a whitespace sniff (tab width 4, as
 * WordPress-Core sets): a whitespace or inline HTML token ends at each newline and the indent
 * after it is a token of its own, a `//` or `#` comment keeps its newline, and every token has a
 * line, a column and a length. A `/* *\/` comment and a docblock stay one token (`endLine` is the
 * line it ends on). The tokens are those of PhpcsTokenStream, so `closer`/`opener` and the
 * `scopeOpener`/`scopeCloser` of a class, function or `try` still index into them.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:excessive-parameter-list
 */
final class Layout
{
    /** `Tokens::$emptyTokens`: whitespace and comments. */
    public const EMPTY = ['T_WHITESPACE' => true, 'T_COMMENT' => true, 'T_DOC_COMMENT' => true];

    private const CLASS_LIKE = ['T_CLASS' => true, 'T_INTERFACE' => true, 'T_TRAIT' => true, 'T_ENUM' => true];

    /** @var array<int, string> the content of a whitespace token with its tabs expanded, as phpcs sees it */
    private array $expanded = [];

    /** @var null|list<int> */
    private ?array $classLikes = null;

    /** @var null|list<int> */
    private ?array $ooScopes = null;

    /**
     * @param list<PhpcsToken> $t
     * @param array<int, int> $column
     * @param array<int, int> $length
     * @param array<int, int> $endLine
     */
    private function __construct(
        public readonly array $t,
        public readonly array $column,
        public readonly array $length,
        public readonly array $endLine,
        public readonly string $eol,
        public readonly int $count,
    ) {}

    public static function of(SourceFile $file): self
    {
        return FileCache::remember($file, 'layout', static fn(): self => self::build($file->contents));
    }

    public static function build(string $source): self
    {
        $eolAt = strpos($source, "\n");
        $eol = $eolAt === false ? "\n" : ($eolAt > 0 && $source[$eolAt - 1] === "\r" ? "\r\n" : "\n");
        $raw = PhpcsTokenStream::fromSource($source);
        /** @var list<PhpcsToken> $out */
        $out = [];
        /** @var array<int, int> $map */
        $map = [];
        $kept = [];
        foreach ($raw as $old => $token) {
            $map[$old] = count($out);
            $code = $token->code;
            if ($code === 'T_WHITESPACE' || $code === 'T_INLINE_HTML') {
                $text = $token->text;
                $last = $out === [] ? null : $out[count($out) - 1];
                if (
                    $code === 'T_WHITESPACE'
                    && $last !== null
                    && $last->code === 'T_COMMENT'
                    && !str_ends_with($last->text, "\n")
                    && (str_starts_with($last->text, '//') || str_starts_with($last->text, '#'))
                    && (str_starts_with($text, "\n") || str_starts_with($text, "\r\n"))
                ) {
                    // PHP 8 ends a line comment before its newline; phpcs keeps the newline in the comment.
                    $cut = $text[0] === "\r" ? 2 : 1;
                    $last->text .= substr($text, 0, $cut);
                    $text = substr($text, $cut);
                    $token->pos += $cut;
                    ++$token->line;
                    if ($text === '') {
                        $map[$old] = count($out);
                        continue;
                    }
                }

                $pieces = preg_split('/(?<=\n)/', $text, flags: PREG_SPLIT_NO_EMPTY);
                $pos = $token->pos;
                $line = $token->line;
                foreach ($pieces === false ? [$text] : $pieces as $piece) {
                    $out[] = PhpcsToken::of($code, $piece, $pos, $line);
                    $pos += strlen($piece);
                    $line += str_ends_with($piece, "\n") ? 1 : 0;
                }

                continue;
            }

            $kept[] = count($out);
            $out[] = $token;
        }

        foreach ($kept as $i) {
            $token = $out[$i];
            $token->closer = $token->closer === null ? null : $map[$token->closer];
            $token->opener = $token->opener === null ? null : $map[$token->opener];
            $token->scopeOpener = $token->scopeOpener === null ? null : $map[$token->scopeOpener];
            $token->scopeCloser = $token->scopeCloser === null ? null : $map[$token->scopeCloser];
        }

        [$column, $length, $endLine, $expanded] = self::measure($out, $eol);
        $layout = new self($out, $column, $length, $endLine, $eol, count($out));
        $layout->expanded = $expanded;

        return $layout;
    }

    /**
     * @param list<PhpcsToken> $tokens
     *
     * @return array{array<int, int>, array<int, int>, array<int, int>, array<int, string>}
     */
    private static function measure(array $tokens, string $eol): array
    {
        $column = [];
        $length = [];
        $endLine = [];
        $expanded = [];
        $col = 1;
        $eolLength = strlen($eol);
        foreach ($tokens as $i => $token) {
            $text = $token->text;
            $column[$i] = $col;
            $newlines = substr_count($text, "\n");
            if (str_contains($text, "\t")) {
                $text = self::expand($text, $col);
                if ($token->code === 'T_WHITESPACE') {
                    $expanded[$i] = $text;
                }
            }

            $width = strlen($text);
            $length[$i] = str_ends_with($text, $eol) ? $width - $eolLength : $width;
            $trailing = str_ends_with($text, "\n") ? 1 : 0;
            $endLine[$i] = $token->line + $newlines - $trailing;
            if ($newlines === 0) {
                $col += $width;
                continue;
            }

            $col = str_ends_with($text, "\n") ? 1 : 1 + $width - 1 - (int) strrpos($text, "\n");
        }

        return [$column, $length, $endLine, $expanded];
    }

    /** Replaces each tab by the spaces up to the next multiple of four columns. */
    private static function expand(string $text, int $col): string
    {
        $out = '';
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "\t") {
                $pad = 4 - (($col - 1) % 4);
                $out .= self::spaces($pad);
                $col += $pad;
                continue;
            }

            $out .= $char;
            $col = $char === "\n" ? 1 : $col + 1;
        }

        return $out;
    }

    /** A run of spaces; a negative count is none. */
    public static function spaces(int $count): string
    {
        return str_repeat(' ', max(0, $count));
    }

    /** The token's content as phpcs reports it: a whitespace token has its tabs expanded. */
    public function content(int $i): string
    {
        return $this->expanded[$i] ?? $this->t[$i]->text;
    }

    public function code(int $i): string
    {
        return $this->t[$i]->code;
    }

    public function line(int $i): int
    {
        return $this->t[$i]->line;
    }

    /** `Tokens::$commentTokens` (phpcs annotations included). */
    public function isComment(int $i): bool
    {
        $code = $this->t[$i]->code;

        return $code === 'T_COMMENT' || $code === 'T_DOC_COMMENT';
    }

    /** A `phpcs:ignore` or `@codingStandardsIgnoreLine` comment, which phpcs tokenizes as `T_PHPCS_IGNORE`. */
    public function isPhpcsIgnore(int $i): bool
    {
        return $this->t[$i]->code === 'T_COMMENT' && self::ignoresLine($this->t[$i]->text);
    }

    /** Whether a comment's text is a `phpcs:ignore` or `@codingStandardsIgnoreLine` instruction. */
    public static function ignoresLine(string $comment): bool
    {
        $text = strtolower(ltrim($comment, " \t/#*"));

        return str_starts_with($text, 'phpcs:ignore') || str_starts_with($text, '@codingstandardsignoreline');
    }

    /** A token `Tokens::$emptyTokens` holds. */
    public function isEmpty(int $i): bool
    {
        return (self::EMPTY[$this->t[$i]->code] ?? null) !== null;
    }

    /**
     * The `class`, `interface`, `trait` and `enum` tokens (not `Foo::class`, not an anonymous class).
     *
     * @return list<int>
     */
    public function classLikes(): array
    {
        $this->scanClasses();

        return $this->classLikes ?? [];
    }

    /**
     * `Tokens::$ooScopeTokens` that open a body: classes, interfaces, traits, enums and anonymous
     * classes, in file order.
     *
     * @return list<int>
     */
    public function ooScopes(): array
    {
        $this->scanClasses();

        return $this->ooScopes ?? [];
    }

    private function scanClasses(): void
    {
        if ($this->classLikes !== null) {
            return;
        }

        $this->classLikes = [];
        $this->ooScopes = [];
        foreach ($this->t as $i => $token) {
            if ((self::CLASS_LIKE[$token->code] ?? null) !== null) {
                $this->classLikes[] = $i;
                $this->ooScopes[] = $i;
            } elseif ($token->code === 'T_ANON_CLASS') {
                $this->ooScopes[] = $i;
            }
        }
    }

    /**
     * `File::findNext()` for a token not in `$codes` when `$exclude`, else in them; `$end` is exclusive.
     *
     * @param string|list<string> $codes
     */
    public function next(string|array $codes, int $from, ?int $end = null, bool $exclude = false): ?int
    {
        $codes = (array) $codes;
        $end ??= $this->count;
        for ($i = $from; $i < $end; $i++) {
            if (in_array($this->t[$i]->code, $codes, strict: true) !== $exclude) {
                return $i;
            }
        }

        return null;
    }

    /** The next token that is not whitespace or a comment. */
    public function nextContent(int $from, ?int $end = null): ?int
    {
        $end ??= $this->count;
        for ($i = $from; $i < $end; $i++) {
            if (!$this->isEmpty($i)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * `File::findPrevious()`; `$end` is inclusive.
     *
     * @param string|list<string> $codes
     */
    public function previous(string|array $codes, int $from, ?int $end = null, bool $exclude = false): ?int
    {
        $codes = (array) $codes;
        $end ??= 0;
        for ($i = $from; $i >= $end; $i--) {
            if (in_array($this->t[$i]->code, $codes, strict: true) !== $exclude) {
                return $i;
            }
        }

        return null;
    }

    /** The previous token that is not whitespace or a comment. */
    public function previousContent(int $from, ?int $end = null): ?int
    {
        $end ??= 0;
        for ($i = $from; $i >= $end; $i--) {
            if (!$this->isEmpty($i)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * `File::findFirstOnLine()` for no types: the first token on the line of `$i`, or with
     * `$skipWhitespace` the first one that is not whitespace.
     */
    public function firstOnLine(int $i, bool $skipWhitespace = false): int
    {
        $line = $this->t[$i]->line;
        $start = $i;
        while ($start > 0 && $this->t[$start - 1]->line === $line) {
            $start--;
        }

        if (!$skipWhitespace) {
            return $start;
        }

        for ($j = $start; $j < $i; $j++) {
            if ($this->t[$j]->code !== 'T_WHITESPACE') {
                return $j;
            }
        }

        return $i;
    }

    /** The span of a token. */
    public function span(int $i): Span
    {
        $pos = $this->t[$i]->pos;

        return new Span($pos, $pos + strlen($this->t[$i]->text));
    }

    /**
     * The edits that make `$fix` apply: one replacement per touched token.
     *
     * @param array<int, string> $content token => its new text
     *
     * @return list<TextEdit>
     */
    public function edits(array $content): array
    {
        $edits = [];
        foreach ($content as $i => $text) {
            if ($text !== $this->t[$i]->text) {
                $edits[] = TextEdit::replace($this->span($i), $text);
            }
        }

        return $edits;
    }
}
