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
use Mago\Sdk\Syntax\SourceFile;
use PhpToken;
use Rlorenzo\MagoWordPress\Internal\FileCache;
use Rlorenzo\MagoWordPress\Internal\FileTokens;
use Rlorenzo\MagoWordPress\Internal\FunctionTokens;
use Rlorenzo\MagoWordPress\Internal\PhpcsToken;
use Rlorenzo\MagoWordPress\Internal\PhpcsTokenStream;
use Rlorenzo\MagoWordPress\Internal\Report;

use function array_pop;
use function array_slice;
use function count;
use function intdiv;
use function max;
use function preg_match;
use function str_contains;
use function str_ends_with;
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
 * Ports `PEAR.Functions.FunctionCallSignature` as WordPress-Core sets it (one space inside the
 * parentheses of a single-line call, `allowMultipleArguments` false): the space before `(` and after
 * `)` before a `;`, the spaces inside a single-line call, and for a call whose parentheses span lines
 * the indentation of the call and of each line (`OpeningIndent`, `Indent`, `EmptyLine`), the first
 * argument on the line after `(` (`ContentAfterOpenBracket`, `FirstArgumentPosition`), each argument
 * on its own line (`MultipleArguments`) and `)` on a line by itself (`CloseBracketLine`).
 *
 * `mago format` keeps a closure or array last argument on the line of `(`, as in
 * `add_action( 'init', function () {`; the fix for the three layout codes moves it, together with
 * the lines it spans, one indent level deeper, as phpcbf's later passes do (lines inside a multi-line
 * string or heredoc stay as they are). Only the outermost call that needs fixing is changed in one run,
 * so the edits never overlap; `mago-wordpress format` repeats the step. A call with a comment among its
 * arguments, or with `?>` in it, is reported but not moved.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionCallSignatureRule implements Rule
{
    private const SNIFF = 'PEAR.Functions.FunctionCallSignature';

    private const TAB_WIDTH = 4;

    private const INDENT = 4;

    /** `requiredSpacesAfterOpen` and `requiredSpacesBeforeClose`. */
    private const SPACES = 1;

    private const NAMES = [
        'T_STRING' => true,
        'T_EVAL' => true,
        'T_EXIT' => true,
        'T_INCLUDE' => true,
        'T_INCLUDE_ONCE' => true,
        'T_REQUIRE' => true,
        'T_REQUIRE_ONCE' => true,
        'T_ISSET' => true,
        'T_UNSET' => true,
        'T_EMPTY' => true,
        'T_STATIC' => true,
        'T_VARIABLE' => true,
        'T_CLOSE_CURLY_BRACKET' => true,
        'T_CLOSE_SQUARE_BRACKET' => true,
        'T_CLOSE_PARENTHESIS' => true,
    ];

    /** Tokens before a `{` whose `}` then ends a dynamic name (`$o->{$name}()`), not a block. */
    private const DYNAMIC = [
        'T_OBJECT_OPERATOR' => true,
        'T_NULLSAFE_OBJECT_OPERATOR' => true,
        'T_DOUBLE_COLON' => true,
        'T_DOLLAR' => true,
    ];

    /** Tokens whose lines are not indentation at all. */
    private const SKIPPED = [
        'T_START_HEREDOC' => true,
        'T_END_HEREDOC' => true,
        'T_HEREDOC' => true,
        'T_START_NOWDOC' => true,
        'T_END_NOWDOC' => true,
        'T_NOWDOC' => true,
        'T_INLINE_HTML' => true,
    ];

    private const STRINGS = [
        'T_CONSTANT_ENCAPSED_STRING' => true,
        'T_DOUBLE_QUOTED_STRING' => true,
        'T_BACKTICK' => true,
    ];

    public function __construct(
        private readonly Report $report,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'wordpress/function-call-signature',
            name: 'Function call signature',
            description: 'Checks the layout of a function call: the spaces inside its parentheses, and for a multi-line call each argument on its own line, indented, with `)` on a line by itself.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $tokens = AssignmentInConditionRule::tokens($context);
        // The end of the call whose findings carry edits: calls inside it are fixed in a later run.
        $fixing = -1;
        foreach ($tokens as $index => $token) {
            if (!isset(self::NAMES[$token->code])) {
                continue;
            }

            $open = PhpcsTokenStream::next($tokens, $index);
            if ($open === null || $tokens[$open]->code !== 'T_OPEN_PARENTHESIS' || $tokens[$open]->closer === null) {
                continue;
            }

            if (!self::isCall($tokens, $index)) {
                continue;
            }

            $close = $tokens[$open]->closer;
            $findings = $this->call($context, $tokens, $index, $open, $close, $tokens[$open]->pos < $fixing);
            foreach ($findings as [$code, $message, $at, $edits]) {
                $issue = FunctionTokens::withEdits(Issue::new($message, new Span($at, $at + 1)), $edits);
                if ($this->report->issue($context, $issue, [self::SNIFF . '.' . $code]) && $edits !== []) {
                    $fixing = max($fixing, $tokens[$close]->pos);
                }
            }
        }
    }

    /** @param list<PhpcsToken> $tokens */
    private static function isCall(array $tokens, int $name): bool
    {
        $before = $name;
        do {
            $before = PhpcsTokenStream::previous($tokens, $before);
        } while ($before !== null && $tokens[$before]->code === 'T_BITWISE_AND');

        if ($before !== null && $tokens[$before]->code === 'T_FUNCTION') {
            return false;
        }

        if ($tokens[$name]->code !== 'T_CLOSE_CURLY_BRACKET') {
            return true;
        }

        // A `}` that closes a block is not a call; one that closes `$o->{$name}` is.
        $opener = $tokens[$name]->opener;
        $prior = $opener === null ? null : PhpcsTokenStream::previous($tokens, $opener);

        return $prior !== null && isset(self::DYNAMIC[$tokens[$prior]->code]);
    }

    /**
     * Everything the sniff reports for one call, as [code, message, offset, edits].
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<array{string, string, int, list<TextEdit>}>
     * @mago-expect lint:excessive-parameter-list
     */
    private function call(LintContext $context, array $tokens, int $name, int $open, int $close, bool $nested): array
    {
        $found = [];
        $nameToken = $tokens[$name];
        if (($name + 1) !== $open) {
            $found[] = [
                'SpaceBeforeOpenBracket',
                'Space before opening parenthesis of function call prohibited',
                $nameToken->pos,
                [TextEdit::delete(new Span($nameToken->pos + strlen($nameToken->text), $tokens[$open]->pos))],
            ];
        }

        $next = FunctionTokens::nextNonWhitespace($tokens, $close);
        if ($next !== null && $tokens[$next]->code === 'T_SEMICOLON' && $next !== ($close + 1)) {
            $found[] = [
                'SpaceAfterCloseBracket',
                'Space after closing parenthesis of function call prohibited',
                $tokens[$close]->pos,
                [TextEdit::delete(new Span($tokens[$close]->pos + 1, $tokens[$next]->pos))],
            ];
        }

        if ($tokens[$open]->line === $tokens[$close]->line) {
            return [...$found, ...self::singleLine($tokens, $context->file->contents, $name, $open, $close)];
        }

        return [...$found, ...self::multiLine($context->file, $tokens, $name, $open, $close, $nested)];
    }

    /**
     * @param list<PhpcsToken> $tokens
     *
     * @return list<array{string, string, int, list<TextEdit>}>
     */
    private static function singleLine(array $tokens, string $source, int $name, int $open, int $close): array
    {
        if ($close === ($open + 1)) {
            return [];
        }

        $found = [];
        // A call with no arguments or comments keeps no space inside.
        $required = FunctionTokens::nextNonWhitespace($tokens, $open, $close) !== null ? self::SPACES : 0;
        $after = $tokens[$open + 1];
        $spaceAfter = $after->code === 'T_WHITESPACE' ? FunctionTokens::width($after, $source) : 0;
        if ($required === 0 && $after->code === 'T_WHITESPACE') {
            $found[] = [
                'SpaceAfterOpenBracket',
                'Space after opening parenthesis of function call prohibited',
                $tokens[$name]->pos,
                [TextEdit::delete(new Span($after->pos, $after->pos + strlen($after->text)))],
            ];
        } elseif ($required > 0 && $spaceAfter !== $required) {
            $found[] = [
                'SpaceAfterOpenBracket',
                "Expected {$required} spaces after opening parenthesis; {$spaceAfter} found",
                $tokens[$name]->pos,
                [self::pad($after, $tokens[$open]->pos + 1, $required)],
            ];
        }

        $prev = FunctionTokens::previousNonWhitespace($tokens, $close, $open);
        if ($prev !== null && ($tokens[$prev]->code === 'T_END_HEREDOC' || $tokens[$prev]->code === 'T_END_NOWDOC')) {
            // A heredoc needs a line break after it.
            return $found;
        }

        $before = $tokens[$close - 1];
        $spaceBefore = $before->code === 'T_WHITESPACE' ? FunctionTokens::width($before, $source) : 0;
        if ($spaceBefore !== $required) {
            $found[] = [
                'SpaceBeforeCloseBracket',
                "Expected {$required} spaces before closing parenthesis; {$spaceBefore} found",
                $tokens[$close]->pos,
                [self::pad($before, $tokens[$close]->pos, $required)],
            ];
        }

        return $found;
    }

    /** Replaces the whitespace token with $count spaces, or inserts them at $at when there is none. */
    private static function pad(PhpcsToken $token, int $at, int $count): TextEdit
    {
        $padding = str_repeat(' ', max(0, $count));
        if ($token->code !== 'T_WHITESPACE') {
            return TextEdit::insert($at, $padding);
        }

        return TextEdit::replace(new Span($token->pos, $token->pos + strlen($token->text)), $padding);
    }

    /**
     * @param list<PhpcsToken> $tokens
     *
     * @return list<array{string, string, int, list<TextEdit>}>
     * @mago-expect lint:excessive-parameter-list
     */
    private static function multiLine(
        SourceFile $file,
        array $tokens,
        int $name,
        int $open,
        int $close,
        bool $nested,
    ): array {
        $source = $file->contents;
        $found = [];
        $nameToken = $tokens[$name];
        $lineStart = self::lineStart($source, $nameToken->pos);
        // A line that starts inside a multi-line string is indented like the line the string starts on.
        for (
            $before = $name - 1;
            $before >= 0 && FunctionTokens::endLine($tokens[$before]) === $nameToken->line;
            $before--
        ) {
            // Back to the first token of the line.
        }

        $string = $tokens[$before + 1];
        if ($string->code === 'T_CONSTANT_ENCAPSED_STRING' && $string->line < $nameToken->line) {
            $lineStart = self::lineStart($source, $string->pos);
        }

        $leading = self::leading($source, $lineStart);
        $indentText = substr($source, $lineStart, $leading);
        $foundIndent = self::columns($indentText);
        $functionIndent = intdiv($foundIndent, self::TAB_WIDTH) * self::TAB_WIDTH;
        if ($foundIndent !== $functionIndent) {
            $found[] = [
                'OpeningIndent',
                "Opening statement of multi-line function call not indented correctly; expected {$functionIndent} spaces but found {$foundIndent}",
                $lineStart + $leading,
                [
                    TextEdit::replace(
                        new Span($lineStart, $lineStart + $leading),
                        self::indentOf($functionIndent, $indentText !== '' ? $indentText : null, $source),
                    ),
                ],
            ];
        }

        $fixes = $nested ? [] : self::fixes($file, $tokens, $open, $close);
        $first = PhpcsTokenStream::next($tokens, $open);
        if ($first !== null && $tokens[$first]->line === $tokens[$open]->line) {
            $found[] = [
                'ContentAfterOpenBracket',
                'Opening parenthesis of a multi-line function call must be the last content on the line',
                $nameToken->pos,
                $fixes['ContentAfterOpenBracket'] ?? [],
            ];
        }

        $prev = FunctionTokens::previousNonWhitespace($tokens, $close);
        if ($prev !== null && FunctionTokens::endLine($tokens[$prev]) === $tokens[$close]->line) {
            $found[] = [
                'CloseBracketLine',
                'Closing parenthesis of a multi-line function call must be on a line by itself',
                $tokens[$close]->pos,
                $fixes['CloseBracketLine'] ?? [],
            ];
        }

        $firstCode = FunctionTokens::nextNonWhitespace($tokens, $open);
        if ($firstCode !== null && $tokens[$firstCode]->line > ($tokens[$open]->line + 1)) {
            $space = $tokens[$firstCode - 1];
            $found[] = [
                'FirstArgumentPosition',
                'The first argument in a multi-line function call must be on the line after the opening parenthesis',
                $tokens[$firstCode]->pos,
                $space->code === 'T_WHITESPACE' && ($firstCode - 1) > $open
                    ? [
                        TextEdit::replace(
                            new Span($space->pos, $space->pos + strlen($space->text)),
                            FunctionTokens::collapseBlankLines($space->text),
                        ),
                    ]
                    : [],
            ];
        }

        $lines = self::lines($tokens, $source, $open, $close, $foundIndent, $fixes);
        $edited = false;
        foreach ($found as [, , , $edits]) {
            $edited = $edited || $edits !== [];
        }

        foreach ($lines as [$code, $message, $at, $edits]) {
            // An indentation fix would overlap the moves of the other fixes; those come first.
            $found[] = [$code, $message, $at, $code === 'Indent' && ($edited || $nested) ? [] : $edits];
        }

        return $found;
    }

    /**
     * The edits that put a call's arguments on lines of their own, with the lines each argument spans
     * moved along with it; keyed by the finding they fix (`MultipleArguments:<offset of the argument>`).
     * Empty when a comment sits among the arguments.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return array<string, list<TextEdit>>
     */
    private static function fixes(SourceFile $file, array $tokens, int $open, int $close): array
    {
        $text = $file->contents;
        $openAt = $tokens[$open]->pos;
        $closeAt = $tokens[$close]->pos;
        $arguments = self::arguments($tokens, $open, $close);
        if ($arguments === [] || str_contains(substr($text, $openAt, $closeAt - $openAt), '?>')) {
            return [];
        }

        $lineStart = self::lineStart($text, $openAt);
        $indent = substr($text, $lineStart, self::leading($text, $lineStart));
        $unit = str_contains($indent, "\t") || $indent === '' && str_contains($text, "\n\t") ? "\t" : '    ';
        $inner = "\n" . $indent . $unit;
        $newWidth = self::columns($indent) + self::TAB_WIDTH;
        $fixes = [];

        $head = self::gap($text, $openAt + 1, $arguments[0][0]);
        if ($head === null) {
            return [];
        }

        if (!str_contains($head, "\n")) {
            $fixes['ContentAfterOpenBracket'] = [
                TextEdit::replace(new Span($openAt + 1, $arguments[0][0]), $inner),
                ...self::reindent($file, $arguments[0][0], $arguments[0][1], $newWidth),
            ];
        }

        for ($i = 1, $count = count($arguments); $i < $count; ++$i) {
            $from = $arguments[$i - 1][1];
            $to = $arguments[$i][0];
            $between = self::gap($text, $from, $to);
            if ($between === null || ($comma = strpos($between, needle: ',')) === false) {
                return [];
            }

            if (!str_contains(substr($between, $comma + 1), "\n")) {
                $fixes['MultipleArguments:' . $to] = [
                    TextEdit::replace(new Span($from + $comma + 1, $to), $inner),
                    ...self::reindent($file, $to, $arguments[$i][1], $newWidth),
                ];
            }
        }

        $tail = self::gap($text, $arguments[$count - 1][1], $closeAt);
        if ($tail === null) {
            return [];
        }

        $comma = strpos($tail, needle: ',');
        $afterComma = $comma === false ? $tail : substr($tail, $comma + 1);
        if (!str_contains($afterComma, "\n")) {
            $fixes['CloseBracketLine'] = [
                TextEdit::replace(
                    new Span($arguments[$count - 1][1] + ($comma === false ? 0 : $comma + 1), $closeAt),
                    "\n" . $indent,
                ),
            ];
        }

        return $fixes;
    }

    /**
     * The byte range of each argument, from its first to its last token (comments around it not counted).
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<list{int, int}>
     */
    private static function arguments(array $tokens, int $open, int $close): array
    {
        $arguments = [];
        $first = null;
        $last = null;
        for ($i = $open + 1; $i <= $close; $i++) {
            $token = $tokens[$i];
            if ($i === $close || $token->code === 'T_COMMA') {
                if ($first !== null && $last !== null) {
                    $arguments[] = [
                        (int) $tokens[$first]->pos,
                        (int) ($tokens[$last]->pos + strlen($tokens[$last]->text)),
                    ];
                }

                $first = null;
                $last = null;
                continue;
            }

            if (isset(PhpcsTokenStream::EMPTY[$token->code])) {
                continue;
            }

            $first ??= $i;
            if ($token->closer !== null) {
                $i = $token->closer;
            }

            $last = $i;
        }

        return $arguments;
    }

    /**
     * The checks that walk the lines between the parentheses: `Indent`, `EmptyLine` and
     * `MultipleArguments`.
     *
     * @param list<PhpcsToken> $tokens
     * @param array<string, list<TextEdit>> $fixes
     *
     * @return list<array{string, string, int, list<TextEdit>}>
     *
     * @mago-expect lint:halstead
     * @mago-expect lint:excessive-parameter-list
     */
    private static function lines(
        array $tokens,
        string $source,
        int $open,
        int $close,
        int $foundIndent,
        array $fixes,
    ): array {
        $found = [];
        $first = PhpcsTokenStream::next($tokens, $open);
        if ($first === null) {
            return [];
        }

        $start = $tokens[$first - 1]->code === 'T_WHITESPACE' && !str_ends_with($tokens[$first - 1]->text, "\n")
            ? $first - 1
            : $first;
        $openLine = $tokens[$open]->line;
        $closeLine = $tokens[$close]->line;
        $lastLine = $openLine - 1;
        $argStart = -1;
        $argEnd = -1;
        for ($i = $start; $i < $close; $i++) {
            $token = $tokens[$i];
            $inArg = $i > $argStart && $i < $argEnd;
            foreach (self::lineEvents($tokens, $source, $i, $start, $lastLine) as [$line, $kind, $width, $at]) {
                // The lines of a comment are tokens after the one that opens it.
                $inArg = ($kind === 'comment' ? $i >= $argStart : $i > $argStart) && $i < $argEnd;
                $lastLine = $line;
                if ($kind === 'skip') {
                    continue;
                }

                $nextCode = $i;
                if ($line !== $openLine) {
                    if ($kind === 'blank') {
                        if (!$inArg) {
                            $found[] = [
                                'EmptyLine',
                                'Empty lines are not allowed in a multi-line function call',
                                $at[0],
                                [TextEdit::delete(new Span($at[0], $at[1]))],
                            ];
                        }

                        continue;
                    }

                    if ($kind === 'indent') {
                        $nextCode = FunctionTokens::nextNonWhitespace($tokens, $i, $close + 1) ?? $i;
                    }

                    if ($kind === 'comment' && $width === null) {
                        // A blank line inside a block comment: nothing to indent.
                        continue;
                    }

                    if ($tokens[$nextCode]->line === $closeLine) {
                        $inArg = false;
                        $expected = $foundIndent;
                    } else {
                        $expected = $foundIndent + self::INDENT;
                    }

                    $have = $width ?? 0;
                    if ($have < $expected || !$inArg && $expected !== $have) {
                        $found[] = [
                            'Indent',
                            "Multi-line function call not indented correctly; expected {$expected} spaces but found {$have}",
                            $at[0],
                            [
                                TextEdit::replace(
                                    new Span($at[0], $at[0] + $at[2]),
                                    self::indentOf(
                                        $expected,
                                        $at[2] > 0 ? substr($source, $at[0], $at[2]) : null,
                                        $source,
                                    ),
                                ),
                            ],
                        ];
                    }
                }

                if (!$inArg) {
                    $argStart = $nextCode;
                    $argEnd = FunctionTokens::endOfStatement($tokens, $nextCode, colonEnds: false);
                }
            }

            // Within an argument the commas do not separate arguments.
            $inArg = $i > $argStart && $i < $argEnd;
            if ($token->code !== 'T_COMMA' || $inArg) {
                continue;
            }

            $next = PhpcsTokenStream::next($tokens, $i, $close);
            if ($next === null) {
                continue;
            }

            if ($token->line === $tokens[$next]->line) {
                $found[] = [
                    'MultipleArguments',
                    'Only one argument is allowed per line in a multi-line function call',
                    $tokens[$next]->pos,
                    $fixes['MultipleArguments:' . $tokens[$next]->pos] ?? [],
                ];
            }

            $argStart = $next;
            $argEnd = FunctionTokens::endOfStatement($tokens, $next, colonEnds: false);
        }

        return $found;
    }

    /**
     * The lines that start at a token as phpcs sees them: a physical line's first token. A run of
     * whitespace gives one event per line it starts (`blank` for a line break alone, `indent` for
     * indentation), a block or doc comment one per continuation line, a token that starts a new line
     * itself a `token` event; strings, heredocs and inline HTML are skipped.
     *
     * @param list<PhpcsToken> $tokens
     *
     * @return list<array{int, string, ?int, array{int, int, int}}> [line, kind, indent width, [offset, end of blank line, indent length]]
     */
    private static function lineEvents(array $tokens, string $source, int $i, int $start, int $lastLine): array
    {
        $token = $tokens[$i];
        $code = $token->code;
        $text = $token->text;
        if ($code === 'T_WHITESPACE') {
            $events = [];
            $newline = strpos($text, "\n");
            if ($newline === false) {
                // Whitespace without a line break starts a line only as the call's first token.
                return (
                    ($i === $start || self::afterOpenTagLine($tokens[$i - 1])) && $token->line !== $lastLine
                        ? [[
                            $token->line,
                            'indent',
                            FunctionTokens::width($token, $source),
                            [$token->pos, 0, strlen($text)],
                        ]]
                        : []
                );
            }

            $offset = $newline + 1;
            $line = $token->line;
            while (true) {
                ++$line;
                $end = strpos($text, "\n", $offset);
                if ($end === false) {
                    $indent = substr($text, $offset);
                    if ($indent !== '') {
                        $events[] = [
                            $line,
                            'indent',
                            FunctionTokens::indentWidth($indent),
                            [$token->pos + $offset, 0, strlen($indent)],
                        ];
                    }

                    break;
                }

                $events[] = [$line, 'blank', null, [$token->pos + $offset, $token->pos + $end + 1, 0]];
                $offset = $end + 1;
            }

            // At the start only the last line of the run counts: the one the first argument is on.
            return $i === $start ? array_slice($events, -1) : $events;
        }

        $kind = isset(self::SKIPPED[$code]) ? 'skip' : 'token';
        $events = [];
        if ($token->line !== $lastLine) {
            $events[] = [$token->line, $kind, 0, [$token->pos, 0, 0]];
        }

        if (($code === 'T_COMMENT' || $code === 'T_DOC_COMMENT') && str_contains($text, "\n")) {
            $offset = 0;
            $line = $token->line;
            while (($newline = strpos($text, "\n", $offset)) !== false) {
                $offset = $newline + 1;
                ++$line;
                $length = strspn($text, " \t", $offset);
                $blank =
                    ($offset + $length) >= strlen($text)
                    || $text[$offset + $length] === "\n"
                    || $text[$offset + $length] === "\r";
                $events[] = [
                    $line,
                    'comment',
                    $blank ? null : FunctionTokens::indentWidth(substr($text, $offset, $length)),
                    [$token->pos + $offset, 0, $length],
                ];
            }
        } elseif (str_contains($text, "\n") && (isset(self::STRINGS[$code]) || $code === 'T_INLINE_HTML')) {
            // Lines inside a string or inline HTML: not indentation.
            $events[] = [FunctionTokens::endLine($token), 'skip', null, [$token->pos, 0, 0]];
        }

        return $events;
    }

    /** An open tag that ends with its line break: the whitespace after it starts a line. */
    private static function afterOpenTagLine(PhpcsToken $token): bool
    {
        return $token->code === 'T_OPEN_TAG' && str_ends_with($token->text, "\n");
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
     * @return list<TextEdit>
     */
    private static function reindent(SourceFile $file, int $start, int $end, int $newWidth): array
    {
        $text = $file->contents;
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
        while ($at !== false && $at < $end) {
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
                    $edits[] = TextEdit::replace(new Span($lineStart, $lineStart + $leading), $new);
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
        return FileTokens::raw($file);
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
