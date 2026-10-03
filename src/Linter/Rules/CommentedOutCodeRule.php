<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_pop;
use function array_slice;
use function ceil;
use function count;
use function explode;
use function in_array;
use function ltrim;
use function min;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function substr_count;
use function trim;
use function usort;

/**
 * Ports `Squiz.PHP.CommentedOutCode` with WordPress-Extra's `maxPercentage` of 40: a comment
 * (a run of `//`/`#` lines with no blank line between them, or one block comment) whose text,
 * tokenized as PHP, is more than 40 % code tokens. The text is tokenized with PHP's tokenizer and
 * the tokens phpcs 3 makes of it are reproduced where they change the count: one token per line
 * of a multi-line token, a double-quoted string as one token, names split at `\`, goto labels,
 * named-argument labels, keywords that read as names, and `true`/`false`/`null`/`self`/`parent`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:halstead
 */
final class CommentedOutCodeRule implements Rule
{
    private const CODE = 'Squiz.PHP.CommentedOutCode.Found';

    /** WordPress-Extra's `maxPercentage`. */
    private const MAX_PERCENTAGE = 40;

    /** Tokens the sniff does not count as code (phpcs comment tokens are T_COMMENT here). */
    private const NOT_CODE = [
        'T_WHITESPACE' => true,
        'T_STRING' => true,
        'T_STRING_CONCAT' => true,
        'T_ENCAPSED_AND_WHITESPACE' => true,
        'T_NONE' => true,
        'T_COMMENT' => true,
        'T_GOTO_LABEL' => true,
        // Tokens::$comparisonTokens.
        'T_IS_EQUAL' => true,
        'T_IS_IDENTICAL' => true,
        'T_IS_NOT_EQUAL' => true,
        'T_IS_NOT_IDENTICAL' => true,
        'T_LESS_THAN' => true,
        'T_GREATER_THAN' => true,
        'T_IS_SMALLER_OR_EQUAL' => true,
        'T_IS_GREATER_OR_EQUAL' => true,
        'T_SPACESHIP' => true,
        'T_COALESCE' => true,
        // Tokens::$arithmeticTokens.
        'T_PLUS' => true,
        'T_MINUS' => true,
        'T_MULTIPLY' => true,
        'T_DIVIDE' => true,
        'T_MODULUS' => true,
        'T_POW' => true,
    ];

    private const CHARS = [
        '.' => 'T_STRING_CONCAT',
        '<' => 'T_LESS_THAN',
        '>' => 'T_GREATER_THAN',
        '+' => 'T_PLUS',
        '-' => 'T_MINUS',
        '*' => 'T_MULTIPLY',
        '/' => 'T_DIVIDE',
        '%' => 'T_MODULUS',
        '?' => 'T_INLINE_THEN',
        ';' => 'T_SEMICOLON',
        '{' => 'T_OPEN_CURLY_BRACKET',
        '(' => 'T_OPEN_PARENTHESIS',
        ',' => 'T_COMMA',
        '&' => 'T_BITWISE_AND',
    ];

    /** PHP::$tstringContexts: after these, a keyword or `true`/`self`/... is a name. */
    private const TSTRING_CONTEXTS = [
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_FUNCTION' => true,
        'T_CLASS' => true,
        'T_INTERFACE' => true,
        'T_TRAIT' => true,
        'T_ENUM' => true,
        'T_ENUM_CASE' => true,
        'T_EXTENDS' => true,
        'T_IMPLEMENTS' => true,
        'T_ATTRIBUTE' => true,
        'T_NEW' => true,
        'T_CONST' => true,
        'T_NS_SEPARATOR' => true,
        'T_USE' => true,
        'T_NAMESPACE' => true,
        'T_DOUBLE_COLON' => true,
    ];

