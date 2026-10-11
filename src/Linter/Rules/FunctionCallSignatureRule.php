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
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\FileCache;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_pop;
use function count;
use function intdiv;
use function max;
use function preg_match;
use function str_contains;
use function str_repeat;
use function strlen;
use function strpos;
use function strrpos;
use function strspn;
use function substr;
use function usort;

use const T_ATTRIBUTE;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_CURLY_OPEN;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_END_HEREDOC;
use const T_INLINE_HTML;
use const T_START_HEREDOC;

/**
 * The layout part of `PEAR.Functions.FunctionCallSignature` for a call whose parentheses span
 * lines, as WordPress-Core sets it (`allowMultipleArguments` false): the first argument goes on the
 * line after `(` (`ContentAfterOpenBracket`), each argument on its own line (`MultipleArguments`)
 * and `)` on a line by itself (`CloseBracketLine`). `mago format` keeps a closure or array last
 * argument on the line of `(`, as in `add_action( 'init', function () {`.
 *
 * Off by default; it is meant to run after `mago format`, as
 * `mago lint --fix --only wordpress/function-call-signature` (a step of `mago-wordpress format`).
 * An argument that moves to a line of its own takes the lines it spans with it, one indent level
 * deeper, as phpcbf's later passes do; lines inside a multi-line string or heredoc stay as they are.
 * Only the outermost call that needs fixing is changed in one run, so the edits never overlap; the
 * format command repeats the step. A call with a comment among its arguments, or with `?>` in it,
 * is left alone. The indentation checks of the sniff stay with `mago format`.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionCallSignatureRule implements Rule
{
    private const SNIFF = 'PEAR.Functions.FunctionCallSignature';

    private const TAB_WIDTH = 4;

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/function-call-signature',
            name: 'Function call signature',
            description: 'Puts the arguments of a multi-line call on lines of their own, with `)` on a line by itself, as WordPress-Core does. Off by default; run after `mago format`.',
            defaultLevel: Level::Error,
            defaultEnabled: false,
            targets: [NodeKind::ArgumentList],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $plan = self::plan($file, $context->node);
        if ($plan === null) {
            return;
        }

        for (
            $ancestor = $file->getParent($context->node);
            $ancestor !== null;
            $ancestor = $file->getParent($ancestor)
        ) {
            $outer = $ancestor->kind === NodeKind::ArgumentList ? self::plan($file, $ancestor) : null;
            if ($outer !== null && $this->reported($context, $outer)) {
                // The outer call is fixed first; this one is looked at again in the next run.
                return;
            }
        }

        foreach ($plan as [$code, $message, $span, $edits]) {
            $issue = Issue::new($message, $span);
            foreach ($edits as [$editSpan, $text]) {
                $issue = $issue->withEdit(TextEdit::replace($editSpan, $text));
            }

            $this->report->issue($context, $issue, [self::SNIFF . '.' . $code]);
        }
    }

    /**
     * Whether any of the findings would be reported, as an excluded or suppressed one is never fixed
     * and so must not hold back the calls inside it.
     *
     * @param list<array{string, string, Span, list<array{Span, string}>}> $plan
     */
    private function reported(LintContext $context, array $plan): bool
    {
        foreach ($plan as [$code, $message, $span]) {
            if ($this->report->wouldReport($context, Issue::new($message, $span), [self::SNIFF . '.' . $code])) {
                return true;
            }
        }

        return false;
    }

    /**
     * What is wrong with the call's layout, each finding with the edits that fix it alone (so a
     * finding a project excludes leaves the others fixable), or NULL when nothing is.
     *
     * @mago-expect lint:halstead
     *
     * @return null|list<array{string, string, Span, list<array{Span, string}>}>
     */
    private static function plan(SourceFile $file, Node $list): ?array
    {
        $text = $file->contents;
        $open = $list->span->start;
        $close = $list->span->end - 1;
        if (
            $text[$open] !== '('
            || $text[$close] !== ')'
            || !str_contains(substr($text, $open, $close - $open), "\n")
        ) {
            return null;
        }

        $arguments = $file->getChildren($list);
        if ($arguments === [] || str_contains(substr($text, $open, $close - $open), '?>')) {
            return null;
        }

        $lineStart = self::lineStart($text, $open);
        $indent = substr($text, $lineStart, self::leading($text, $lineStart));
        $unit = str_contains($indent, "\t") || $indent === '' && str_contains($text, "\n\t") ? "\t" : '    ';
        $inner = "\n" . $indent . $unit;

        $newWidth = self::columns($indent) + self::TAB_WIDTH;
        $found = [];

        $head = self::gap($text, $open + 1, $arguments[0]->span->start);
        if ($head === null) {
            return null;
        }

        if (!str_contains($head, "\n")) {
            $found[] = [
                'ContentAfterOpenBracket',
                'Opening parenthesis of a multi-line function call must be the last content on the line',
                new Span($open, $open + 1),
                [
                    [new Span($open + 1, $arguments[0]->span->start), $inner],
                    ...self::reindent($file, $arguments[0], $newWidth),
                ],
            ];
        }

        for ($i = 1, $count = count($arguments); $i < $count; ++$i) {
            $from = $arguments[$i - 1]->span->end;
            $to = $arguments[$i]->span->start;
            $between = self::gap($text, $from, $to);
            if ($between === null || ($comma = strpos($between, needle: ',')) === false) {
                return null;
            }

            if (str_contains(substr($between, $comma + 1), "\n")) {
                continue;
            }

            $found[] = [
                'MultipleArguments',
                'Only one argument is allowed per line in a multi-line function call',
                $arguments[$i]->span,
                [
                    [new Span($from + $comma + 1, $to), $inner],
                    ...self::reindent($file, $arguments[$i], $newWidth),
                ],
            ];
        }

        $last = $arguments[count($arguments) - 1];
        $tail = self::gap($text, $last->span->end, $close);
        if ($tail === null) {
            return null;
        }

        $comma = strpos($tail, needle: ',');
        $afterComma = $comma === false ? $tail : substr($tail, $comma + 1);
        if (!str_contains($afterComma, "\n")) {
            $found[] = [
                'CloseBracketLine',
                'Closing parenthesis of a multi-line function call must be on a line by itself',
                new Span($close, $close + 1),
                [[new Span($last->span->end + ($comma === false ? 0 : $comma + 1), $close), "\n" . $indent]],
            ];
        }

        return $found === [] ? null : $found;
    }

    /** The text between two arguments' tokens, or NULL when it holds a comment. */
    private static function gap(string $text, int $from, int $to): ?string
    {
        $gap = substr($text, $from, $to - $from);

        return preg_match('~/[/*]|\#~', $gap) === 1 ? null : $gap;
    }

    /**
     * Shifts the lines after the one an argument starts on, so they keep their place relative to it.
     *
     * @mago-expect lint:halstead
     *
     * @return list<array{Span, string}>
     */
    private static function reindent(SourceFile $file, Node $argument, int $newWidth): array
    {
        $text = $file->contents;
        $start = $argument->span->start;
        $startLine = self::lineStart($text, $start);
        $oldWidth = self::columns(substr($text, $startLine, self::leading($text, $startLine)));
        $delta = $newWidth - $oldWidth;
        if ($delta === 0) {
            return [];
        }

        $protected = self::protectedRanges($file);
        $tokens = self::tokens($file);
        $token = self::firstAtOrAfter($tokens, $start);
        $count = count($tokens);
        $depth = 0;
        $edits = [];
        $at = strpos($text, needle: "\n", offset: $start);
        while ($at !== false && $at < $argument->span->end) {
            $lineStart = $at + 1;
            // Only lines inside a bracket the argument opens move with it: an array's or closure's
            // body and its closing line. A continuation of the expression itself keeps its indent.
            for (; $token < $count && $tokens[$token]->pos < $lineStart; ++$token) {
                $depth += self::bracket($tokens[$token]);
            }

            $leading = self::leading($text, $lineStart);
            $blank =
                ($lineStart + $leading) >= strlen($text)
                || $text[$lineStart + $leading] === "\n"
                || $text[$lineStart + $leading] === "\r";
            if ($depth > 0 && !$blank && !self::isProtected($protected, $lineStart)) {
                $current = substr($text, $lineStart, $leading);
                $new = self::indentOf(
                    max(0, self::columns($current) + $delta),
                    $current !== '' ? $current : null,
                    $text,
                );
                if ($new !== $current) {
                    $edits[] = [new Span($lineStart, $lineStart + $leading), $new];
                }
            }

            $at = strpos($text, needle: "\n", offset: $lineStart);
        }

        return $edits;
    }

    /** Leading whitespace of the given width, in tabs when the line it replaces (or the file) indents with tabs. */
    private static function indentOf(int $width, ?string $current, string $text): string
    {
        $tabs = $current === null ? str_contains($text, "\n\t") : str_contains($current, "\t");

        $width = max(0, $width);

        return $tabs
            ? str_repeat("\t", max(0, intdiv($width, self::TAB_WIDTH)))
            . str_repeat(' ', max(0, $width % self::TAB_WIDTH))
            : str_repeat(' ', $width);
    }

    private static function leading(string $text, int $offset): int
    {
        return strspn($text, characters: " \t", offset: $offset);
    }

    /** +1 for a token that opens a bracket, -1 for one that closes it, 0 for any other. */
    private static function bracket(PhpToken $token): int
    {
        if ($token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES || $token->id === T_ATTRIBUTE) {
            return 1;
        }

        // Only a token of its own counts: a bracket inside string text is not one.
        return match ($token->id < 256 ? $token->text : '') {
            '(', '[', '{' => 1,
            ')', ']', '}' => -1,
            default => 0,
        };
    }

    /** @return list<PhpToken> */
    private static function tokens(SourceFile $file): array
    {
        return FileCache::remember(
            $file,
            'call-signature-tokens',
            static fn(): array => PhpToken::tokenize($file->contents),
        );
    }

    /** @param list<PhpToken> $tokens */
    private static function firstAtOrAfter(array $tokens, int $offset): int
    {
        $low = 0;
        $high = count($tokens);
        while ($low < $high) {
            $middle = ($low + $high) >> 1;
            $before = $tokens[$middle]->pos < $offset;
            $low = $before ? $middle + 1 : $low;
            $high = $before ? $high : $middle;
        }

        return $low;
    }

    /**
     * Byte ranges (exclusive at both ends) whose line starts must not be re-indented: a multi-line
     * string, heredoc, shell command or inline HTML, with every interpolation inside it.
     *
     * @return list<array{int, int}>
     */
    private static function protectedRanges(SourceFile $file): array
    {
        return FileCache::remember($file, 'call-signature-protected', static fn(): array => self::scan(self::tokens(
            $file,
        )));
    }

    /**
     * Strings nest (`"a {$o->m("b")} c"`), so the quotes are paired with a stack: a bare quote closes
     * the string on top of it, and otherwise opens a new one. `{` stands for an interpolation, `<`
     * for a heredoc.
     *
     * @param list<PhpToken> $tokens
     *
     * @mago-expect lint:halstead
     *
     * @return list<array{int, int}>
     */
    private static function scan(array $tokens): array
    {
        /** @var list<array{int, int}> $ranges */
        $ranges = [];
        /** @var list<string> $kinds */
        $kinds = [];
        /** @var list<int> $starts */
        $starts = [];
        foreach ($tokens as $token) {
            $end = $token->pos + strlen($token->text);
            $text = $token->text;
            // A delimiter is a token of its own (its id is the character); a quote or brace inside string text is not.
            $char = $token->id < 256 ? $text : '';
            $top = $kinds === [] ? '' : $kinds[count($kinds) - 1];
            $closes = match (true) {
                $token->id === T_END_HEREDOC => $top === '<',
                $char === '"', $char === '`' => $top === $char,
                $char === '}' => $top === '{',
                default => false,
            };
            if ($closes) {
                $start = (int) array_pop($starts);
                array_pop($kinds);
                if ($top !== '{') {
                    $ranges[] = [$start, $end];
                }

                continue;
            }

            $opens = match (true) {
                $token->id === T_START_HEREDOC => '<',
                $char === '"', $char === '`' => $char,
                $kinds !== []
                    && ($token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES || $char === '{')
                    => '{',
                default => '',
            };
            if ($opens !== '') {
                $kinds[] = $opens;
                $starts[] = $token->pos;

                continue;
            }

            if (
                ($token->id === T_CONSTANT_ENCAPSED_STRING || $token->id === T_INLINE_HTML)
                && str_contains($text, "\n")
            ) {
                $ranges[] = [$token->pos, $end];
            }
        }

        // Nested strings add their ranges inside out, and isProtected() reads them in order.
        usort($ranges, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        return $ranges;
    }

    /** @param list<array{int, int}> $ranges */
    private static function isProtected(array $ranges, int $lineStart): bool
    {
        foreach ($ranges as [$from, $to]) {
            if ($from > $lineStart) {
                return false;
            }

            if ($lineStart < $to && $from < $lineStart) {
                return true;
            }
        }

        return false;
    }

    private static function lineStart(string $text, int $offset): int
    {
        $newline = $offset === 0 ? false : strrpos($text, needle: "\n", offset: $offset - strlen($text) - 1);

        return $newline === false ? 0 : $newline + 1;
    }

    /** The display width of leading whitespace, tabs counted to the next multiple of 4. */
    private static function columns(string $whitespace): int
    {
        $width = 0;
        for ($i = 0, $length = strlen($whitespace); $i < $length; ++$i) {
            $width += $whitespace[$i] === "\t" ? self::TAB_WIDTH - ($width % self::TAB_WIDTH) : 1;
        }

        return $width;
    }
}
