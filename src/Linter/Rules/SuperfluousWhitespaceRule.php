<?php

declare(strict_types=1);

namespace Rlorenzo\MagoWordPress\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_pop;
use function count;
use function explode;
use function preg_match;
use function sprintf;
use function strlen;
use function strpos;

use const T_CLASS;
use const T_CLOSE_TAG;
use const T_COMMENT;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_FUNCTION;
use const T_INLINE_HTML;
use const T_NEW;
use const T_OPEN_TAG;
use const T_WHITESPACE;

/**
 * Ports `Squiz.WhiteSpace.SuperfluousWhitespace` as WordPress-Core sets it (`ignoreBlankLines` off):
 * whitespace before the first `<?php` (`StartFile`) and after the last `?>` (`EndFile`), whitespace at the
 * end of a line of code or of a comment (`EndLine`), and two or more empty lines in a row inside a
 * function or closure (`EmptyLines`, collapsed to one). Lines inside strings, heredocs and inline HTML are
 * not checked, as in the sniff. Like the sniff, it skips the empty lines directly inside an anonymous class
 * (told by the innermost brace).
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class SuperfluousWhitespaceRule implements Rule
{
    private const SNIFF = 'Squiz.WhiteSpace.SuperfluousWhitespace';

    private const ANNOTATION = '~^(?://|\#)\s*@?phpcs:(?:ignore|ignoreFile|disable|enable|set)\b~i';

    private const OTHER_BODY = 0;

    private const FUNCTION_BODY = 1;

    private const ANONYMOUS_CLASS_BODY = 2;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'generic/superfluous-whitespace',
            name: 'Superfluous whitespace',
            description: 'Reports whitespace at the start or end of the file, at the end of a line, and runs of empty lines inside functions.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $text = $context->file->contents;
        $tokens = PhpToken::tokenize($text);
        $count = count($tokens);
        $this->fileEdges($context, $tokens, $count);

        // Empty lines are only looked for in a file with an empty line followed by a blank one.
        $checkEmptyLines = preg_match('/\n\r?\n[ \t]*\r?\n/', $text) === 1;
        $stack = [];
        $pending = null;
        $depth = 0;
        $functions = 0;
        $previousCode = null;
        // A `// phpcs:ignore` style comment is a token of its own in phpcs that keeps its line break;
        // true when it starts its line.
        $annotation = null;
        foreach ($tokens as $index => $token) {
            $id = $token->id;
            if ($id === T_WHITESPACE) {
                $this->endLines($context, $text, $token, $index === ($count - 1), $annotation !== null);
                if (
                    $checkEmptyLines
                    && $functions > 0
                    && ($stack[count($stack) - 1] ?? self::OTHER_BODY) !== self::ANONYMOUS_CLASS_BODY
                ) {
                    $this->emptyLines($context, $token, $annotation === true);
                }

                $annotation = null;
                continue;
            }

            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                $this->endLines($context, $text, $token, $index === ($count - 1), false);
                $annotation =
                    $id === T_COMMENT && preg_match(self::ANNOTATION, $token->text) === 1
                        ? $token->pos === 0 || $text[$token->pos - 1] === "\n"
                        : null;
                continue;
            }

            $annotation = null;

            if ($id === T_FUNCTION) {
                $pending = self::FUNCTION_BODY;
                $depth = 0;
            } elseif ($id === T_CLASS && $previousCode?->id === T_NEW) {
                $pending = self::ANONYMOUS_CLASS_BODY;
                $depth = 0;
            } elseif ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $stack[] = self::OTHER_BODY;
            } elseif ($token->text === '(') {
                ++$depth;
            } elseif ($token->text === ')') {
                --$depth;
            } elseif ($token->text === ';' && $depth <= 0) {
                $pending = null;
            } elseif ($token->text === '{') {
                $kind = $pending !== null && $depth <= 0 ? $pending : self::OTHER_BODY;
                $pending = null;
                $stack[] = $kind;
                $functions += $kind === self::FUNCTION_BODY ? 1 : 0;
            } elseif ($token->text === '}') {
                $functions -= array_pop($stack) === self::FUNCTION_BODY ? 1 : 0;
            }

            $previousCode = $token;
        }
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function fileEdges(LintContext $context, array $tokens, int $count): void
    {
        if ($count > 1 && $tokens[0]->id === T_INLINE_HTML && $tokens[1]->id === T_OPEN_TAG) {
            if (preg_match('/^[\pZ\s]+$/u', $tokens[0]->text) === 1) {
                $this->edge(
                    $context,
                    $tokens[1],
                    'Additional whitespace found at start of file',
                    'StartFile',
                    new Span(0, $tokens[1]->pos),
                );
            }
        }

        if ($count > 1 && $tokens[$count - 2]->id === T_CLOSE_TAG && $tokens[$count - 1]->id === T_INLINE_HTML) {
            $html = $tokens[$count - 1];
            if (preg_match('/^[\pZ\s]+$/u', $html->text) === 1) {
                $this->edge(
                    $context,
                    $tokens[$count - 2],
                    'Additional whitespace found at end of file',
                    'EndFile',
                    new Span($html->pos, $html->pos + strlen($html->text)),
                );
            }
        }
    }

    private function edge(LintContext $context, PhpToken $tag, string $message, string $code, Span $remove): void
    {
        $this->report->issue(
            $context,
            Issue::new($message, new Span($tag->pos, $tag->pos + strlen($tag->text)))->withEdit(TextEdit::delete(
                $remove,
            )),
            [self::SNIFF . '.' . $code],
        );
    }

    /** Spaces and tabs before each line break inside the token, and before the end of the file. */
    private function endLines(
        LintContext $context,
        string $text,
        PhpToken $token,
        bool $isLast,
        bool $afterAnnotation,
    ): void {
        $content = $token->text;
        $offset = 0;
        if ($afterAnnotation && ($content[0] ?? '') === "\n") {
            // The line break after a phpcs annotation comment belongs to that comment's token.
            $offset = 1;
        }

        while (($newline = strpos($content, "\n", $offset)) !== false) {
            $this->trailing($context, $text, $token->pos + $newline);
            $offset = $newline + 1;
        }

        $end = strlen($content);
        if ($isLast && $end > 0 && $content[$end - 1] !== "\n") {
            $this->trailing($context, $text, $token->pos + $end);
        }
    }

    /** Reports the spaces and tabs right before the line break (or the end of the file) at $at. */
    private function trailing(LintContext $context, string $text, int $at): void
    {
        $end = $at > 0 && $text[$at - 1] === "\r" && ($text[$at] ?? '') === "\n" ? $at - 1 : $at;
        $start = $end;
        while ($start > 0 && ($text[$start - 1] === ' ' || $text[$start - 1] === "\t")) {
            --$start;
        }

        if ($start === $end) {
            return;
        }

        $span = new Span($start, $end);
        $this->report->issue(
            $context,
            Issue::new('Whitespace found at end of line', $span)->withEdit(TextEdit::delete($span)),
            [self::SNIFF . '.EndLine'],
        );
    }

    /**
     * Each empty line that follows a non-empty one and is followed by more blank lines before the next token,
     * in a whitespace token that sits in a function.
     */
    private function emptyLines(LintContext $context, PhpToken $token, bool $afterLineStartAnnotation): void
    {
        $lines = explode("\n", $token->text);
        $last = count($lines) - 1;
        if ($last < 3) {
            // The line break ending the code line, the empty line and one more line at least.
            return;
        }

        $offsets = [];
        $position = $token->pos;
        foreach ($lines as $line) {
            $offsets[] = $position;
            $position += strlen($line) + 1;
        }

        // $offsets[$j] is where line $j of the token starts; line 0 is the rest of the code line.
        for ($j = 1; $j < $last; ++$j) {
            $line = $lines[$j];
            $before = $j === 1 ? 'code' : $lines[$j - 1];
            if ($j === 1 && $afterLineStartAnnotation) {
                // In phpcs the comment is then the only token on its line, so the empty line does not count.
                continue;
            }

            if ($line !== '' && $line !== "\r" || $before === '' || $before === "\r") {
                continue;
            }

            $empty = $last - $j;
            if ($empty < 2) {
                continue;
            }

            $newline = $offsets[$j] + strlen($line);
            $this->report->issue(
                $context,
                Issue::new(
                    sprintf('Functions must not contain multiple empty lines in a row; found %d empty lines', $empty),
                    new Span($newline, $newline + 1),
                )->withEdit(TextEdit::replace(new Span($offsets[$j], $offsets[$last]), "\n")),
                [self::SNIFF . '.EmptyLines'],
            );
        }
    }
}