    /** Tokens::$contextSensitiveKeywords. */
    private const KEYWORDS = [
        'T_ABSTRACT',
        'T_ARRAY',
        'T_AS',
        'T_BREAK',
        'T_CALLABLE',
        'T_CASE',
        'T_CATCH',
        'T_CLASS',
        'T_CLONE',
        'T_CONST',
        'T_CONTINUE',
        'T_DECLARE',
        'T_DEFAULT',
        'T_DO',
        'T_ECHO',
        'T_ELSE',
        'T_ELSEIF',
        'T_EMPTY',
        'T_ENDDECLARE',
        'T_ENDFOR',
        'T_ENDFOREACH',
        'T_ENDIF',
        'T_ENDSWITCH',
        'T_ENDWHILE',
        'T_ENUM',
        'T_EVAL',
        'T_EXIT',
        'T_EXTENDS',
        'T_FINAL',
        'T_FINALLY',
        'T_FN',
        'T_FOR',
        'T_FOREACH',
        'T_FUNCTION',
        'T_GLOBAL',
        'T_GOTO',
        'T_IF',
        'T_IMPLEMENTS',
        'T_INCLUDE',
        'T_INCLUDE_ONCE',
        'T_INSTANCEOF',
        'T_INSTEADOF',
        'T_INTERFACE',
        'T_ISSET',
        'T_LIST',
        'T_LOGICAL_AND',
        'T_LOGICAL_OR',
        'T_LOGICAL_XOR',
        'T_MATCH',
        'T_NAMESPACE',
        'T_NEW',
        'T_PRINT',
        'T_PRIVATE',
        'T_PROTECTED',
        'T_PUBLIC',
        'T_READONLY',
        'T_REQUIRE',
        'T_REQUIRE_ONCE',
        'T_RETURN',
        'T_STATIC',
        'T_SWITCH',
        'T_THROW',
        'T_TRAIT',
        'T_TRY',
        'T_UNSET',
        'T_USE',
        'T_VAR',
        'T_WHILE',
        'T_YIELD',
        'T_YIELD_FROM',
    ];

    private const EMPTY = ['T_WHITESPACE' => true, 'T_COMMENT' => true, 'T_DOC_COMMENT' => true];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/commented-out-code',
            name: 'Commented out code',
            description: 'Reports a comment whose text is mostly PHP code (more than 40 % of its tokens, as WordPress-Extra sets it), which is usually code that was commented out instead of deleted.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $contents = $context->file->contents;
        $eol = preg_match('/\r\n?|\n/', $contents, $match) === 1 ? $match[0] : "\n";
        $comments = self::comments($context, $eol);
        $count = count($comments);
        $i = 0;
        while ($i < $count) {
            if ($comments[$i]['type'] !== 'comment' || str_starts_with($comments[$i]['text'], '//end ')) {
                $i++;
                continue;
            }

            [$content, $last] = self::gather($comments, $i, $eol);
            $percent = self::percentCode($content);
            if ($percent !== null && $percent > self::MAX_PERCENTAGE) {
                $percent = min(100, $percent);
                $this->report->issue(
                    $context,
                    Issue::new(
                        "This comment is {$percent}% valid code; is this commented out code?",
                        $comments[$i]['span'],
                        'looks like code',
                    )->withHelp('Delete the commented out code; version control keeps it.'),
                    [self::CODE],
                );
            }

            $i = $last + 1;
        }
    }

    /**
     * The file's comments as phpcs tokenizes them: a block comment is one token per line; a
     * `phpcs:` instruction is `phpcs`; a docblock is `doc`. `adjacent` is whether only
     * whitespace separates the token from the one before.
     *
     * @return list<array{type: string, text: string, line: int, span: Span, adjacent: bool}>
     */
    private static function comments(LintContext $context, string $eol): array
    {
        $file = $context->file;
        $trivia = $file->getTrivia();
        usort($trivia, static fn($a, $b): int => $a->span->start <=> $b->span->start);
        $tokens = [];
        $end = null;
        foreach ($trivia as $comment) {
            $start = $comment->span->start;
            $adjacent = $end !== null && trim(substr($file->contents, $end, $start - $end)) === '';
            $end = $comment->span->end;
            $line = substr_count($file->contents, "\n", 0, $start) + 1;
            $text = $file->getText($comment->span);
            if ($comment->kind === TriviaKind::DocBlockComment) {
                $tokens[] = [
                    'type' => 'doc',
                    'text' => $text,
                    'line' => $line,
                    'span' => $comment->span,
                    'adjacent' => $adjacent,
                ];
                continue;
            }

            $offset = $start;
            foreach (explode($eol, $text) as $index => $part) {
                $tokens[] = [
                    'type' => self::isPhpcsInstruction($part) ? 'phpcs' : 'comment',
                    'text' => $part,
                    'line' => $line + $index,
                    'span' => new Span($offset, $offset + strlen($part)),
                    'adjacent' => $index > 0 || $adjacent,
                ];
                $offset += strlen($part) + strlen($eol);
            }
        }

        return $tokens;
    }

    /** A comment phpcs turns into a T_PHPCS_* token. */
    private static function isPhpcsInstruction(string $text): bool
    {
        $text = strtolower(rtrim(ltrim($text, " \t/*#"), " */\t\r\n"));
        $text = str_starts_with($text, '@phpcs:') ? substr($text, 1) : $text;
        foreach (['phpcs:set', 'phpcs:ignorefile', 'phpcs:disable', 'phpcs:enable', 'phpcs:ignore'] as $prefix) {
            if (str_starts_with($text, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The comment text the sniff collects from token $i on, and the last token it used.
     *
     * @param list<array{type: string, text: string, line: int, span: Span, adjacent: bool}> $comments
     *
     * @return array{string, int}
     */
    private static function gather(array $comments, int $i, string $eol): array
    {
        $block = str_starts_with($comments[$i]['text'], '/*');
        $content = '';
        $lastLine = $comments[$i]['line'];
        $last = $i;
        $count = count($comments);
        for ($j = $i; $j < $count && ($j === $i || $comments[$j]['adjacent']); $j++) {
            $token = $comments[$j];
            if ($token['type'] === 'phpcs') {
                $lastLine = $token['line'];
                continue;
            }

            if (!$block && ($lastLine + 1) <= $token['line'] && str_starts_with($token['text'], '/*')) {
                break;
            }

            if (!$block && ($lastLine + 1) < $token['line']) {
                break;
            }

            $text = trim($token['text']);
            $stop = false;
            if (!$block) {
                $text = str_starts_with($text, '//') ? substr($text, 2) : $text;
                $text = str_starts_with($text, '#') ? substr($text, 1) : $text;
            } else {
                $text = str_starts_with($text, '/**') ? substr($text, 3) : $text;
                $text = str_starts_with($text, '/*') ? substr($text, 2) : $text;
                if (str_ends_with($text, '*/')) {
                    $text = substr($text, 0, -2);
                    $stop = true;
                }

                $text = str_starts_with($text, '*') ? substr($text, 1) : $text;
            }

            $content .= $text . $eol;
            $lastLine = $token['line'];
            $last = $j;
            if ($stop) {
                break;
            }
        }

        return [$content, $last];
    }

    /** The share of code tokens in the comment text, or NULL when the sniff does not judge it. */
    private static function percentCode(string $content): ?int
    {
        if (preg_match('`^\s*@[A-Za-z()\._-]+\s*$`', $content) === 1) {
            return null;
        }

        $content = trim((string) preg_replace('/\d+/', '', (string) preg_replace('/[-=#*]{2,}/', '-', $content)));
        if ($content === '') {
            return null;
        }

        $eol = preg_match('/\r\n?|\n/', $content, $match) === 1 ? $match[0] : "\n";
        $codes = self::phpcsCodes('<?php ' . $content . ' ?>', $eol);
        if (($codes[0] ?? null) !== 'T_OPEN_TAG' || $codes[count($codes) - 1] !== 'T_CLOSE_TAG') {
            return null;
        }

        $codes = array_slice($codes, 1, -1);
        $lastCode = $codes[count($codes) - 1] ?? null;
        if ($lastCode === null || !isset(self::EMPTY[$lastCode])) {
            return null;
        }

        if ($lastCode === 'T_WHITESPACE') {
            array_pop($codes);
        }

        $numCode = 0;
        $nonWhitespace = 0;
        foreach ($codes as $code) {
            $numCode += isset(self::NOT_CODE[$code]) ? 0 : 1;
            $nonWhitespace += $code === 'T_WHITESPACE' ? 0 : 1;
        }

        if ($nonWhitespace <= 2) {
            return null;
        }

        return (int) ceil(($numCode / count($codes)) * 100);
    }

    /**
     * The token codes phpcs's PHP tokenizer gives the text, one per phpcs token.
     *
     * @return list<string>
     */
    private static function phpcsCodes(string $source, string $eol): array
    {
        $raw = PhpToken::tokenize($source);
        $count = count($raw);
        $codes = [];
        $nextNotEmpty = static function (int $from) use ($raw, $count): ?PhpToken {
            for ($k = $from; $k < $count; $k++) {
                if (!$raw[$k]->isIgnorable()) {
                    return $raw[$k];
                }
            }

            return null;
        };
        $prevNotEmpty = static function (int $from) use ($raw): ?PhpToken {
            for ($k = $from; $k > 0; $k--) {
                if (!$raw[$k]->isIgnorable()) {
                    return $raw[$k];
                }
            }

            return null;
        };
        $lines = static fn(string $text): int => substr_count($text, $eol) + (str_ends_with($text, $eol) ? 0 : 1);

        for ($i = 0; $i < $count; $i++) {
            $token = $raw[$i];
            $name = $token->id < 256 ? self::CHARS[$token->text] ?? $token->text : (string) $token->getTokenName();
            $name = match ($name) {
                'T_PAAMAYIM_NEKUDOTAYIM' => 'T_DOUBLE_COLON',
                'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' => 'T_BITWISE_AND',
                default => $name,
            };
            $before = self::lastNotEmpty($codes);

            // A keyword used as a name.
            if (in_array($name, self::KEYWORDS, strict: true) && isset(self::TSTRING_CONTEXTS[$before])) {
                $preserve = $before === 'T_NEW' && in_array($name, ['T_CLASS', 'T_STATIC'], strict: true);
                if (!$preserve) {
                    $codes[] = 'T_STRING';
                    continue;
                }
            }

            // PHP 8 moves the newline after a `//` or `#` comment into the next whitespace token.
            if (
                $name === 'T_COMMENT'
                && !str_starts_with($token->text, '/*')
                && ($raw[$i + 1] ?? null)?->id === T_WHITESPACE
                && str_starts_with($raw[$i + 1]->text, "\n")
            ) {
                $next = $raw[$i + 1];
                $raw[$i + 1] = new PhpToken(T_WHITESPACE, substr($next->text, 1), $next->line, $next->pos + 1);
                $codes[] = 'T_COMMENT';
                if ($raw[$i + 1]->text === '') {
                    $i++;
                }

                continue;
            }

            if ($token->text === '"') {
                $text = '"';
                $depth = 0;
                while (++$i < $count) {
                    $part = $raw[$i];
                    $text .= $part->text;
                    if (in_array($part->text, ['{', '${'], strict: true) && $part->id !== T_ENCAPSED_AND_WHITESPACE) {
                        $depth++;
                    } elseif ($part->text === '}' && $depth > 0) {
                        $depth--;
                    } elseif ($part->text === '"' && $depth === 0) {
                        break;
                    }
                }

                for ($k = $lines($text); $k > 0; $k--) {
                    $codes[] = 'T_DOUBLE_QUOTED_STRING';
                }

                continue;
            }

            if (in_array($token->id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], strict: true)) {
                foreach (explode('\\', $token->text) as $index => $part) {
                    if ($index > 0) {
                        $codes[] = 'T_NS_SEPARATOR';
                    }

                    if ($part !== '') {
                        $codes[] = $index === 0 && strtolower($part) === 'namespace' ? 'T_NAMESPACE' : 'T_STRING';
                    }
                }

                continue;
            }

            // A named-argument label.
            if (
                $token->id >= 256
                && ($token->id === T_STRING || preg_match('`^[a-zA-Z_\x80-\xff]`', $token->text) === 1)
            ) {
                $next = $nextNotEmpty($i + 1);
                $prev = $prevNotEmpty($i - 1);
                if (
                    $next?->text === ':'
                    && $next->id < 256
                    && $prev !== null
                    && $prev->id < 256
                    && in_array($prev->text, ['(', ','], strict: true)
                ) {
                    $codes[] = 'T_PARAM_NAME';
                    continue;
                }
            }

            if (
                $name === 'T_MATCH'
                && ($nextNotEmpty($i + 1)?->text !== '(' || isset(self::TSTRING_CONTEXTS[$before]))
            ) {
                $codes[] = 'T_STRING';
                continue;
            }

            // A goto label: a name and a colon, unless a `case`, `?` or `enum` comes first.
            if (
                $token->id === T_STRING
                && ($raw[$i + 1] ?? null)?->text === ':'
                && ($raw[$i - 1] ?? null)?->id !== T_PAAMAYIM_NEKUDOTAYIM
            ) {
                $stop = null;
                for ($x = count($codes) - 1; $x > 0; $x--) {
                    if (!in_array(
                        $codes[$x],
                        ['T_CASE', 'T_SEMICOLON', 'T_OPEN_TAG', 'T_OPEN_CURLY_BRACKET', 'T_INLINE_THEN', 'T_ENUM'],
                        strict: true,
                    )) {
                        continue;
                    }

                    $stop = $codes[$x];
                    break;
                }

                if (!in_array($stop ?? $codes[0] ?? '', ['T_CASE', 'T_INLINE_THEN', 'T_ENUM'], strict: true)) {
                    $codes[] = 'T_GOTO_LABEL';
                    $i++;
                    continue;
                }
            }

            if ($token->id >= 256 && str_contains($token->text, $eol)) {
                for ($k = $lines($token->text); $k > 0; $k--) {
                    $codes[] = $name;
                }

                continue;
            }

            if ($token->id === T_STRING) {
                $lower = strtolower($token->text);
                $special = in_array($lower, ['true', 'false', 'null', 'self', 'parent'], strict: true);
                $asName =
                    isset(self::TSTRING_CONTEXTS[$before])
                    && !($before === 'T_NEW' && in_array($lower, ['self', 'parent'], strict: true));
                if ($special && !$asName && $nextNotEmpty($i + 1)?->text !== '(') {
                    $name = 'T_' . strtoupper($lower);
                }
            }

            // `array` not followed by `(` is a type, which phpcs makes a T_STRING.
            if ($name === 'T_ARRAY' && !in_array($nextNotEmpty($i + 1)?->text, [null, '('], strict: true)) {
                $name = 'T_STRING';
            }

            if ($name === 'T_FN' && !in_array($nextNotEmpty($i + 1)?->text, ['(', '&'], strict: true)) {
                $name = 'T_STRING';
            }

            $codes[] = $name;
        }

        return $codes;
    }

    /** @param list<string> $codes */
    private static function lastNotEmpty(array $codes): string
    {
        for ($k = count($codes) - 1; $k >= 0; $k--) {
            $code = $codes[$k] ?? '';
            if (!isset(self::EMPTY[$code])) {
                return $code;
            }
        }

        return '';
    }
}
